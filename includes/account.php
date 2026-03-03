<?php
/**
 * Handle "Remind admin to review" link: resend campaign pending email and redirect to account.
 */
function cm_maybe_remind_admin_campaign() {
    if ( ! is_user_logged_in() || ! isset( $_GET['cm_remind_campaign'] ) || ! isset( $_GET['_wpnonce'] ) ) {
        return;
    }
    $campaign_id = (int) $_GET['cm_remind_campaign'];
    if ( $campaign_id < 1 ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cm_remind_campaign_' . $campaign_id ) ) {
        return;
    }
    $campaign = get_post( $campaign_id );
    if ( ! $campaign || $campaign->post_type !== 'campaign' || $campaign->post_status !== 'draft' ) {
        return;
    }
    if ( (int) $campaign->post_author !== get_current_user_id() ) {
        return;
    }
    $account_url = get_permalink( (int) get_option( 'fxm_members_account_page_id' ) );
    if ( ! $account_url ) {
        $account_url = home_url( '/' );
    }
    if ( function_exists( 'cm_send_admin_campaign_pending_email' ) ) {
        cm_send_admin_campaign_pending_email( $campaign_id );
    }
    wp_safe_redirect( add_query_arg( 'cm_remind_sent', '1', $account_url ) );
    exit;
}
add_action( 'template_redirect', 'cm_maybe_remind_admin_campaign', 5 );

function fxm_user_actions_user_last_login( $user_login, $user ) {
    update_user_meta( $user->ID, 'user_last_login', time() );
}

add_action( 'wp_login', 'fxm_user_actions_user_last_login', 10, 2 );

function fxm_user_table( $column ) {
    $column['registration_date'] = __( 'Registration Date', 'wp-charity' );

    unset( $column['posts'] );

    return $column;
}

add_filter( 'manage_users_columns', 'fxm_user_table' );

function fxm_user_table_row( $val, $column_name, $user_id ) {
    switch ( $column_name ) {
        case 'registration_date':
            $date_format = 'j M, Y H:i';

            return gmdate( $date_format, strtotime( get_the_author_meta( 'registered', $user_id ) ) );

            break;
        default:
    }

    return $val;
}

add_filter( 'manage_users_custom_column', 'fxm_user_table_row', 10, 3 );



function fxm_signup_form( $atts, $content = false ) {
    $out = '<form action="#" method="post" name="register-form" class="fxm-form" autocomplete="off">
        <p class="register-message" style="display:none"></p>
        <p style="display:none">
            <label class="block" for="new-username">' . __( 'Username', 'wp-charity' ) . '</label>
            <input type="text" name="new_user_name" id="new-username" autocomplete="off">
        </p>
        <div class="wp-block-columns" style="gap:2em">
            <div class="wp-block-column">
                <p>
                    <label class="block" for="new-useremail">' . __( 'Email Address', 'wp-charity' ) . ' <span>*</span></label>
                    <input type="email" name="new_user_email" id="new-useremail" autocomplete="on">
                </p>
            </div>
            <div class="wp-block-column">
                <p>
                    <label class="block" for="new-userphone">' . __( 'Phone Number', 'wp-charity' ) . '</label>
                    <input type="text" name="new_user_phone" id="new-userphone" autocomplete="on">
                </p>
            </div>
        </div>
        <div class="wp-block-columns" style="gap:2em">
            <div class="wp-block-column">
                <p>
                    <label class="block" for="new-firstname">' . __( 'First Name', 'wp-charity' ) . ' <span>*</span></label>
                    <input type="text" name="new_firstname" id="new-firstname" autocomplete="on">
                </p>
            </div>
            <div class="wp-block-column">
                <p>
                    <label class="block" for="new-lastname">' . __( 'Last Name', 'wp-charity' ) . ' <span>*</span></label>
                    <input type="text" name="new_lastname" id="new-lastname" autocomplete="on">
                </p>
            </div>
        </div>
        <div class="wp-block-columns" style="gap:2em">
            <div class="wp-block-column">
                <p>
                    <label class="block" for="new-userpassword">' . __( 'Password', 'wp-charity' ) . ' <span>*</span></label>
                    <input type="password" name="new_user_password" id="new-userpassword" autocomplete="new-password">
                </p>
            </div>
            <div class="wp-block-column">
                <p>
                    <label class="block" for="re-pwd">' . __( 'Confirm Password', 'wp-charity' ) . ' <span>*</span></label>
                    <input type="password" name="re-pwd" id="re-pwd" autocomplete="new-password">
                </p>
            </div>
        </div>';

    if ( (int) get_option( 'wp_page_for_privacy_policy' ) > 0 ) {
        $privacy_policy_url = get_privacy_policy_url();
        $policy_page_id     = (int) get_option( 'wp_page_for_privacy_policy' );
        $page_title         = ( $policy_page_id ) ? get_the_title( $policy_page_id ) : '';

        $out .= '<p>
            <input type="checkbox" name="fxm_tc_agree" id="fxm_tc_agree" value="1">
            <label for="fxm_tc_agree">';
        /* translators: 1: Privacy policy URL, 2: Privacy policy page title */
        $out .= sprintf( __( 'I agree to the <a href="%1$s" target="_blank">%2$s</a>', 'wp-charity' ), esc_url( $privacy_policy_url ), esc_html( $page_title ) );
        $out .= ' <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="height:16px;vertical-align:text-bottom;"><path fill-rule="evenodd" d="M19 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7c0-1.1.9-2 2-2h5v2H5v12h12v-5h2Zm0-7.6-7.3 7.3-1.4-1.4L17.6 5H13V3h8v8h-2V6.4Z"></path></svg></label>
            </p>';
    }

        $out .= '<p>
            <input type="submit" class="button" id="register-button" value="' . __( 'Register', 'wp-charity' ) . '">
        </p>
    </form>';

    return $out;
}

add_shortcode( 'fxm-signup', 'fxm_signup_form' );

add_action( 'wp_ajax_fxm_register_user_front_end', 'fxm_register_user_front_end' );
add_action( 'wp_ajax_nopriv_fxm_register_user_front_end', 'fxm_register_user_front_end' );

/**
 * Send a GET webhook to the configured GHL URL when a user registers.
 * Includes name, email, and phone if available.
 */
function cm_send_ghl_webhook_on_register( $user_id ) {
    $webhook_url = trim( (string) get_option( 'fxm_webhook_ghl' ) );
    if ( empty( $webhook_url ) ) {
        return;
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return;
    }

    $first = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
    $last  = trim( (string) get_user_meta( $user_id, 'last_name', true ) );
    $name  = trim( $first . ' ' . $last );
    if ( '' === $name ) {
        $dn = (string) $user->display_name;
        if ( '' === $dn ) {
            $dn = (string) $user->user_login;
        }
        $name = $dn;
    }

    // Try common phone meta keys; fall back to request payload if present.
    $phone = (string) get_user_meta( $user_id, 'billing_phone', true );
    if ( '' === $phone ) {
        $phone = (string) get_user_meta( $user_id, 'phone', true );
    }
    if ( '' === $phone ) {
        if ( isset( $_POST['phone'] ) ) {
            $phone = sanitize_text_field( wp_unslash( $_POST['phone'] ) );
        } elseif ( isset( $_POST['new_phone'] ) ) {
            $phone = sanitize_text_field( wp_unslash( $_POST['new_phone'] ) );
        }
    }

    $args      = [
        'name'  => $name,
        'email' => (string) $user->user_email,
        'phone' => $phone,
    ];
    $hook_call = add_query_arg( $args, $webhook_url );

    // Fire-and-forget; don't block registration on webhook issues.
    $response = wp_remote_get( $hook_call, [ 'timeout' => 5 ] );
    if ( is_wp_error( $response ) ) {
        // Optional: uncomment for debugging
        // error_log( 'GHL webhook failed for user ' . $user_id . ': ' . $response->get_error_message() );
    }
}

// Also capture registrations from other flows (e.g., wp-admin or other forms)
add_action( 'user_register', 'cm_send_ghl_webhook_on_register', 20, 1 );

/**
 * Send a POST webhook to the configured GHL approval URL when a campaign is approved (draft → publish).
 * Payload: name, email, phone, volunteer-portal-page-url (JSON).
 */
function cm_send_approval_webhook_ghl( $new_status, $old_status, $post ) {
    if ( $post->post_type !== 'campaign' || $new_status !== 'publish' || $old_status !== 'draft' ) {
        return;
    }

    $webhook_url = trim( (string) get_option( 'fxm_approval_webhook_ghl' ) );
    if ( empty( $webhook_url ) ) {
        return;
    }

    $user_id = (int) $post->post_author;
    $user    = get_userdata( $user_id );
    if ( ! $user || ! is_email( $user->user_email ) ) {
        return;
    }

    $first = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
    $last  = trim( (string) get_user_meta( $user_id, 'last_name', true ) );
    $name  = trim( $first . ' ' . $last );
    if ( '' === $name ) {
        $dn = (string) $user->display_name;
        if ( '' === $dn ) {
            $dn = (string) $user->user_login;
        }
        $name = $dn;
    }

    $phone = (string) get_user_meta( $user_id, 'billing_phone', true );
    if ( '' === $phone ) {
        $phone = (string) get_user_meta( $user_id, 'phone', true );
    }

    $campaign_url = get_permalink( $post->ID );
    if ( ! $campaign_url ) {
        return;
    }

    $payload = [
        'name'                       => $name,
        'email'                      => (string) $user->user_email,
        'phone'                      => $phone,
        'volunteer-portal-page-url'  => $campaign_url,
    ];

    wp_remote_post(
        $webhook_url,
        [
            'timeout' => 10,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
        ]
    );
}
add_action( 'transition_post_status', 'cm_send_approval_webhook_ghl', 10, 3 );

function fxm_register_user_front_end() {
    $new_user_email    = stripcslashes( $_POST['new_user_email'] );
    $new_user_phone    = isset( $_POST['new_user_phone'] ) ? sanitize_text_field( $_POST['new_user_phone'] ) : '';
    $new_user_password = $_POST['new_user_password'];
    $user_nice_name    = strtolower( $_POST['new_user_email'] );
    $loginurl          = get_permalink( (int) get_option( 'fxm_members_account_page_id' ) );

    // Derive display name from provided first/last names if available
    $new_firstname = isset( $_POST['new_firstname'] ) ? sanitize_text_field( $_POST['new_firstname'] ) : '';
    $new_lastname  = isset( $_POST['new_lastname'] ) ? sanitize_text_field( $_POST['new_lastname'] ) : '';
    $display_name  = trim( $new_firstname . ' ' . $new_lastname );
    if ( '' === $display_name ) {
        $display_name = $new_user_email;
    }

    $user_data = [
        'user_login'    => $new_user_email,
        'user_email'    => $new_user_email,
        'user_pass'     => $new_user_password,
        'user_nicename' => $user_nice_name,
        'display_name'  => $display_name,
        'role'          => 'volunteer',
    ];

    $user_id = wp_insert_user( $user_data );

    if ( ! is_wp_error( $user_id ) ) {
        if ( isset( $_POST['new_firstname'] ) ) {
            update_user_meta( $user_id, 'first_name', sanitize_text_field( $_POST['new_firstname'] ) );
        }

        if ( isset( $_POST['new_lastname'] ) ) {
            update_user_meta( $user_id, 'last_name', sanitize_text_field( $_POST['new_lastname'] ) );
        }
        if ( ! empty( $new_user_phone ) ) {
            update_user_meta( $user_id, 'billing_phone', $new_user_phone );
        }

        // Trigger new user email notification (to user)
        wp_send_new_user_notifications( $user_id, 'user' );

        echo '<p>' . __( 'Registration successful!', 'wp-charity' ) . '</p>';
        echo '<p><a href="' . $loginurl . '">' . __( 'Log in', 'wp-charity' ) . '</a></p>';
    } else {
        if ( isset( $user_id->errors['empty_user_login'] ) ) {
            $notice_key = __( 'Username and email are mandatory', 'wp-charity' );

            echo $notice_key;
        } elseif ( isset( $user_id->errors['existing_user_email'] ) ) {
            echo __( 'Sorry, that email address is already used!', 'wp-charity' );
        } elseif ( isset( $user_id->errors['existing_user_login'] ) ) {
            echo __( 'Username already exists.', 'wp-charity' );
        } else {
            echo __( 'Error: please fill up the sign up form carefully.', 'wp-charity' );
        }
    }

    wp_die();
}



/**
 * Simple WordPress frontend login implementation
 *
 * Usage: use the [fxm-login] shortcode or the fxm_login_form() template tag
 */

// login action hook - catches form submission and acts accordingly
add_action( 'init', 'fxm_login_action' );

function fxm_login_action() {
    global $tiny_error;

    $tiny_error = false;

    if ( isset( $_POST['tiny_email'] ) && isset( $_POST['password'] ) ) {
        $creds = [];

        // Get username from email address
        $user_object = get_user_by( 'email', sanitize_email( $_POST['tiny_email'] ) );

        if ( ! empty( $user_object->user_login ) ) {
            $username = $user_object->user_login;
        }

        $creds['user_login']    = $username;
        $creds['user_password'] = sanitize_text_field( $_POST['password'] );

        $user = wp_signon( $creds );

        if ( is_wp_error( $user ) ) {
            $tiny_error = $user->get_error_message();
        } else {
            if ( isset( $_POST['redirect'] ) && $_POST['redirect'] ) {
                wp_redirect( $_POST['redirect'] );

                exit;
            }
        }
    }
}

function fxm_error() {
    global $tiny_error;

    if ( $tiny_error ) {
        $return     = $tiny_error;
        $tiny_error = false;

        return $return;
    } else {
        return false;
    }
}

// shows login form (or a message, if user already logged in)
function fxm_login_form() {
    $redirect = (int) get_option( 'fxm_members_account_page_id' ) > 0 ? get_permalink( (int) get_option( 'fxm_members_account_page_id' ) ) : home_url();

    if ( ! is_user_logged_in() ) {
        $return = '<form action="#" method="post" name="register-form" class="fxm-form" autocomplete="off">';

        $error = fxm_error();

        if ( $error ) {
            $return .= '<p class="error">' . $error . '</p>';
        }

        if ( isset( $_GET['rp'] ) && sanitize_text_field( $_GET['rp'] ) === 's' ) {
            $return .= '<p class="register-message" style="display: block; background-color: #7bed9f">' . __( 'Password reset successful. Please login with your new password.', 'wp-charity' ) . '</p>';
        }

        $return .= '<p>
            <label class="block" for="tiny_email">' . __( 'Email Address', 'wp-charity' ) . '</label>
            <input type="text" id="tiny_email" name="tiny_email" value="' . ( isset( $_POST['tiny_email'] ) ? $_POST['tiny_email'] : '' ) . '" autocomplete="username">
        </p>';

        $return .= '<p>
            <label class="block" for="tiny_password">' . __( 'Password', 'wp-charity' ) . '</label>
            <input type="password" id="tiny_password" name="password" autocomplete="current-password">
        </p>';

        $return .= '<input type="hidden" name="redirect" value="' . $redirect . '">';

        $return .= '<button type="submit">' . __( 'Login', 'wp-charity' ) . '</button>';
        $return .= '</form>';
    } else {
        $return = '<p>' . __( 'You are already logged in.', 'wp-charity' ) . '</p>';
    }

    return $return;
}

add_shortcode( 'fxm-login', 'fxm_login_form' );



function fxm_forgot_password_form( $redirect ) {
    $out = '<form id="lostpasswordform" action="" method="post" class="fxm-form fxm-reset-password-form">
        <input type="hidden" name="action" value="reset">

        <p class="register-message" style="display:none"></p>

        <p>' . __( 'Lost your password? Please enter your username or email address. You will receive a link to create a new password via email.', 'wp-charity' ) . '</p>

        <p>
            <label class="block" for="user_login">' . __( 'Email', 'wp-charity' ) . '</label>
            <input type="text" name="user_login" id="user_login">
        </p>
        <p class="submit">
            <input type="submit" name="submit" id="submit" value="' . __( 'Get New Password', 'wp-charity' ) . '">
        </p>
    </form>';

    return $out;
}

function wppd_tiny_ajax_password_reset() {
    $email_address = $_POST['user_login'];
    $user_data     = get_user_by( 'email', trim( wp_unslash( $email_address ) ) );

    if ( empty( $user_data ) ) {
        echo json_encode( [ 'status' => 'error' ] );
        exit;
    }

    $user_login = $user_data->user_login;
    $user_email = $user_data->user_email;

    do_action( 'retrieve_password', $user_login );

    $allow = apply_filters( 'allow_password_reset', true, $user_data->ID );

    if ( ! $allow ) {
        echo json_encode( [ 'status' => 'error' ] );
        exit;
    } elseif ( is_wp_error( $allow ) ) {
        echo json_encode( [ 'status' => 'error' ] );
        exit;
    }
    $key = get_password_reset_key( $user_data );

    if ( is_wp_error( $key ) ) {
        echo json_encode( [ 'status' => 'error' ] );
        exit;
    }

    $message  = '<p>' . __( 'Someone has requested a password reset for the following account:', 'wp-charity' ) . '</p>';
    $message .= '<p>' . network_home_url( '/' ) . '</p>';
    // translators: %s: Username of the account requesting a password reset
    $message .= '<p>' . sprintf( __( 'Username: %s', 'wp-charity' ), $user_login ) . '</p>';
    $message .= '<p>' . __( 'If this was a mistake, just ignore this email and nothing will happen.', 'wp-charity' ) . '</p>';
    $message .= '<p>' . __( 'To reset your password, visit the following address:', 'wp-charity' ) . '</p>';
    $message .= '<p>
        <a href="' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . '">' . __( 'Reset Password', 'wp-charity' ) . '</a>
    </p>';

    if ( is_multisite() ) {
        $blogname = $GLOBALS['current_site']->site_name;
    } else {
        $blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
    }

    // translators: %s: Site name used in the email subject
    $title = sprintf( __( '[%s] Password Reset', 'wp-charity' ), $blogname );

    $headers   = [];
    $headers[] = 'Content-Type: text/html;';
    $headers[] = 'X-Mailer: WordPress/PropertyDrive;';
    $headers[] = "Reply-To: $name <$email>;";

    if ( $message && ! wp_mail( $user_email, wp_specialchars_decode( $title ), $message, $headers ) ) {
        echo json_encode( [ 'status' => 'error' ] );
    } else {
        echo json_encode( [ 'status' => 'success' ] );
    }

    exit;
}
add_action( 'wp_ajax_nopriv_reset', 'wppd_tiny_ajax_password_reset' );



/**
 * Add custom redirect after password reset for non-admin users
 *
 * @param mixed $user
 * @return void
 */
function wppd_tiny_redirect_after_password_reset() {
    $confirm  = ( isset( $_GET['action'] ) && sanitize_text_field( $_GET['action'] ) === 'resetpass' );
    $redirect = (int) get_option( 'fxm_members_account_page_id' ) > 0 ? get_permalink( (int) get_option( 'fxm_members_account_page_id' ) ) : home_url();

    $redirect_with_params = add_query_arg( 'rp', 's', $redirect ) . '#login';

    if ( $confirm ) {
        wp_redirect( $redirect_with_params );
        exit;
    }
}
add_action( 'login_headerurl', 'wppd_tiny_redirect_after_password_reset' );



function fxm_account() {
    $out = '';

    $redirect = (int) get_option( 'fxm_members_account_page_id' ) > 0 ? get_permalink( (int) get_option( 'fxm_members_account_page_id' ) ) : home_url();

    if ( ! is_user_logged_in() ) {
        $out .= '<ul class="whiskey-tabs">
            <li><a href="#login" class="is-active">' . __( 'Login', 'wp-charity' ) . '</a></li>
            <li><a href="#signup">' . __( 'Sign Up', 'wp-charity' ) . '</a></li>
            <li><a href="#forgot" class="is-muted">' . __( 'Forgot Password?', 'wp-charity' ) . '</a></li>
        </ul>
        <section class="whiskey-tab-content" id="login">' .
            fxm_login_form( $redirect ) .
        '</section>
        <section class="whiskey-tab-content" id="signup">' .
            fxm_signup_form( $redirect ) .
        '</section>
        <section class="whiskey-tab-content" id="forgot">' .
            fxm_forgot_password_form( $redirect ) .
        '</section>';
        // Show login/signup tabs
    } else {
        // Show account details
        include_once 'fxm-users.php';

        if ( function_exists( 'fxm_account_page' ) ) {
            $out .= fxm_account_page();
        }
    }

    return $out;
}

add_shortcode( 'fxm-account', 'fxm_account' );
