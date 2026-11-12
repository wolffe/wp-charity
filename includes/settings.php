<?php
function fxm_members_menu_links() {
    // Add menu item as a child for the "campaign" CPT
    add_submenu_page( 'edit.php?post_type=campaign', __( 'WP Charity Settings', 'wp-charity' ), __( 'Settings', 'wp-charity' ), 'manage_options', 'fxm', 'fxm_build_admin_page' );
}

add_action( 'admin_menu', 'fxm_members_menu_links', 10 );

require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/how-to.php';

function fxm_build_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'wp-charity' ) ); }
    $tab     = ( filter_has_var( INPUT_GET, 'tab' ) ) ? filter_input( INPUT_GET, 'tab' ) : 'dashboard';
    $tab = in_array( $tab, [ 'dashboard', 'reports', 'help', 'how-to' ], true ) ? $tab : 'dashboard';
    $section = 'edit.php?post_type=campaign&page=fxm&tab=';
    ?>
    <div class="wrap">
        <h1><?php _e( 'WP Charity Settings', 'wp-charity' ); ?></h1>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_attr( $section ); ?>dashboard" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Dashboard', 'wp-charity' ); ?></a>
            <a href="<?php echo esc_attr( $section ); ?>reports" class="nav-tab <?php echo $tab === 'reports' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Reports', 'wp-charity' ); ?></a>
            <a href="<?php echo esc_attr( $section ); ?>help" class="nav-tab <?php echo $tab === 'help' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Help', 'wp-charity' ); ?></a>
        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=campaign&page=fxm&tab=how-to' ) ); ?>" class="nav-tab <?php echo $tab === 'how-to' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'How To', 'wp-charity' ); ?></a>
        </h2>

        <?php
        if ( $tab === 'dashboard' ) {
            global $wpdb;

            if ( isset( $_POST['save_fxm_settings'] ) && wp_verify_nonce( $_POST['save_fxm_settings_nonce_field'], 'save_fxm_settings_nonce' ) ) {
                update_option( 'fxm_members_account_page_id', (int) sanitize_text_field( wp_unslash( $_POST['fxm_members_account_page_id'] ?? 0 ) ) );

                $allowed_html          = wp_kses_allowed_html( 'post' );
                $allowed_html['style'] = [
                    'type' => true, // Add <style> tag with the type attribute allowed
                ];

                update_option( 'fxm_accent_background', sanitize_text_field( wp_unslash( $_POST['fxm_accent_background'] ?? '' ) ) );
                update_option( 'fxm_accent_text', sanitize_text_field( wp_unslash( $_POST['fxm_accent_text'] ?? '' ) ) );
                update_option( 'fxm_cta_background', sanitize_text_field( wp_unslash( $_POST['fxm_cta_background'] ?? '' ) ) );
                update_option( 'fxm_cta_text', sanitize_text_field( wp_unslash( $_POST['fxm_cta_text'] ?? '' ) ) );

                update_option( 'fxm_notifications_emails', sanitize_text_field( wp_unslash( $_POST['fxm_notifications_emails'] ?? '' ) ) );
                update_option( 'fxm_volunteer_campaign_status', sanitize_text_field( wp_unslash( $_POST['fxm_volunteer_campaign_status'] ?? 'draft' ) ) );

                update_option( 'fxm_webhook_ghl', sanitize_url( $_POST['fxm_webhook_ghl'] ) );
                update_option( 'fxm_approval_webhook_ghl', sanitize_url( $_POST['fxm_approval_webhook_ghl'] ?? '' ) );

                echo '<div class="updated notice is-dismissible"><p>' . __( 'Settings updated successfully!', 'wp-charity' ) . '</p></div>';
            }
            ?>

            <h2><?php _e( 'Dashboard', 'wp-charity' ); ?></h2>

            <form method="post">
                <?php wp_nonce_field( 'save_fxm_settings_nonce', 'save_fxm_settings_nonce_field' ); ?>

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label><?php _e( 'Account Page', 'wp-charity' ); ?></label></th>
                            <td>
                                <p>
                                    <?php
                                    wp_dropdown_pages(
                                        [
                                            'name'     => 'fxm_members_account_page_id',
                                            'echo'     => 1,
                                            'show_option_none' => __( 'Select account page...', 'wp-charity' ),
                                            'option_none_value' => 0,
                                            'selected' => get_option( 'fxm_members_account_page_id' ),
                                        ]
                                    );

                                    $fxm_members_account_page_id = (int) get_option( 'fxm_members_account_page_id' );
                                    ?>
                                    <br><small><?php _e( 'Make sure you add the', 'wp-charity' ); ?> <code>[fxm-account]</code> <?php _e( 'shortcode on this page.', 'wp-charity' ); ?></small>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e( 'UI Colours', 'wp-charity' ); ?></label></th>
                            <td>
                                <p>
                                    <label for="fxm_accent_background"><?php _e( 'Accent Background Color:', 'wp-charity' ); ?></label>
                                    <br><small><?php _e( 'This colour is used for buttons, tabs and links background.', 'wp-charity' ); ?></small>
                                    <br><input type="color" id="fxm_accent_background" name="fxm_accent_background" value="<?php echo esc_attr( get_option( 'fxm_accent_background' ) ); ?>">
                                </p>
                                <p>
                                    <label for="fxm_accent_text"><?php _e( 'Accent Text Color:', 'wp-charity' ); ?></label>
                                    <br><small><?php _e( 'This colour is used for buttons, tabs and links text.', 'wp-charity' ); ?></small>
                                    <br><input type="color" id="fxm_accent_text" name="fxm_accent_text" value="<?php echo esc_attr( get_option( 'fxm_accent_text' ) ); ?>">
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e( 'CTA Colours', 'wp-charity' ); ?></label></th>
                            <td>
                                <p>
                                    <label for="fxm_cta_background"><?php _e( 'CTA Background Color:', 'wp-charity' ); ?></label>
                                    <br><small><?php _e( 'This colour is used for the main Donate button.', 'wp-charity' ); ?></small>
                                    <br><input type="color" id="fxm_cta_background" name="fxm_cta_background" value="<?php echo esc_attr( get_option( 'fxm_cta_background' ) ); ?>">
                                </p>
                                <p>
                                    <label for="fxm_cta_text"><?php _e( 'CTA Text Color:', 'wp-charity' ); ?></label>
                                    <br><small><?php _e( 'This colour is used for the main Donate button.', 'wp-charity' ); ?></small>
                                    <br><input type="color" id="fxm_cta_text" name="fxm_cta_text" value="<?php echo esc_attr( get_option( 'fxm_cta_text' ) ); ?>">
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="fxm_volunteer_campaign_status"><?php _e( 'Volunteer Campaign Status', 'wp-charity' ); ?></label></th>
                            <td>
                                <select name="fxm_volunteer_campaign_status" id="fxm_volunteer_campaign_status">
                                    <option value="draft" <?php selected( get_option( 'fxm_volunteer_campaign_status', 'draft' ), 'draft' ); ?>><?php _e( 'Draft (Requires Approval)', 'wp-charity' ); ?></option>
                                    <option value="publish" <?php selected( get_option( 'fxm_volunteer_campaign_status', 'draft' ), 'publish' ); ?>><?php _e( 'Published (Automatic Approval)', 'wp-charity' ); ?></option>
                                </select>
                                <p class="description"><?php _e( 'Choose whether volunteer campaigns require admin approval before being published.', 'wp-charity' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e( 'Notifications Email(s)', 'wp-charity' ); ?></label></th>
                            <td>
                                <p>
                                    <input type="text" name="fxm_notifications_emails" value="<?php echo get_option( 'fxm_notifications_emails' ); ?>" class="regular-text">
                                    <br><small><?php _e( 'Enter email addresses to receive notifications when new campaigns are created. Default is the admin email. Separate multiple emails with commas.', 'wp-charity' ); ?></small>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="fxm_webhook_ghl">GHL Webhook URL</label></th>
                            <td>
                                <p>
                                    <input type="url" name="fxm_webhook_ghl" value="<?php echo get_option( 'fxm_webhook_ghl' ); ?>" class="regular-text" placeholder="https://">
                                    <br><small>Enter the webhook URL for GHL integration.</small>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="fxm_approval_webhook_ghl">GHL Campaign Approval Webhook URL</label></th>
                            <td>
                                <p>
                                    <input type="url" name="fxm_approval_webhook_ghl" id="fxm_approval_webhook_ghl" value="<?php echo esc_url( get_option( 'fxm_approval_webhook_ghl', '' ) ); ?>" class="regular-text" placeholder="https://">
                                    <br><small><?php _e( 'Optional. Called when a campaign is approved (draft → published). Payload: name, email, phone, volunteer-portal-page-url.', 'wp-charity' ); ?></small>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><input type="submit" name="save_fxm_settings" class="button button-primary" value="<?php _e( 'Save Changes', 'wp-charity' ); ?>"></th>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </form>
        <?php } elseif ( $tab === 'how-to' ) {
            cm_render_how_to();
        } elseif ( $tab === 'reports' ) {
            cm_render_donations_report();
        ?>
        <?php } elseif ( $tab === 'help' ) { ?>
            <h2><span class="dashicons dashicons-editor-help"></span> <?php _e( 'Help', 'wp-charity' ); ?></h2>

            <h3><?php _e( 'Shortcodes', 'wp-charity' ); ?></h3>
            <p><?php _e( 'Use these shortcodes to display campaign elements on any page or post:', 'wp-charity' ); ?></p>

            <details>
                <summary><code>[campaign_buttons]</code> - <?php _e( 'Display campaign donation, share, and volunteer buttons.', 'wp-charity' ); ?></summary>

                <h4><?php _e( 'Optional parameters', 'wp-charity' ); ?></h4>
                <ul>
                    <li><code>campaign_id="123"</code> - <?php _e( 'Specific campaign ID (defaults to current campaign)', 'wp-charity' ); ?></li>
                    <li><code>align="left|center|right"</code> - <?php _e( 'Button alignment (defaults to left)', 'wp-charity' ); ?></li>
                </ul>
                <p><?php _e( 'Example:', 'wp-charity' ); ?> <code>[campaign_buttons align="center"]</code></p>
            </details>

            <?php cm_render_campaign_report(); ?>

            <hr>
            <p><?php _e( 'For support, feature requests and bug reporting, please visit the', 'wp-charity' ); ?> <a href="https://getbutterfly.com/" rel="external"><?php _e( 'official website', 'wp-charity' ); ?></a>. <?php _e( 'If you enjoy this plugin, don\'t forget to rate it. Also, try our other WordPress plugins at', 'wp-charity' ); ?> <a href="https://getbutterfly.com/wordpress-plugins/" rel="external" target="_blank">getButterfly.com</a>.</p>
            <p>&copy;<?php echo esc_attr( gmdate( 'Y' ) ); ?> <a href="https://getbutterfly.com/" rel="external"><strong>getButterfly</strong>.com</a> &middot; <small><?php _e( 'Code wrangling since 2005', 'wp-charity' ); ?></small></p>
        <?php } ?>
    </div>
    <?php
}
