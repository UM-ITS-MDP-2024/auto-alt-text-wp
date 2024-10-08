<?php
/*
Plugin Name: AutoAltTextGenerator
Description: Places alt text when clicking regenerate
Version: 1.1
Author: UMITS
*/

require_once __DIR__ . '/vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'includes/handler.php';

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
                  '<textarea type="text" id="umits_alt_text_regen_text" name="regen_text" value="" placeholder="Regenerated alt text appear here"></textarea>' .
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
                        console.log('generated new alt text');
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
    // Fixed API key and prompt within the function
    $apiKey = defined('AZURE_API_KEY') ? AZURE_API_KEY : null;
    $prompt = "
    Create alt text for an image, following WCAG guidelines, at most 125 characters long.  
    Make reasonable inferences only when identifying well-known characters, locations, objects, or text that are clearly visible in the image. 
    Do not infer emotions, intentions, or any contextual meaning not directly observable in the image.
    1. Begin by describing the main subject, followed by key details, and conclude with visible contextual elements.
    2. Include relevant image text verbatim if it's integral to understanding the image.
    3. Be clear and include necessary details without over-describing.
    4. Avoid repetition and redundancy.
    5. Do not make inferences or suggestions (e.g., don't say 'this shows/means/suggests...').
    6. Do not begin with 'Alt text:'.
    7. Incorporate keywords directly relevant to the image's primary content; avoid keyword stuffing (1-2 keywords max).";


    if ($prevAltText != null) {
        $prompt .= "Please generate new alt text based on previous alt text and user feedback.\n";
        $prompt .= "Previous generated alt text: \n";
        $prompt .= $prevAltText;
    }
    
    if ($feedBack != null) {
        $prompt .= "User feedback for alt text: \n";
        $prompt .= $feedBack;
    }

    $model = 'gpt-4o';

    $client = OpenAI::factory()
    ->withBaseUri(defined('AZURE_API_BASE') ? AZURE_API_BASE . '/openai/deployments/' . $model : null)
    ->withHttpHeader('api-key', $apiKey)
    ->withQueryParam('api-version', defined('API_VERSION') ? API_VERSION : null)
    ->withOrganization(defined('OPENAI_ORGANIZATION') ? OPENAI_ORGANIZATION : null)
    ->make();

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

        $messagge = [];
        for ($i = 0; $i < count($result->choices); $i++) {
            $messagge[] = $result->choices[$i]->message->content;
        }
        return $messagge;
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

?>
