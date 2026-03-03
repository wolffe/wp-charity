<?php
function fxm_account_page() {
    $user_data       = get_userdata( get_current_user_id() );
    $user_email      = esc_attr( get_the_author_meta( 'user_email', $user_data->ID ) );
    $current_user_id = get_current_user_id();

    $username = '';

    if ( (string) $user_data->first_name !== '' && (string) $user_data->last_name !== '' ) {
        $username = $user_data->first_name . ' ' . $user_data->last_name;
    } elseif ( (string) $user_data->first_name !== '' ) {
        $username = $user_data->first_name;
    } elseif ( (string) $username === '' ) {
        $username = $user_data->display_name;
    }

    $campaigns = get_posts(
        [
            'post_type'   => 'campaign',
            'author'      => $current_user_id,
            'post_status' => [ 'publish', 'draft' ],
            'numberposts' => -1,
        ]
    );

    $out = '<div class="fxm--tiny-account">
        <h1>' . __( 'My Account', 'wp-charity' ) . '</h1>';
    if ( isset( $_GET['cm_remind_sent'] ) && $_GET['cm_remind_sent'] === '1' ) {
        $out .= '<div class="fxm--notification fxm--notification-success" role="alert">' . esc_html__( 'A reminder has been sent to the administrator to review your campaign.', 'wp-charity' ) . '</div>';
    }
    $out .= '
        <ul class="whiskey-tabs">
            <li><a href="#dashboard" class="is-active"><i class="ai-dashboard"></i> ' . __( 'Dashboard', 'wp-charity' ) . '</a></li>
            <li><a href="#my-campaigns"><i class="ai-two-line-horizontal"></i> ' . __( 'My Campaigns', 'wp-charity' ) . '</a></li>
            <li><a href="#donations"><i class="ai-coin"></i> ' . __( 'Donations', 'wp-charity' ) . '</a></li>
            <li><a href="#new-campaign"><i class="ai-circle-plus"></i> ' . __( 'New Campaign', 'wp-charity' ) . '</a></li>
            <li><a href="#profile"><i class="ai-person"></i> ' . __( 'Profile', 'wp-charity' ) . '</a></li>
        </ul>

        <section class="whiskey-tab-content" id="dashboard">
            <div class="fxm--box">
                ' . sprintf( __( 'Hi %1$s, welcome to <b>%2$s</b>!', 'wp-charity' ), $username, get_bloginfo( 'name' ) ) . '
                <br><small>' . sprintf( __( 'Here you can manage your campaigns, profile, and account settings. <a href="%s">Logout</a>.', 'wp-charity' ), wp_logout_url( home_url() ) ) . '</small>
            </div>
        </section>
        <section class="whiskey-tab-content" id="my-campaigns">';

            /**
             * Display list of campaigns created by the volunteer
             */
    if ( $campaigns ) {
        $out .= '<h2>' . __( 'My Campaigns', 'wp-charity' ) . '</h2>';
        $out .= '<ul class="fxm--campaign-list">';
        foreach ( $campaigns as $campaign ) {
            $campaign_status = $campaign->post_status === 'publish' ? __( 'Published', 'wp-charity' ) : __( 'Draft', 'wp-charity' );

            $links = '<a href="?edit_campaign=' . $campaign->ID . '#new-campaign"><i class="ai-pencil"></i> ' . __( 'Edit', 'wp-charity' ) . '</a>
                     | <a href="' . get_permalink( $campaign->ID ) . '"><i class="ai-link-chain"></i> ' . __( 'View Campaign', 'wp-charity' ) . '</a>
                     | <a href="#" onclick="share(event, \'' . get_permalink( $campaign->ID ) . '\')"><i class="ai-network"></i> ' . __( 'Share Campaign', 'wp-charity' ) . '</a>';
            if ( $campaign->post_status === 'draft' ) {
                $remind_url = add_query_arg( 'cm_remind_campaign', $campaign->ID, get_permalink( (int) get_option( 'fxm_members_account_page_id' ) ) );
                $remind_url = wp_nonce_url( $remind_url, 'cm_remind_campaign_' . $campaign->ID );
                $links     .= ' | <a href="' . esc_url( $remind_url ) . '"><i class="ai-bell"></i> ' . __( 'Remind admin to review', 'wp-charity' ) . '</a>';
            }

            $out .= '<li>' .
                esc_html( $campaign->post_title ) . ' (' . $campaign_status . ') ' .
                '<br>
                <small>' . $links . '</small>
            </li>';
        }
        $out .= '</ul>';
    } else {
        $out .= '<p>' . __( 'You have not created any campaigns yet.', 'wp-charity' ) . '</p>';
    }
            //

        $out .= '</section>
        <section class="whiskey-tab-content" id="donations">';

    /**
     * Donations to the user's campaigns: donor name, email, amount.
     */
    $my_campaign_ids = $campaigns ? wp_list_pluck( $campaigns, 'ID' ) : [];
    $donations_rows  = [];
    if ( ! empty( $my_campaign_ids ) && function_exists( 'wc_get_orders' ) ) {
        $orders = wc_get_orders(
            [
                'limit'  => -1,
                'status' => [ 'completed', 'processing', 'on-hold' ],
                'type'   => 'shop_order',
            ]
        );
        foreach ( $orders as $order ) {
            $donor_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
            $donor_email = $order->get_billing_email();
            foreach ( $order->get_items() as $item ) {
                $campaign_id = (int) $item->get_meta( '_campaign_id' );
                if ( ! $campaign_id || ! in_array( $campaign_id, $my_campaign_ids, true ) ) {
                    continue;
                }
                $amount = $item->get_total();
                if ( is_string( $amount ) ) {
                    $amount = (float) $amount;
                }
                $donations_rows[] = [
                    'donor_name'  => $donor_name !== '' ? $donor_name : __( 'Guest', 'wp-charity' ),
                    'donor_email' => $donor_email,
                    'amount'      => $amount,
                    'date'        => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '',
                    'campaign_id' => $campaign_id,
                ];
            }
        }
        usort( $donations_rows, function ( $a, $b ) {
            return strcmp( $b['date'], $a['date'] );
        } );
    }

    $out .= '<h2>' . __( 'Donations to your campaigns', 'wp-charity' ) . '</h2>';
    if ( ! empty( $donations_rows ) ) {
        $out .= '<div class="fxm--donations-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">';
        foreach ( $donations_rows as $row ) {
            $campaign = get_post( $row['campaign_id'] );
            $campaign_name = $campaign && $campaign->post_type === 'campaign' ? $campaign->post_title : __( '(Unknown campaign)', 'wp-charity' );
            $campaign_url  = get_permalink( $row['campaign_id'] );
            $out .= '<div class="fxm--donation-box fxm--box">';
            $out .= '<p style="margin: 0 0 0.5em 0;"><strong>' . wp_kses_post( wc_price( $row['amount'] ) ) . '</strong> <small>' . esc_html( $row['date'] ) . '</small></p>';
            $out .= '<p style="margin: 0 0 0.5em 0;">' . esc_html( $row['donor_name'] ) . ' · ' . esc_html( $row['donor_email'] ) . '</p>';
            $out .= '<p style="margin: 0;"><small><a href="' . esc_url( $campaign_url ) . '">' . esc_html( $campaign_name ) . '</a></small></p>';
            $out .= '</div>';
        }
        $out .= '</div>';
    } else {
        $out .= '<p>' . __( 'No donations to your campaigns yet.', 'wp-charity' ) . '</p>';
    }

        $out .= '</section>
        <section class="whiskey-tab-content" id="new-campaign">';

            include_once 'campaigns.php';
            $out .= fxm_create_campaign();

        $out .= '</section>
        <section class="whiskey-tab-content" id="profile">';

    if ( isset( $_POST['action'] ) && $_POST['action'] === 'wppd_update_user_profile' ) {
        $user_id = (int) $_POST['user_id'];

        if ( ! wp_verify_nonce( $_POST['nonce'], 'wppd_update_user_profile' ) ) {
            $out .= '<div class="notice notice-error"><p>' . __( 'Sorry, your nonce did not verify.', 'wp-charity' ) . '</p></div>';
        } else {
            $first_name = sanitize_text_field( $_POST['first_name'] );
            $last_name  = sanitize_text_field( $_POST['last_name'] );
            $user_email = sanitize_email( $_POST['user_email'] );
            $bio        = sanitize_textarea_field( $_POST['bio'] );

            update_user_meta( $user_id, 'first_name', $first_name );
            update_user_meta( $user_id, 'last_name', $last_name );
            update_user_meta( $user_id, 'user_email', $user_email );
            update_user_meta( $user_id, 'description', $bio );

            // Handle avatar upload
            if ( ! empty( $_FILES['avatar']['name'] ) ) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';

                $attachment_id = media_handle_upload( 'avatar', 0 );
                if ( ! is_wp_error( $attachment_id ) ) {
                    update_user_meta( $user_id, 'avatar', $attachment_id );
                }
            }

            // Update password
            if ( (string) $_POST['user_pass'] !== '' && (string) $_POST['user_pass_confirm'] !== '' ) {
                if ( $_POST['user_pass'] === $_POST['user_pass_confirm'] ) {
                    wp_set_password( $_POST['user_pass'], $user_id );
                } else {
                    $out .= '<p style="background-color: #f1f2f6; padding: 16px; border-radius: 8px;">' . __( 'Your passwords do not match.', 'wp-charity' ) . '</p>';
                }
            }

            $out .= '<p style="background-color: #f1f2f6; padding: 16px; border-radius: 8px;">' . __( 'Your profile has been updated.', 'wp-charity' ) . '</p>';
        }
    }

            $out                       .= '<h2>' . __( 'Profile', 'wp-charity' ) . '</h2>
            <p>' . __( 'Update your profile details, including your name, email address, and password.', 'wp-charity' ) . '</p>

            <form method="post" action="' . get_permalink( (int) get_option( 'fxm_members_account_page_id' ) ) . '#profile" class="fxm-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="wppd_update_user_profile">
                <input type="hidden" name="user_id" value="' . $user_data->ID . '">
                <input type="hidden" name="redirect" value="' . get_permalink( (int) get_option( 'fxm_members_account_page_id' ) ) . '#profile">
                <input type="hidden" name="nonce" value="' . wp_create_nonce( 'wppd_update_user_profile' ) . '">

                <div class="fxm--grid-container" style="--columns:2">
                    <div>
                        <h3>' . __( 'Profile Settings', 'wp-charity' ) . '</h3>

                        <p>
                            <label>' . __( 'First Name', 'wp-charity' ) . '</label>
                            <input type="text" name="first_name" value="' . $user_data->first_name . '" placeholder="' . __( 'First Name', 'wp-charity' ) . '" required>
                        </p>
                        <p>
                            <label>' . __( 'Last Name', 'wp-charity' ) . '</label>
                            <input type="text" name="last_name" value="' . $user_data->last_name . '" placeholder="' . __( 'Last Name', 'wp-charity' ) . '" required>
                        </p>
                        <p>
                            <label>' . __( 'Email Address', 'wp-charity' ) . '</label>
                            <input type="email" name="user_email" value="' . $user_email . '" placeholder="' . __( 'Email Address', 'wp-charity' ) . '" required>
                        </p>
                        <p>
                            <label>' . __( 'Biography', 'wp-charity' ) . '</label>
                            <textarea name="bio" rows="5" placeholder="' . __( 'Tell us about yourself', 'wp-charity' ) . '">' . esc_textarea( get_user_meta( $user_data->ID, 'description', true ) ) . '</textarea>
                            <br><small>' . __( 'A few words about yourself. This will be displayed on your campaign page.', 'wp-charity' ) . '</small>
                        </p>
                    </div>
                    <div>
                        <h3>' . __( 'Account Settings', 'wp-charity' ) . '</h3>

                        <p>
                            <label>' . __( 'New Password', 'wp-charity' ) . '</label>
                            <input type="password" name="user_pass" placeholder="' . __( 'New Password', 'wp-charity' ) . '">
                        </p>
                        <p>
                            <label>' . __( 'Confirm New Password', 'wp-charity' ) . '</label>
                            <input type="password" name="user_pass_confirm" placeholder="' . __( 'Confirm New Password', 'wp-charity' ) . '">
                        </p>
                        <p>
                            <label>' . __( 'Profile Picture', 'wp-charity' ) . '</label>
                            <div class="avatar-preview">';
                            $avatar     = get_user_meta( $user_data->ID, 'avatar', true );
                            $avatar_url = $avatar ? wp_get_attachment_url( $avatar ) : get_avatar_url( $user_data->ID, [ 'size' => 150 ] );
                            $out       .= '<img src="' . esc_url( $avatar_url ) . '" alt="' . __( 'Profile Picture', 'wp-charity' ) . '" style="max-width: 128px; height: auto; border-radius: 50%; margin-bottom: 10px;">';
                            $out       .= '</div>
                            <input type="file" name="avatar" accept="image/*">
                            <small style="display: block; margin-top: 5px; color: #666;">' . __( 'Upload a new profile picture. Recommended size: 512x512 pixels (square aspect ratio).', 'wp-charity' ) . '</small>
                        </p>
                    </div>
                </div>

                <p>
                    <input type="submit" name="update_settings" value="' . __( 'Update', 'wp-charity' ) . '" class="button button-primary" style="font-size: 16px; padding: 16px 24px;">
                </p>
            </form>
        </section>

        <script>
        var tabLinks = document.querySelectorAll(".whiskey-tabs li a");

        for (var i = 0; i < tabLinks.length; i++) {
            tabLinks[i].onclick = function() {
                var target = this.getAttribute("href").replace("#", "");
                var sections = document.querySelectorAll(".whiskey-tab-content");

                for(var j=0; j < sections.length; j++) {
                    sections[j].style.display = "none";
                }

                document.getElementById(target).style.display = "block";
                
                for(var k=0; k < tabLinks.length; k++) {
                    tabLinks[k].removeAttribute("class");
                }
                
                this.setAttribute("class", "is-active");

                // Change the URL hash based on the selected tab
                history.pushState(null, null, "#" + target);

                return false;
            }
        };

        // Enable link to tab
        var hash = document.location.hash;
        if (hash && document.querySelector(`.whiskey-tabs li a[href="${hash}"]`)) {
            document.querySelectorAll(`.whiskey-tabs li a[href="${hash}"]`)[0].click();
        }
        </script>
    </div>';

    return $out;
}
