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

    $campaign_page = max( 1, absint( $_GET['my_campaign_page'] ?? 1 ) );
    $campaign_query = new WP_Query( [ 'post_type' => 'campaign', 'author' => $current_user_id, 'post_status' => [ 'publish', 'draft' ], 'posts_per_page' => 20, 'paged' => $campaign_page, 'orderby' => [ 'date' => 'DESC', 'ID' => 'DESC' ] ] );
    $campaigns = $campaign_query->posts;

    $out = '<div class="fxm--tiny-account">
        <h1>' . __( 'My Account', 'wp-charity' ) . '</h1>';
    if ( isset( $_GET['cm_remind_sent'] ) && $_GET['cm_remind_sent'] === '1' ) {
        $out .= '<div class="fxm--notification fxm--notification-success" role="alert">' . esc_html__( 'A reminder has been sent to the administrator to review your campaign.', 'wp-charity' ) . '</div>';
    }
    $out .= '
        <ul class="whiskey-tabs">
            <li><a href="#dashboard" class="is-active"><i class="ai-dashboard ai-fw"></i> ' . __( 'Dashboard', 'wp-charity' ) . '</a></li>
            <li><a href="#my-campaigns"><i class="ai-two-line-horizontal ai-fw"></i> ' . __( 'My Campaigns', 'wp-charity' ) . '</a></li>
            <li><a href="#donations"><i class="ai-coin ai-fw"></i> ' . __( 'Donations', 'wp-charity' ) . '</a></li>
            <li><a href="#new-campaign"><i class="ai-circle-plus ai-fw"></i> ' . __( 'New Campaign', 'wp-charity' ) . '</a></li>
            <li><a href="#profile"><i class="ai-person ai-fw"></i> ' . __( 'Profile', 'wp-charity' ) . '</a></li>
        </ul>

        <section class="whiskey-tab-content" id="dashboard">
            <div class="fxm--box">
                ' . sprintf( __( 'Hi %1$s, welcome to <b>%2$s</b>!', 'wp-charity' ), esc_html( $username ), esc_html( get_bloginfo( 'name' ) ) ) . '
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

            $links = '<a href="?edit_campaign=' . $campaign->ID . '#new-campaign"><i class="ai-pencil ai-fw"></i> ' . __( 'Edit', 'wp-charity' ) . '</a>
                     | <a href="' . get_permalink( $campaign->ID ) . '"><i class="ai-link-chain ai-fw"></i> ' . __( 'View Campaign', 'wp-charity' ) . '</a>
                     | <a href="#" onclick="share(event, \'' . get_permalink( $campaign->ID ) . '\')"><i class="ai-network ai-fw"></i> ' . __( 'Share Campaign', 'wp-charity' ) . '</a>';
            if ( $campaign->post_status === 'draft' ) {
                $remind_url = add_query_arg( 'cm_remind_campaign', $campaign->ID, get_permalink( (int) get_option( 'fxm_members_account_page_id' ) ) );
                $remind_url = wp_nonce_url( $remind_url, 'cm_remind_campaign_' . $campaign->ID );
                $links     .= ' | <a href="' . esc_url( $remind_url ) . '"><i class="ai-bell ai-fw"></i> ' . __( 'Remind admin to review', 'wp-charity' ) . '</a>';
            }

            $out .= '<li>' .
                esc_html( $campaign->post_title ) . ' (' . $campaign_status . ') ' .
                '<br>
                <small>' . $links . '</small>
            </li>';
        }
        $out .= '</ul>' . cm_render_page_links( $campaign_page, $campaign_page < $campaign_query->max_num_pages, 'my_campaign_page', '#my-campaigns' );
    } else {
        $out .= '<p>' . __( 'You have not created any campaigns yet.', 'wp-charity' ) . '</p>';
    }
            //

        $out .= '</section>
        <section class="whiskey-tab-content" id="donations">';

    $out .= '<h2>' . esc_html__( 'Donations to your campaigns', 'wp-charity' ) . '</h2><div class="cm-remote-list" data-action="cm_account_donations" data-nonce="' . esc_attr( wp_create_nonce( 'cm_report_data' ) ) . '"></div>';

        $out .= '</section>
        <section class="whiskey-tab-content" id="new-campaign">';

            include_once 'campaigns.php';
            $out .= fxm_create_campaign();

        $out .= '</section>
        <section class="whiskey-tab-content" id="profile">';

    if ( isset( $GLOBALS['cm_profile_notice'] ) ) {
        $notice = $GLOBALS['cm_profile_notice'];
        $out .= '<p role="status" class="cm-' . esc_attr( $notice[0] ) . '">' . esc_html( $notice[1] ) . '</p>';
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
                            <input type="text" name="first_name" value="' . esc_attr( $user_data->first_name ) . '" placeholder="' . __( 'First Name', 'wp-charity' ) . '" required>
                        </p>
                        <p>
                            <label>' . __( 'Last Name', 'wp-charity' ) . '</label>
                            <input type="text" name="last_name" value="' . esc_attr( $user_data->last_name ) . '" placeholder="' . __( 'Last Name', 'wp-charity' ) . '" required>
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


    </div>';

    return $out;
}
