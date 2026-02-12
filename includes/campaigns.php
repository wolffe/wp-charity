<?php
function fxm_create_campaign() {
    if ( is_user_logged_in() ) {
        $out             = ''; // Initialize the output variable
        $current_user_id = get_current_user_id();

        // Handle form submission for new or edited campaign
        if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset( $_POST['submit_campaign'] ) ) {
            // Verify nonce
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'create_campaign' ) ) {
                wp_die( 'Invalid nonce' );
            }

            $title              = sanitize_text_field( $_POST['campaign_title'] );
            $short_description  = sanitize_textarea_field( $_POST['short_description'] );
            $long_description   = wp_kses_post( $_POST['long_description'] );
            $donation_goal      = floatval( $_POST['donation_goal'] );
            $start_date         = sanitize_text_field( $_POST['start_date'] );
            $end_date           = sanitize_text_field( $_POST['end_date'] );
            $status             = isset( $_POST['campaign_status'] ) ? sanitize_text_field( $_POST['campaign_status'] ) : 'draft';
            $parent_campaign_id = isset( $_POST['parent_campaign'] ) ? intval( $_POST['parent_campaign'] ) : 0;

            // Check if this is an edit or a new campaign
            $campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;

            $post_data = [
                'post_title'   => $title,
                'post_content' => $long_description,
                'post_excerpt' => $short_description,
                'post_status'  => $parent_campaign_id ? get_option( 'fxm_volunteer_campaign_status', 'draft' ) : 'publish', // Use setting for volunteer campaigns
                'post_type'    => 'campaign',
                'post_parent'  => $parent_campaign_id, // Set the parent campaign
                'post_author'  => get_current_user_id(),
            ];

            if ( $campaign_id > 0 ) {
                // Update existing campaign
                $post_data['ID'] = $campaign_id;
                $campaign_id     = wp_update_post( $post_data );
            } else {
                // Insert new campaign
                $campaign_id = wp_insert_post( $post_data );
            }

            if ( $campaign_id && ! is_wp_error( $campaign_id ) ) {
                // Save meta fields
                update_post_meta( $campaign_id, '_donation_goal', $donation_goal );
                update_post_meta( $campaign_id, '_start_date', $start_date );
                update_post_meta( $campaign_id, '_end_date', $end_date );
                update_post_meta( $campaign_id, '_youtube_url', esc_url_raw( $_POST['youtube_url'] ) );

                // Handle image upload
                if ( ! empty( $_FILES['campaign_image']['name'] ) ) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';

                    $attachment_id = media_handle_upload( 'campaign_image', $campaign_id );
                    if ( ! is_wp_error( $attachment_id ) ) {
                        set_post_thumbnail( $campaign_id, $attachment_id );
                    }
                }

                $saved_campaign = get_post( $campaign_id );
                if ( $saved_campaign && $saved_campaign->post_status === 'draft' ) {
                    $out .= '<div class="fxm--notification fxm--notification-success">' . __( 'Your campaign has been submitted and is pending approval. We\'ll notify you once it\'s reviewed.', 'wp-charity' ) . '</div>';
                } else {
                    $out .= '<div class="fxm--notification fxm--notification-success">' . sprintf( __( 'Campaign %s successfully!', 'wp-charity' ), ( $campaign_id ? __( 'updated', 'wp-charity' ) : __( 'submitted', 'wp-charity' ) ) ) . '</div>';
                }

                return $out;
            } else {
                $out .= '<div class="fxm--notification fxm--notification-error">' . sprintf( __( 'There was an error %s the campaign.', 'wp-charity' ), ( $campaign_id ? __( 'updating', 'wp-charity' ) : __( 'submitting', 'wp-charity' ) ) ) . '</div>';
            }
        }

        // Handle edit request
        $edit_campaign_id = isset( $_GET['edit_campaign'] ) ? intval( $_GET['edit_campaign'] ) : 0;
        $edit_campaign    = $edit_campaign_id ? get_post( $edit_campaign_id ) : null;

        // Get parent campaigns with _allow_volunteer_campaigns = 1
        $parent_campaigns = get_posts(
            [
                'post_type'   => 'campaign',
                'meta_key'    => '_allow_volunteer_campaigns',
                'meta_value'  => '1',
                'numberposts' => -1,
            ]
        );

        // Display the form for new or edit campaign
        $out .= '<h2>' . __( 'Add New Campaign', 'wp-charity' ) . '</h2>

        <form method="post" enctype="multipart/form-data" class="fxm-form">
            ' . wp_nonce_field( 'create_campaign', '_wpnonce', true, false ) . '
            <input type="hidden" name="campaign_id" value="' . ( $edit_campaign ? $edit_campaign->ID : '' ) . '">
            <input type="hidden" id="get_parent_goal_nonce" value="' . wp_create_nonce( 'get_parent_goal' ) . '">

            <div class="fxm--grid-container" style="--columns:2">
                <div>
                    <p>
                        <label for="parent_campaign">' . __( 'Choose the campaign you would like to raise money for', 'wp-charity' ) . '</label>
                        <select name="parent_campaign" id="parent_campaign" required>
                            <option value="0">' . __( 'Select a Campaign...', 'wp-charity' ) . '</option>';

        foreach ( $parent_campaigns as $parent ) {
            $out .= '<option value="' . $parent->ID . '" ' . ( $edit_campaign && $edit_campaign->post_parent == $parent->ID ? 'selected' : '' ) . '>' . esc_html( $parent->post_title ) . '</option>';
        }

                        $out .= '</select>
                    </p>
                    <p>
                        <label for="campaign_title">' . __( 'Campaign Title', 'wp-charity' ) . '</label>
                        <small>' . __( 'This is the title that will be displayed to the public. Keep it short and concise.', 'wp-charity' ) . '</small>
                        <input type="text" name="campaign_title" id="campaign_title" value="' . ( $edit_campaign ? esc_attr( $edit_campaign->post_title ) : '' ) . '" required>
                    </p>
                    <p>
                        <label for="short_description">' . __( 'Summary', 'wp-charity' ) . '</label>
                        <small>' . __( 'A short summary of the campaign displayed on the campaign page and in Google Search.', 'wp-charity' ) . '</small>
                        <textarea name="short_description" id="short_description" rows="6" required>' . ( $edit_campaign ? esc_textarea( $edit_campaign->post_excerpt ) : '' ) . '</textarea>
                    </p>
                    <p>
                        <label for="long_description">' . __( 'Description (why are you raising money?)', 'wp-charity' ) . '</label>
                        <small>' . __( 'Use this space to tell your story. Why are you raising money? What will the funds be used for? Why should your friends and family donate?', 'wp-charity' ) . '</small>
                        <textarea name="long_description" id="long_description" rows="12" required>' . ( $edit_campaign ? esc_textarea( $edit_campaign->post_content ) : '' ) . '</textarea>
                    </p>
                </div>
                <div>
                    <p>
                        <label for="donation_goal">' . sprintf( __( 'Fundraising Goal (%s)', 'wp-charity' ), get_woocommerce_currency_symbol() ) . '</label>
                        <input type="number" name="donation_goal" id="donation_goal" step="10" value="' . ( $edit_campaign ? esc_attr( get_post_meta( $edit_campaign->ID, '_donation_goal', true ) ) : '' ) . '" required>
                        <br><small>' . __( 'Enter your fundraising goal. If you selected a parent campaign, this will be pre-filled with their goal.', 'wp-charity' ) . '</small>
                    </p>
                    <div class="fxm--grid-container" style="--columns:2">
                        <p>
                            <label for="start_date">' . __( 'Start Date (optional)', 'wp-charity' ) . '</label>
                            <input type="date" name="start_date" id="start_date" value="' . ( $edit_campaign ? esc_attr( get_post_meta( $edit_campaign->ID, '_start_date', true ) ) : '' ) . '">
                        </p>
                        <p>
                            <label for="end_date">' . __( 'End Date (optional)', 'wp-charity' ) . '</label>
                            <input type="date" name="end_date" id="end_date" value="' . ( $edit_campaign ? esc_attr( get_post_meta( $edit_campaign->ID, '_end_date', true ) ) : '' ) . '">
                        </p>
                    </div>
                    <p>
                        <label for="campaign_status">' . __( 'Status', 'wp-charity' ) . '</label>
                        <select name="campaign_status" id="campaign_status" disabled required>
                            <option value="draft" ' . ( ( $edit_campaign && $edit_campaign->post_status == 'draft' ) || ( ! $edit_campaign && get_option( 'fxm_volunteer_campaign_status', 'draft' ) == 'draft' ) ? 'selected' : '' ) . '>' . __( 'Draft (Requires Approval)', 'wp-charity' ) . '</option>
                            <option value="publish" ' . ( ( $edit_campaign && $edit_campaign->post_status == 'publish' ) || ( ! $edit_campaign && get_option( 'fxm_volunteer_campaign_status', 'draft' ) == 'publish' ) ? 'selected' : '' ) . '>' . __( 'Published (Automatic Approval)', 'wp-charity' ) . '</option>
                        </select>
                        <br><small>' . ( get_option( 'fxm_volunteer_campaign_status', 'draft' ) == 'draft' ? __( 'Drafts are not visible to the public and they need to be approved by an administrator before they are visible.', 'wp-charity' ) : __( 'Campaigns will be automatically published and visible to the public.', 'wp-charity' ) ) . '</small>
                    </p>
                    <p>
                        <label for="youtube_url">' . __( 'YouTube Video URL', 'wp-charity' ) . '</label>
                        <input type="url" name="youtube_url" id="youtube_url" value="' . ( $edit_campaign ? esc_attr( get_post_meta( $edit_campaign->ID, '_youtube_url', true ) ) : '' ) . '" placeholder="https://www.youtube.com/watch?v=...">
                        <br><small>' . __( 'Add a YouTube video URL to showcase your campaign. This will be displayed on your campaign page.', 'wp-charity' ) . '</small>
                    </p>
                    <p>
                        <label for="campaign_image">' . __( 'Image', 'wp-charity' ) . '</label>
                        <div class="campaign-image-preview">
                            <img src="' . ( $edit_campaign ? get_the_post_thumbnail_url( $edit_campaign->ID, 'thumbnail' ) : '' ) . '" alt="' . __( 'Campaign Image Preview', 'wp-charity' ) . '" style="max-width: 100%; height: auto; margin-bottom: 10px; display: ' . ( $edit_campaign ? 'block' : 'none' ) . ';">
                        </div>
                        <input type="file" name="campaign_image" id="campaign_image" accept="image/*">
                        <br><small>' . __( 'Upload a featured image for your campaign. Recommended size: 1200x630 pixels.', 'wp-charity' ) . '</small>
                    </p>';

        if ( $edit_campaign && has_post_thumbnail( $edit_campaign->ID ) ) {
            $out .= '<p>' . __( 'Current Image:', 'wp-charity' ) . '</p>' . get_the_post_thumbnail( $edit_campaign->ID, 'thumbnail' );
        }

                    $out .= '<p>
                        <input type="submit" name="submit_campaign" value="' . ( $edit_campaign ? __( 'Update Campaign', 'wp-charity' ) : __( 'Add Campaign', 'wp-charity' ) ) . '">
                    </p>
                </div>
            </div>
        </form>';
    } else {
        $out = '<p>' . __( 'You must be logged in as a volunteer to access this page.', 'wp-charity' ) . '</p>';
    }

    return $out; // Return the output
}

/**
 * Preserve the original author when an admin edits a campaign
 */
function cm_preserve_campaign_author( $data, $postarr ) {
    // Only modify campaign post type
    if ( $data['post_type'] !== 'campaign' ) {
        return $data;
    }

    // If this is an existing post (has ID) and we're not explicitly setting the author
    if ( ! empty( $postarr['ID'] ) && ! isset( $postarr['post_author'] ) ) {
        // Get the original post
        $original_post = get_post( $postarr['ID'] );
        if ( $original_post ) {
            // Keep the original author
            $data['post_author'] = $original_post->post_author;
        }
    }

    return $data;
}
add_filter( 'wp_insert_post_data', 'cm_preserve_campaign_author', 10, 2 );
