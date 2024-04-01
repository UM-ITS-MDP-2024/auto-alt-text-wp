<?php
// handler.php

add_action('wp_ajax_updatethealttext', 'update_alt_text_callback');

function update_alt_text_callback() {
    error_log('Handler function called');
    // Check if the current user is logged in
    $nonce = isset($_POST['security']) ? $_POST['security'] : '';
    if ( ! wp_verify_nonce($nonce, 'umits_alt_text_nonce') ) {
        wp_send_json_error('Invalid nonce');
        wp_die();
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error('User not logged in');
        wp_die();
    }
    if (!current_user_can('edit_post', $_POST['attachment_id'])) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
    }
    // Get data from the AJAX request
    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    $photo_url = isset($_POST['photo_url']) ? esc_url($_POST['photo_url']) : '';
    $prevAltText = isset($_POST['prevAltText']) ? strval($_POST['prevAltText']) : '';
    $feedback = isset($_POST['feedback']) ? strval($_POST['feedback']) : '';
    if ($prevAltText == '') {
        $prevAltText = null;
    }
    if ($feedback == '') {
        $feedback = null;
    }
    $attachment = get_post($attachment_id);
    $imagePath = wp_get_original_image_path($attachment_id);
    // Encode the image to base64
    $imageUrl = encodeImageToDataURL($attachment, $imagePath);

    $new_alt_text = generateAltText($imageUrl, 1, $prevAltText, $feedback);

    

    // Update post meta
   
    $success_message = 'generated new text';
    $response_data = array(
            'altText' => $new_alt_text, // Include the new alt text in the response data
            'image_url' => $photo_url,
            'message' => $success_message
        );
    wp_send_json_success($response_data);
    wp_die();
}
add_action('wp_ajax_pushchange', 'push_changes');

function push_changes() {
    error_log('Handler function called');
    // Check if the current user is logged in
    $nonce = isset($_POST['security']) ? $_POST['security'] : '';
    if ( ! wp_verify_nonce($nonce, 'umits_alt_text_nonce') ) {
        wp_send_json_error('Invalid nonce');
        wp_die();
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error('User not logged in');
        wp_die();
    }
    if (!current_user_can('edit_post', $_POST['attachment_id'])) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
    }
    // Get data from the AJAX request
    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    $new_alt_text = isset($_POST['newVal']) ? sanitize_text_field(stripslashes($_POST['newVal'])) : '';


    // Update post meta
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $new_alt_text);
   
    $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    $success_message = 'Alt text updated successfully for attachment ID: ' . $attachment_id;
    $response_data = array(
            'altText' => $new_alt_text, // Include the new alt text in the response data
            'message' => $success_message
        );
    wp_send_json_success($response_data);
    wp_die();
}
?>
