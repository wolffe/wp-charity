<?php
function fxm_members_menu_links() {
    // Add menu item as a child for the "campaign" CPT
    add_submenu_page( 'edit.php?post_type=campaign', __( 'WP Charity Settings', 'wp-charity' ), __( 'Settings', 'wp-charity' ), 'manage_options', 'fxm', 'fxm_build_admin_page' );
}

add_action( 'admin_menu', 'fxm_members_menu_links', 10 );

function fxm_build_admin_page() {
    $tab     = ( filter_has_var( INPUT_GET, 'tab' ) ) ? filter_input( INPUT_GET, 'tab' ) : 'dashboard';
    $section = 'edit.php?post_type=campaign&page=fxm&amp;tab=';
    ?>
    <div class="wrap">
        <h1><?php _e( 'WP Charity Settings', 'wp-charity' ); ?></h1>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_attr( $section ); ?>dashboard" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Dashboard', 'wp-charity' ); ?></a>
            <a href="<?php echo esc_attr( $section ); ?>help" class="nav-tab <?php echo $tab === 'help' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Help', 'wp-charity' ); ?></a>
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
                update_option( 'fxm_notifications_emails', sanitize_text_field( wp_unslash( $_POST['fxm_notifications_emails'] ?? '' ) ) );
                update_option( 'fxm_volunteer_campaign_status', sanitize_text_field( wp_unslash( $_POST['fxm_volunteer_campaign_status'] ?? 'draft' ) ) );
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
                            <th scope="row"><label><?php _e( 'Colours', 'wp-charity' ); ?></label></th>
                            <td>
                                <p>
                                    <label for="fxm_accent_background"><?php _e( 'Background Accent Color:', 'wp-charity' ); ?></label><br>
                                    <input type="color" id="fxm_accent_background" name="fxm_accent_background" value="<?php echo esc_attr( get_option( 'fxm_accent_background' ) ); ?>">
                                    <br><small><?php _e( 'This colour is used for buttons, tabs and links background.', 'wp-charity' ); ?></small>
                                </p>
                                <p>
                                    <label for="fxm_accent_text"><?php _e( 'Text Accent Color:', 'wp-charity' ); ?></label><br>
                                    <input type="color" id="fxm_accent_text" name="fxm_accent_text" value="<?php echo esc_attr( get_option( 'fxm_accent_text' ) ); ?>">
                                    <br><small><?php _e( 'This colour is used for buttons, tabs and links text.', 'wp-charity' ); ?></small>
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
                            <th scope="row"><input type="submit" name="save_fxm_settings" class="button button-primary" value="<?php _e( 'Save Changes', 'wp-charity' ); ?>"></th>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </form>
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

            <h3><?php _e( 'Campaign Report', 'wp-charity' ); ?></h3>
            <div class="campaign-report">
                <?php
                // Get all campaigns
                $campaigns = get_posts(
                    [
                        'post_type'      => 'campaign',
                        'posts_per_page' => -1,
                        'post_status'    => 'any',
                    ]
                );

                if ( ! empty( $campaigns ) ) {
                    echo '<table class="widefat fixed" cellspacing="0">
                        <thead>
                            <tr>
                                <th>' . __( 'Campaign', 'wp-charity' ) . '</th>
                                <th>' . __( 'Status', 'wp-charity' ) . '</th>
                                <th>' . __( 'Goal', 'wp-charity' ) . '</th>
                                <th>' . __( 'Total Raised', 'wp-charity' ) . '</th>
                                <th>' . __( 'Orders', 'wp-charity' ) . '</th>
                            </tr>
                        </thead>
                        <tbody>';

                    foreach ( $campaigns as $campaign ) {
                        $donation_goal = get_post_meta( $campaign->ID, '_donation_goal', true );
                        $total_raised  = 0;
                        $orders_html   = '';

                        // Get orders for this campaign
                        $orders = wc_get_orders(
                            [
                                'limit'  => -1,
                                'status' => [ 'completed', 'processing', 'on-hold' ],
                                'type'   => 'shop_order',
                            ]
                        );

                        foreach ( $orders as $order ) {
                            foreach ( $order->get_items() as $item ) {
                                $item_campaign_id = $item->get_meta( '_campaign_id' );
                                if ( $item_campaign_id == $campaign->ID ) {
                                    $total_raised += $order->get_total();
                                    $orders_html  .= sprintf(
                                        '<a href="%s" target="_blank">' . __( 'Order #%s', 'wp-charity' ) . '</a> - %s<br>',
                                        esc_url( get_edit_post_link( $order->get_id() ) ),
                                        esc_html( $order->get_order_number() ),
                                        wc_price( $order->get_total() )
                                    );
                                }
                            }
                        }

                        // Calculate progress percentage
                        $progress = $donation_goal > 0 ? min( 100, ( $total_raised / $donation_goal ) * 100 ) : 0;

                        echo '<tr>
                            <td>
                                <strong><a href="' . get_edit_post_link( $campaign->ID ) . '">' . esc_html( $campaign->post_title ) . '</a></strong>
                                ' . ( $campaign->post_parent ? '<br><small>' . sprintf( __( 'Child of: %s', 'wp-charity' ), get_the_title( $campaign->post_parent ) ) . '</small>' : '' ) . '
                            </td>
                            <td>' . esc_html( get_post_status_object( $campaign->post_status )->label ) . '</td>
                            <td>' . ( $donation_goal ? wc_price( $donation_goal ) : __( 'No goal set', 'wp-charity' ) ) . '</td>
                            <td>
                                ' . wc_price( $total_raised ) . '
                                ' . ( $donation_goal > 0 ? '<br><small>' . sprintf( __( '%s%% of goal', 'wp-charity' ), round( $progress ) ) . '</small>' : '' ) . '
                            </td>
                            <td>' . ( $orders_html ? $orders_html : __( 'No orders', 'wp-charity' ) ) . '</td>
                        </tr>';
                    }

                    echo '</tbody></table>';
                } else {
                    echo '<p>' . __( 'No campaigns found.', 'wp-charity' ) . '</p>';
                }
                ?>
            </div>

            <style>
                .campaign-report {
                    margin-top: 20px;
                }
                .campaign-report table {
                    margin-top: 10px;
                }
                .campaign-report th,
                .campaign-report td {
                    padding: 12px;
                }
                .campaign-report td small {
                    color: #666;
                }
            </style>

            <hr>
            <p><?php _e( 'For support, feature requests and bug reporting, please visit the', 'wp-charity' ); ?> <a href="https://getbutterfly.com/" rel="external"><?php _e( 'official website', 'wp-charity' ); ?></a>. <?php _e( 'If you enjoy this plugin, don\'t forget to rate it. Also, try our other WordPress plugins at', 'wp-charity' ); ?> <a href="https://getbutterfly.com/wordpress-plugins/" rel="external" target="_blank">getButterfly.com</a>.</p>
            <p>&copy;<?php echo esc_attr( gmdate( 'Y' ) ); ?> <a href="https://getbutterfly.com/" rel="external"><strong>getButterfly</strong>.com</a> &middot; <small><?php _e( 'Code wrangling since 2005', 'wp-charity' ); ?></small></p>
        <?php } ?>
    </div>
    <?php
}
