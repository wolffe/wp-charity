<?php
function fxm_members_menu_links() {
    // Add menu item as a child for the "campaign" CPT
    add_submenu_page( 'edit.php?post_type=campaign', __( 'WP Charity Settings', 'wp-charity' ), __( 'Settings', 'wp-charity' ), 'manage_options', 'fxm', 'fxm_build_admin_page' );
}

add_action( 'admin_menu', 'fxm_members_menu_links', 10 );

// Handle CSV export before any output
function fxm_handle_csv_export() {
    // Only process on our settings page
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fxm' ) {
        return;
    }

    // Handle CSV export
    if ( isset( $_GET['export_campaign_orders'] ) && isset( $_GET['campaign_id'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'export_campaign_orders_' . (int) $_GET['campaign_id'] ) ) {
        $campaign_id = (int) $_GET['campaign_id'];
        cm_export_campaign_orders_csv( $campaign_id );
        exit;
    }
}

add_action( 'admin_init', 'fxm_handle_csv_export', 1 );

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
                update_option( 'fxm_cta_background', sanitize_text_field( wp_unslash( $_POST['fxm_cta_background'] ?? '' ) ) );
                update_option( 'fxm_cta_text', sanitize_text_field( wp_unslash( $_POST['fxm_cta_text'] ?? '' ) ) );

                update_option( 'fxm_notifications_emails', sanitize_text_field( wp_unslash( $_POST['fxm_notifications_emails'] ?? '' ) ) );
                update_option( 'fxm_volunteer_campaign_status', sanitize_text_field( wp_unslash( $_POST['fxm_volunteer_campaign_status'] ?? 'draft' ) ) );

                update_option( 'fxm_webhook_ghl', sanitize_url( $_POST['fxm_webhook_ghl'] ) );

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
                        $has_orders    = false;

                        // Use the same function as frontend for consistency
                        if ( function_exists( 'cm_get_campaign_stats' ) ) {
                            $stats        = cm_get_campaign_stats( $campaign->ID );
                            $total_raised = $stats['total_raised'] ?? 0;
                            $orders        = $stats['orders'] ?? [];
                            $has_orders    = ! empty( $orders );

                            foreach ( $orders as $order_data ) {
                                $order = wc_get_order( $order_data['order_id'] );
                                if ( $order ) {
                                    $orders_html .= sprintf(
                                        '<a href="%s" target="_blank">' . __( 'Order #%s', 'wp-charity' ) . '</a> - %s<br>',
                                        esc_url( get_edit_post_link( $order->get_id() ) ),
                                        esc_html( $order->get_order_number() ),
                                        wc_price( $order_data['total'] )
                                    );
                                }
                            }
                        } else {
                            // Fallback to direct query if function doesn't exist
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
                                    if ( (int) $item_campaign_id === (int) $campaign->ID ) {
                                        $has_orders    = true;
                                        $total_raised += (float) $order->get_total();
                                        $orders_html  .= sprintf(
                                            '<a href="%s" target="_blank">' . __( 'Order #%s', 'wp-charity' ) . '</a> - %s<br>',
                                            esc_url( get_edit_post_link( $order->get_id() ) ),
                                            esc_html( $order->get_order_number() ),
                                            wc_price( $order->get_total() )
                                        );
                                        break; // no need to check more items in this order
                                    }
                                }
                            }
                        }

                        // Calculate progress percentage
                        $progress = $donation_goal > 0 ? min( 100, ( $total_raised / $donation_goal ) * 100 ) : 0;

                        $export_url = wp_nonce_url(
                            add_query_arg(
                                [
                                    'export_campaign_orders' => '1',
                                    'campaign_id'            => $campaign->ID,
                                ],
                                admin_url( 'edit.php?post_type=campaign&page=fxm&tab=dashboard' )
                            ),
                            'export_campaign_orders_' . $campaign->ID
                        );

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
                            <td>
                                ' . ( $orders_html ? $orders_html : __( 'No orders', 'wp-charity' ) ) . '
                                ' . ( $has_orders ? '<br><a href="' . esc_url( $export_url ) . '" class="button button-small" style="margin-top:5px;"><span class="dashicons dashicons-download" style="font-size:16px;vertical-align:middle;"></span> ' . __( 'Export CSV', 'wp-charity' ) . '</a>' : '' ) . '
                            </td>
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

/**
 * Export campaign orders to CSV
 *
 * @param int $campaign_id Campaign ID to export orders for
 */
function cm_export_campaign_orders_csv( $campaign_id ) {
    $campaign = get_post( $campaign_id );
    if ( ! $campaign || $campaign->post_type !== 'campaign' ) {
        wp_die( __( 'Invalid campaign ID.', 'wp-charity' ) );
    }

    // Get all orders for this campaign (not just cached ones)
    $orders = wc_get_orders(
        [
            'limit'  => -1,
            'status' => [ 'completed', 'processing', 'on-hold' ],
            'type'   => 'shop_order',
        ]
    );

    $campaign_orders = [];
    foreach ( $orders as $order ) {
        foreach ( $order->get_items() as $item ) {
            $item_campaign_id = $item->get_meta( '_campaign_id' );
            if ( (int) $item_campaign_id === (int) $campaign_id ) {
                $billing_name  = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $billing_email = $order->get_billing_email();
                $order_date    = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ) : '';
                $order_total   = $order->get_total();
                $campaign_name = $campaign->post_title;
                $campaign_url  = get_permalink( $campaign_id );

                $campaign_orders[] = [
                    'order_id'      => $order->get_id(),
                    'name'          => trim( $billing_name ),
                    'email'         => $billing_email,
                    'amount'        => $order_total,
                    'date'          => $order_date,
                    'campaign_id'   => $campaign_id,
                    'campaign_name' => $campaign_name,
                    'campaign_url'  => $campaign_url,
                ];
                break; // no need to check more items in this order
            }
        }
    }

    // Sort by date, newest first
    usort(
        $campaign_orders,
        function ( $a, $b ) {
            return strtotime( $b['date'] ) <=> strtotime( $a['date'] );
        }
    );

    // Set headers for CSV download
    $filename = 'campaign-orders-' . sanitize_file_name( $campaign_name ) . '-' . date( 'Y-m-d' ) . '.csv';
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    // Output UTF-8 BOM for Excel compatibility
    echo "\xEF\xBB\xBF";

    // Open output stream
    $output = fopen( 'php://output', 'w' );

    // Add CSV headers
    fputcsv(
        $output,
        [
            __( 'Order ID', 'wp-charity' ),
            __( 'Name', 'wp-charity' ),
            __( 'Email', 'wp-charity' ),
            __( 'Amount', 'wp-charity' ),
            __( 'Date of Donation', 'wp-charity' ),
            __( 'Campaign ID', 'wp-charity' ),
            __( 'Campaign Name', 'wp-charity' ),
            __( 'Campaign URL', 'wp-charity' ),
        ]
    );

    // Add order data
    foreach ( $campaign_orders as $order_data ) {
        fputcsv(
            $output,
            [
                $order_data['order_id'],
                $order_data['name'],
                $order_data['email'],
                $order_data['amount'],
                $order_data['date'],
                $order_data['campaign_id'],
                $order_data['campaign_name'],
                $order_data['campaign_url'],
            ]
        );
    }

    fclose( $output );
    exit;
}
