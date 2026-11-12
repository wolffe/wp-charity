<?php
/** Account mutations run before rendering, with validation shared by each form. */

function cm_post_text( $key ) {
    return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
}

function cm_account_url() {
    $id = (int) get_option( 'fxm_members_account_page_id' );
    return $id && get_permalink( $id ) ? get_permalink( $id ) : home_url();
}

function cm_account_nonce_valid( $action ) {
    return (bool) wp_verify_nonce( cm_post_text( 'nonce' ), $action );
}

function cm_registration_error( $data, $privacy_required ) {
    if ( ! is_email( $data['email'] ) || '' === trim( $data['first'] ) || '' === trim( $data['last'] ) || '' === $data['password'] ) {
        return __( 'Please enter your name, a valid email address and a password.', 'wp-charity' );
    }
    if ( $data['password'] !== $data['confirmation'] ) { return __( 'Your passwords do not match.', 'wp-charity' ); }
    if ( $privacy_required && '1' !== $data['agreement'] ) { return __( 'Please agree to the privacy policy.', 'wp-charity' ); }
    return '';
}

function cm_handle_registration() {
    if ( ! cm_account_nonce_valid( 'cm_register' ) ) { wp_send_json_error( [ 'message' => __( 'Please reload the page and try again.', 'wp-charity' ) ], 403 ); }
    if ( is_user_logged_in() ) { wp_send_json_error( [ 'message' => __( 'Please log out before creating another account.', 'wp-charity' ) ], 400 ); }
    $data = [ 'email' => trim( cm_post_text( 'new_user_email' ) ), 'first' => sanitize_text_field( cm_post_text( 'new_firstname' ) ), 'last' => sanitize_text_field( cm_post_text( 'new_lastname' ) ), 'password' => cm_post_text( 'new_user_password' ), 'confirmation' => cm_post_text( 're-pwd' ), 'agreement' => cm_post_text( 'fxm_tc_agree' ) ];
    $error = cm_registration_error( $data, (int) get_option( 'wp_page_for_privacy_policy' ) > 0 );
    if ( $error ) { wp_send_json_error( [ 'message' => $error ], 400 ); }
    $user_id = wp_insert_user( [ 'user_login' => $data['email'], 'user_email' => $data['email'], 'user_pass' => $data['password'], 'first_name' => $data['first'], 'last_name' => $data['last'], 'display_name' => trim( $data['first'] . ' ' . $data['last'] ), 'role' => 'volunteer', 'meta_input' => [ 'billing_phone' => sanitize_text_field( cm_post_text( 'new_user_phone' ) ) ] ] );
    if ( is_wp_error( $user_id ) ) { wp_send_json_error( [ 'message' => wp_strip_all_tags( $user_id->get_error_message() ) ], 400 ); }
    wp_send_new_user_notifications( $user_id, 'user' );
    wp_send_json_success( [ 'message' => __( 'Registration successful! You can now log in.', 'wp-charity' ) ] );
}

function cm_handle_login() {
    global $tiny_error;
    $tiny_error = false;
    if ( 'cm_login' !== cm_post_text( 'cm_action' ) ) { return; }
    if ( ! cm_account_nonce_valid( 'cm_login' ) ) { $tiny_error = __( 'Please reload the page and try again.', 'wp-charity' ); return; }
    $email = sanitize_email( cm_post_text( 'tiny_email' ) );
    $password = cm_post_text( 'password' );
    if ( ! is_email( $email ) || '' === $password ) { $tiny_error = __( 'Please enter your email and password.', 'wp-charity' ); return; }
    $user = wp_signon( [ 'user_login' => $email, 'user_password' => $password ], is_ssl() );
    if ( is_wp_error( $user ) ) { $tiny_error = __( 'The email or password was not recognised.', 'wp-charity' ); return; }
    wp_safe_redirect( cm_account_url() );
    exit;
}

function cm_handle_password_reset() {
    if ( ! cm_account_nonce_valid( 'cm_reset' ) ) { wp_send_json_error( [ 'message' => __( 'Please reload the page and try again.', 'wp-charity' ) ], 403 ); }
    $login = trim( cm_post_text( 'user_login' ) );
    if ( '' === $login ) { wp_send_json_error( [ 'message' => __( 'Enter your email address or username.', 'wp-charity' ) ], 400 ); }
    $result = retrieve_password( $login );
    // Do not reveal whether an email address belongs to an account.
    if ( is_wp_error( $result ) && ! in_array( $result->get_error_code(), [ 'invalid_email', 'invalidcombo' ], true ) ) {
        wp_send_json_error( [ 'message' => __( 'The reset email could not be sent. Please try again later.', 'wp-charity' ) ], 400 );
    }
    wp_send_json_success( [ 'message' => __( 'If an account matches, a password reset link has been sent. Please check your email.', 'wp-charity' ) ] );
}

function cm_validate_image_upload( $field ) {
    if ( empty( $_FILES[ $field ]['name'] ) ) { return true; }
    $file = $_FILES[ $field ];
    if ( ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) { return false; }
    $types = [ 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif' ];
    $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $types );
    return ! empty( $check['ext'] ) && ! empty( $check['type'] ) && in_array( wp_get_image_mime( $file['tmp_name'] ), $types, true );
}

function cm_handle_profile_update() {
    if ( 'wppd_update_user_profile' !== cm_post_text( 'action' ) ) { return; }
    $error = '';
    $user_id = get_current_user_id();
    $email = trim( cm_post_text( 'user_email' ) );
    $password = cm_post_text( 'user_pass' );
    $confirmation = cm_post_text( 'user_pass_confirm' );
    if ( ! $user_id || ! cm_account_nonce_valid( 'wppd_update_user_profile' ) || (int) cm_post_text( 'user_id' ) !== $user_id ) {
        $error = __( 'You can only update your own profile. Please reload and try again.', 'wp-charity' );
    } elseif ( ! is_email( $email ) || ( email_exists( $email ) && (int) email_exists( $email ) !== $user_id ) ) {
        $error = __( 'Enter a valid email address that is not used by another account.', 'wp-charity' );
    } elseif ( $password !== $confirmation ) {
        $error = __( 'Your passwords do not match.', 'wp-charity' );
    } elseif ( ! cm_validate_image_upload( 'avatar' ) ) {
        $error = __( 'Choose a valid JPEG, PNG, GIF, WebP or AVIF image.', 'wp-charity' );
    }
    if ( $error ) { $GLOBALS['cm_profile_notice'] = [ 'error', $error ]; return; }
    $attachment_id = 0;
    if ( ! empty( $_FILES['avatar']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $attachment_id = media_handle_upload( 'avatar', 0 );
        if ( is_wp_error( $attachment_id ) ) { $GLOBALS['cm_profile_notice'] = [ 'error', $attachment_id->get_error_message() ]; return; }
    }
    $data = [ 'ID' => $user_id, 'user_email' => $email, 'first_name' => sanitize_text_field( cm_post_text( 'first_name' ) ), 'last_name' => sanitize_text_field( cm_post_text( 'last_name' ) ), 'description' => sanitize_textarea_field( cm_post_text( 'bio' ) ) ];
    if ( '' !== $password ) { $data['user_pass'] = $password; }
    $result = wp_update_user( $data );
    if ( is_wp_error( $result ) ) {
        if ( $attachment_id ) { wp_delete_attachment( $attachment_id, true ); }
        $GLOBALS['cm_profile_notice'] = [ 'error', $result->get_error_message() ];
        return;
    }
    if ( $attachment_id ) { update_user_meta( $user_id, 'avatar', $attachment_id ); }
    $GLOBALS['cm_profile_notice'] = [ 'success', __( 'Your profile has been updated.', 'wp-charity' ) ];
}
add_action( 'init', 'cm_handle_profile_update' );

function cm_can_edit_account_campaign( $campaign ) {
    return $campaign && 'campaign' === $campaign->post_type && current_user_can( 'edit_post', $campaign->ID ) && ( (int) $campaign->post_author === get_current_user_id() || current_user_can( 'edit_others_campaigns' ) );
}

function cm_enqueue_data_script() {
    wp_enqueue_script( 'cm-data', CM_PLUGIN_URL . 'assets/js/cm-data.js', [], CM_PLUGIN_VERSION, true );
    wp_localize_script( 'cm-data', 'cmData', [ 'url' => admin_url( 'admin-ajax.php' ), 'loading' => __( 'Loading…', 'wp-charity' ), 'error' => __( 'Unable to load this content. Please try again.', 'wp-charity' ), 'retry' => __( 'Retry', 'wp-charity' ), 'previous' => __( 'Previous', 'wp-charity' ), 'next' => __( 'Next', 'wp-charity' ), 'page' => __( 'Page', 'wp-charity' ) ] );
}
add_action( 'wp_enqueue_scripts', 'cm_enqueue_data_script' );
add_action( 'admin_enqueue_scripts', function () {
    if ( 'fxm' === ( $_GET['page'] ?? '' ) ) { cm_enqueue_data_script(); }
} );
