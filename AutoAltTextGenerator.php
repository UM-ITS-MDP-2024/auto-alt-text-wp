<?php
/*
Plugin Name: AutoAltTextGenerator
Description: Places alt text when clicking regenerate
Version: 1.1
Author: UMITS
*/

require_once __DIR__ . '/vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'includes/handler.php';

// Define the default prompt.
define('DEFAULT_PROMPT', "Create alt text for an image, following WCAG guidelines, at most 125 characters long.
Make reasonable inferences only when identifying well-known characters, locations, objects, or text that are clearly visible in the image.
Do not infer emotions, intentions, or any contextual meaning not directly observable in the image.
1. Begin by describing the main subject, followed by key details, and conclude with visible contextual elements.
2. Include relevant image text verbatim if it's integral to understanding the image.
3. Be clear and include necessary details without over-describing.
4. Avoid repetition and redundancy.
5. Do not make inferences or suggestions (e.g., don't say 'this shows/means/suggests...').
6. Do not begin with 'Alt text:'.
7. Incorporate keywords directly relevant to the image's primary content; avoid keyword stuffing (1-2 keywords max).");

function Regen($form_fields, $post) {
    // Add custom alt text options
    $customAltText = get_post_meta($post->ID, '_wp_attachment_image_alt', true); // Get the existing alt text
    $image_url = wp_get_attachment_url($post->ID);

    $form_fields['custom_alt_text'] = array(
        'label' => 'UMITS',
        'input' => 'html',
        'html' => '<div>' .
                  '<label for="attachments-' . $post->ID . '-custom_alt_text">Regenerate</label>' .
                  '<input type="text" id="umits_alt_text_feedback" name="feedback" value="" placeholder="Add feedback here">' .
                  '<textarea type="text" id="umits_alt_text_regen_text" name="regen_text" value="" placeholder="Regenerated alt text will appear here"></textarea>' .
                  '<button class="button regenerate-alt-text" data-attachment-id="' . $post->ID . '" data-image-url="' . $image_url . '">Regenerate</button>' .
                  '<button class="button commit-alt-text" data-attachment-id="' . $post->ID . '" data-image-url="' . $image_url . '">Commit</button>' .
                  '</div>',
    );

    return $form_fields;
}
add_filter('attachment_fields_to_edit', 'Regen', 10, 2);

function custom_admin_js() {
    // Embedding JavaScript code
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $(document).on('click', '.regenerate-alt-text', function() {
                var attachmentId = $(this).data('attachment-id');
                var photo_url = $(this).data('image-url');
                var prevAltText = $('#attachment-details-two-column-alt-text').val();
                var feedback = $('#umits_alt_text_feedback').val();
                var regenTextarea = $('#umits_alt_text_regen_text'); // Reference to the textarea
                var originalPlaceholder = regenTextarea.attr('placeholder'); // Store the original placeholder
                var nonce = '<?php echo wp_create_nonce("umits_alt_text_nonce"); ?>';

                if (typeof nonce === 'undefined' || nonce === null || nonce === '') {
                    console.error('Nonce is missing or invalid.');
                    return;
                }

                regenTextarea.val('').attr('placeholder', 'Generating...');
                // Make AJAX request
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action:'updatethealttext',
                        attachment_id: attachmentId,
                        security: nonce,
                        photo_url: photo_url,
                        prevAltText: prevAltText,
                        feedback: feedback
                    },
                    success: function(response) {
                        $('#umits_alt_text_regen_text').val(response.data.altText);
                        regenTextarea.attr('placeholder', originalPlaceholder);
                        console.log('Generated new alt text');
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating alt text: ' + error);
                    }
                });
            });

            // Event handler for commit button click
            $(document).on('click', '.commit-alt-text', function() {
                var altText = $('#umits_alt_text_regen_text').val();
                var attachmentId = $(this).data('attachment-id');
                var nonce = '<?php echo wp_create_nonce("umits_alt_text_nonce"); ?>';
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action:'pushchange',
                        attachment_id: attachmentId,
                        security: nonce,
                        newVal: altText
                    },
                    success: function(response) {
                        if ($('#attachment-details-two-column-alt-text').length) {
                            $('#attachment-details-two-column-alt-text').val(response.data.altText);
                            $('#attachment-details-two-column-alt-text').focus();
                        } else {
                            $('#attachment-details-alt-text').val(response.data.altText);
                            $('#attachment-details-alt-text').focus();
                        }
                        console.log('Alt text updated successfully');
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating alt text: ' + error);
                    }
                });
                console.log('Applied the alt text');
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'custom_admin_js');


// Add action hook for image insertion
add_action('add_attachment', 'assign_generated_alt_text');

function assign_generated_alt_text($attachment_id) {
    $attachment = get_post($attachment_id);
    
    // Check if the attachment is an image
    if (strpos($attachment->post_mime_type, 'image') !== false) {
        $imagePath = wp_get_original_image_path($attachment_id);
        // Encode the image to base64
        $imageUrl = encodeImageToDataURL($attachment, $imagePath);

        $messages = generateAltText($imageUrl);
        
        // Update the image's alt text with the first generated message
        if (!empty($messages) && !strpos($messages[0], 'Error:')) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', stripslashes($messages[0]));
        } else {
            // Handle error
            error_log('Failed to generate alt text for attachment ID ' . $attachment_id);
        }
    }
}


/**
 * Generates alt text for an image using OpenAI's GPT model.
 * 
 * @param string $imageUrl The URL of the image for which to generate alt text.
 * @return array An array of alt text options.
 */
function generateAltText($imageUrl, $option = 1, $prevAltText = null, $feedBack = null) {
    // Load settings
    $service = get_option('umits_service_selection', 'openai');

    if ($service == 'openai') {
        $apiKey = get_option('umits_openai_api_key');
        $prompt = get_option('umits_openai_prompt', DEFAULT_PROMPT);
    } else {
        $apiKey = get_option('umits_azure_api_key');
        $prompt = get_option('umits_azure_prompt', DEFAULT_PROMPT);
    }

    if ($prevAltText != null) {
        $prompt .= "\n\nNow please regenerate another alt text based on the previous alt text and feedback (if there is no feedback, just generate a different one).\n\nPrevious alt text: \n$prevAltText\n";
    }

    if ($feedBack != null) {
        $prompt .= "User feedback for alt text: \n$feedBack\n";
    }

    $model = 'gpt-4o';

    if ($service == 'openai') {
        $client = OpenAI::client($apiKey);
    } elseif ($service == 'azure_openai') {
        $client = OpenAI::factory()
        ->withBaseUri(get_option('umits_azure_api_base') . '/openai/deployments/' . $model)
        ->withHttpHeader('api-key', $apiKey)
        ->withQueryParam('api-version', get_option('umits_azure_api_version'))
        ->withOrganization(get_option('umits_azure_organization'))
        ->make();
    } else {
        error_log('Invalid service selected.');
        return ['Invalid service selected'];
    }

    try {
        $result = $client->chat()->create([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 
                'content' => [
                    ['type' => 'text',
                    'text' => $prompt],
                ]
                ],
                ['role' => 'user', 
                'content' => [
                    ['type' => 'image_url',
                    'image_url' => ['url' => $imageUrl]],
                ]
                ]
            ],
            'max_tokens' => 500,
            'n' => $option,
            'temperature' => 0.3
        ]);

        $messages = [];
        for ($i = 0; $i < count($result->choices); $i++) {
            $messages[] = $result->choices[$i]->message->content;
        }
        return $messages;
    } catch (\Exception $e) {
        error_log($e);
        return [$e->getMessage()];
    }
}

function encodeImageToDataURL($attachment, $imagePath) {
    if ($imagePath !== false) {
        $fileType = $attachment->post_mime_type;
        error_log($fileType);
        $imageData = file_get_contents($imagePath);
        $base64EncodedImage = base64_encode($imageData);
        return "data:" . $fileType . ";base64," . $base64EncodedImage;
    } else {
        error_log('Image file path could not be retrieved for attachment ID ');
    }
}

// Add settings page
add_action('admin_menu', 'umits_add_settings_page');

function umits_add_settings_page() {
    add_options_page(
        'Auto Alt Text Generator Settings', // Page title
        'Auto Alt Text', // Menu title
        'manage_options', // Capability
        'umits-alt-text-settings', // Menu slug
        'umits_render_settings_page' // Function to display the page
    );
}

add_action('admin_init', 'umits_register_settings');

function umits_register_settings() {
    register_setting('umits_alt_text_settings_group', 'umits_service_selection');
    register_setting('umits_alt_text_settings_group', 'umits_openai_api_key');
    register_setting('umits_alt_text_settings_group', 'umits_openai_prompt');
    register_setting('umits_alt_text_settings_group', 'umits_azure_api_base');
    register_setting('umits_alt_text_settings_group', 'umits_azure_api_key');
    register_setting('umits_alt_text_settings_group', 'umits_azure_organization');
    register_setting('umits_alt_text_settings_group', 'umits_azure_api_version');
    register_setting('umits_alt_text_settings_group', 'umits_azure_prompt');
}

function umits_render_settings_page() {
    // Retrieve current settings
    $service_selection = get_option('umits_service_selection', 'openai');
    $openai_api_key = get_option('umits_openai_api_key');
    $azure_api_key = get_option('umits_azure_api_key');

    // Placeholder texts
    $openai_api_key_placeholder = $openai_api_key ? 'Current API key is set' : 'API key is not set';
    $azure_api_key_placeholder = $azure_api_key ? 'Current API key is set' : 'API key is not set';
    ?>
    <div class="wrap">
        <h1>Auto Alt Text Generator Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('umits_alt_text_settings_group');
            do_settings_sections('umits-alt-text-settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Choose AI Service</th>
                    <td>
                        <select name="umits_service_selection" id="umits_service_selection">
                            <option value="openai" <?php selected($service_selection, 'openai'); ?>>OpenAI</option>
                            <option value="azure_openai" <?php selected($service_selection, 'azure_openai'); ?>>Azure OpenAI</option>
                        </select>
                    </td>
                </tr>
                <!-- OpenAI Settings -->
                <tbody id="umits_openai_settings" <?php if ($service_selection != 'openai') echo 'style="display:none;"'; ?>>
                <tr valign="top">
                    <th scope="row">OpenAI API Key</th>
                    <td>
                        <input type="password" name="umits_openai_api_key" value="" size="50" autocomplete="off" placeholder="<?php echo esc_attr($openai_api_key_placeholder); ?>" />
                        <p class="description">Leave empty to keep the current API key.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Predefined OpenAI Prompt</th>
                    <td>
                        <textarea name="umits_openai_prompt" rows="10" cols="50"><?php echo esc_textarea(get_option('umits_openai_prompt', DEFAULT_PROMPT)); ?></textarea>
                        <br>
                        <button type="button" id="restore_openai_prompt" class="button">Restore Default Prompt</button>
                        <p class="description">Define the prompt for OpenAI requests.</p>
                    </td>
                </tr>
                </tbody>
                <!-- Azure OpenAI Settings -->
                <tbody id="umits_azure_settings" <?php if ($service_selection != 'azure_openai') echo 'style="display:none;"'; ?>>
                <tr valign="top">
                    <th scope="row">Azure API Base URL</th>
                    <td>
                        <input type="text" name="umits_azure_api_base" value="<?php echo esc_attr(get_option('umits_azure_api_base')); ?>" size="50" placeholder="e.g., https://api.example.com/azure-openai-api" />
                        <p class="description">Example: https://api.example.com/azure-openai-api</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Azure API Key</th>
                    <td>
                        <input type="password" name="umits_azure_api_key" value="" size="50" autocomplete="off" placeholder="<?php echo esc_attr($azure_api_key_placeholder); ?>" />
                        <p class="description">Leave empty to keep the current API key.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Azure OpenAI Organization</th>
                    <td>
                        <input type="text" name="umits_azure_organization" value="<?php echo esc_attr(get_option('umits_azure_organization')); ?>" size="50" placeholder="Your Azure OpenAI organization ID" />
                        <p class="description">Define your Azure OpenAI organization.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Azure API Version</th>
                    <td>
                        <input type="text" name="umits_azure_api_version" value="<?php echo esc_attr(get_option('umits_azure_api_version')); ?>" size="50" placeholder="e.g., 2024-06-01" />
                        <p class="description">Example: 2024-06-01</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Predefined Azure OpenAI Prompt</th>
                    <td>
                        <textarea name="umits_azure_prompt" rows="10" cols="50"><?php echo esc_textarea(get_option('umits_azure_prompt', DEFAULT_PROMPT)); ?></textarea>
                        <br>
                        <button type="button" id="restore_azure_prompt" class="button">Restore Default Prompt</button>
                        <p class="description">Define the prompt for Azure OpenAI requests.</p>
                    </td>
                </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <script type="text/javascript">
        (function($) {
            $('#umits_service_selection').change(function() {
                if ($(this).val() == 'openai') {
                    $('#umits_openai_settings').show();
                    $('#umits_azure_settings').hide();
                } else if ($(this).val() == 'azure_openai') {
                    $('#umits_openai_settings').hide();
                    $('#umits_azure_settings').show();
                }
            }).trigger('change');

            $('#restore_openai_prompt').click(function() {
                var defaultPrompt = <?php echo json_encode(DEFAULT_PROMPT); ?>;
                $('textarea[name="umits_openai_prompt"]').val(defaultPrompt);
            });

            $('#restore_azure_prompt').click(function() {
                var defaultPrompt = <?php echo json_encode(DEFAULT_PROMPT); ?>;
                $('textarea[name="umits_azure_prompt"]').val(defaultPrompt);
            });
        })(jQuery);
    </script>
    <?php
}
?>
