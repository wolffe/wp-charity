<?php
// Register the Custom Post Type (CPT) "campaign"
function cm_register_campaign_cpt() {
    $labels = [
        'name'               => __( 'Campaigns', 'wp-charity' ),
        'singular_name'      => __( 'Campaign', 'wp-charity' ),
        'add_new'            => __( 'Add New', 'wp-charity' ),
        'add_new_item'       => __( 'Add New Campaign', 'wp-charity' ),
        'edit_item'          => __( 'Edit Campaign', 'wp-charity' ),
        'new_item'           => __( 'New Campaign', 'wp-charity' ),
        'view_item'          => __( 'View Campaign', 'wp-charity' ),
        'search_items'       => __( 'Search Campaigns', 'wp-charity' ),
        'not_found'          => __( 'No campaigns found', 'wp-charity' ),
        'not_found_in_trash' => __( 'No campaigns found in Trash', 'wp-charity' ),
        'parent_item_colon'  => __( 'Parent Campaign:', 'wp-charity' ),
        'menu_name'          => __( 'WP Charity', 'wp-charity' ),
    ];

    $args = [
        'labels'              => $labels,
        'public'              => true,
        'has_archive'         => true,
        'rewrite'             => [ 'slug' => 'campaign' ],
        'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt' ],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'hierarchical'        => true,
        'show_in_rest'        => true, // False disables the block editor
        'publicly_queryable'  => true,
        'exclude_from_search' => false,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-heart',
        'taxonomies'          => [],
        'has_archive'         => true,
        'rewrite'             => [ 'slug' => 'campaign' ],
        'query_var'           => true,
        'can_export'          => true,
        'delete_with_user'    => false,
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'comments'            => false, // Disable comments
    ];

    register_post_type( 'campaign', $args );
}
add_action( 'init', 'cm_register_campaign_cpt' );

// Disable comments, pingbacks, and trackbacks for campaigns
function cm_disable_campaign_comments( $open, $post_id ) {
    $post = get_post( $post_id );
    if ( $post->post_type == 'campaign' ) {
        return false;
    }
    return $open;
}
add_filter( 'comments_open', 'cm_disable_campaign_comments', 10, 2 );
add_filter( 'pings_open', 'cm_disable_campaign_comments', 10, 2 );

// Add a unified metabox for all campaign settings
function cm_add_unified_campaign_metabox() {
    add_meta_box(
        'cm_unified_campaign_settings',
        __( 'Campaign Settings', 'wp-charity' ),
        'cm_unified_campaign_metabox_callback',
        'campaign',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'cm_add_unified_campaign_metabox' );

// Callback function for the unified metabox
function cm_unified_campaign_metabox_callback( $post ) {
    wp_nonce_field( 'cm_unified_campaign_nonce', 'cm_unified_campaign_nonce' );

    // Get current values
    $product_id       = get_post_meta( $post->ID, '_cm_product_id', true );
    $parent_id        = wp_get_post_parent_id( $post->ID );
    $allow_volunteers = get_post_meta( $post->ID, '_allow_volunteer_campaigns', true );
    $donation_goal    = get_post_meta( $post->ID, '_donation_goal', true );
    $start_date       = get_post_meta( $post->ID, '_start_date', true );
    $end_date         = get_post_meta( $post->ID, '_end_date', true );

    $is_child = $parent_id > 0;

    // Get all parent campaigns
    $args      = [
        'post_type'      => 'campaign',
        'posts_per_page' => 100,
        'post_status'    => 'publish',
        'post_parent'    => 0,
        'post__not_in'   => [ $post->ID ],
    ];
    $campaigns = get_posts( $args );

    echo '<div class="fxm--grid-container">
        <div>
            <h3>' . __( 'Parent Campaign', 'wp-charity' ) . '</h3>
            <p>
                <label for="parent_campaign" class="screen-reader-text">' . __( 'Select Parent Campaign', 'wp-charity' ) . '</label>
                <select id="parent_campaign" name="parent_campaign" class="widefat">';

                echo '<option value="0">' . __( '— No Parent —', 'wp-charity' ) . '</option>';
    foreach ( $campaigns as $campaign ) {
        $selected = selected( $parent_id, $campaign->ID, false );
        echo '<option value="' . esc_attr( $campaign->ID ) . '" ' . $selected . '>' . esc_html( $campaign->post_title ) . '</option>';
    }
                echo '</select>
            </p>';

    if ( ! $is_child ) {
        echo '<p>
                    <label>
                        <input type="checkbox" id="allow_volunteer_campaigns" name="allow_volunteer_campaigns" value="1" ' . checked( $allow_volunteers, '1', false ) . '>
                        ' . __( 'Allow Volunteer Campaigns', 'wp-charity' ) . '
                    </label>
                </p>';
        echo '<p class="description">' . __( 'Check this box to allow volunteers to create their own campaign under this main campaign.', 'wp-charity' ) . '</p>';
    }
        echo '</div>
        <div>
            <h3>' . __( 'Associated Product', 'wp-charity' ) . '</h3>';

    if ( $is_child ) {
        $parent_product_id = get_post_meta( $parent_id, '_cm_product_id', true );
        if ( $parent_product_id ) {
            $product_id = $parent_product_id;

            echo '<p class="description"><em>' . __( 'This campaign inherits the product from its parent.', 'wp-charity' ) . '</em></p>';
            echo '<input type="hidden" name="cm_product_id" value="' . esc_attr( $parent_product_id ) . '">';

            // Add permalink to WooCommerce product
            $product_url = get_edit_post_link( $parent_product_id );
            if ( $product_url ) {
                echo '<p><a href="' . esc_url( $product_url ) . '" target="_blank">' . esc_html( get_the_title( $parent_product_id ) ) . '</a></p>';
            }
        }
    } else {
        $args     = [
            'post_type'      => 'product',
            'posts_per_page' => 100,
            'post_status'    => 'publish',
        ];
        $products = get_posts( $args );

        echo '<p><label for="cm_product_id" class="screen-reader-text">' . __( 'Select WooCommerce Product', 'wp-charity' ) . '</label>';
        echo '<select id="cm_product_id" name="cm_product_id" class="widefat">';
        echo '<option value="0">' . __( 'Select a Product...', 'wp-charity' ) . '</option>';
        foreach ( $products as $product ) {
            $selected = selected( $product_id, $product->ID, false );
            echo '<option value="' . esc_attr( $product->ID ) . '" ' . $selected . '>' . esc_html( $product->post_title ) . '</option>';
        }
        echo '</select></p>
        <p>' . __( 'Assign a WooCommerce product to this campaign to receive donations.', 'wp-charity' ) . '</p>';
    }

        echo '</div>
    </div>';

    // Campaign Details Section
    echo '<h3>' . __( 'Campaign Details', 'wp-charity' ) . '</h3>';

    echo '<p><label for="donation_goal">' . sprintf( __( 'Donation Goal (%s)', 'wp-charity' ), get_woocommerce_currency_symbol() ) . '</label>';
    echo '<input type="number" id="donation_goal" name="donation_goal" value="' . esc_attr( $donation_goal ) . '" min="0" step="0.01" class="widefat"></p>';

    echo '<p><label for="excerpt">' . __( 'Short Description', 'wp-charity' ) . '</label>';
    echo '<textarea id="excerpt" name="excerpt" rows="4" class="widefat">' . esc_textarea( $post->post_excerpt ) . '</textarea>';
    echo '<p class="description">' . __( 'A short summary of the campaign displayed on the campaign page and in Google Search.', 'wp-charity' ) . '</p></p>';

    echo '<p><label for="start_date">' . __( 'Start Date', 'wp-charity' ) . '</label>';
    echo '<input type="date" id="start_date" name="start_date" value="' . esc_attr( $start_date ) . '" class="widefat"></p>';

    echo '<p><label for="end_date">' . __( 'End Date', 'wp-charity' ) . '</label>';
    echo '<input type="date" id="end_date" name="end_date" value="' . esc_attr( $end_date ) . '" class="widefat"></p>';

    echo '<p class="description">' . __( 'Leave dates empty for an ongoing campaign.', 'wp-charity' ) . '</p>';

    // YouTube Video URL
    $youtube_url = get_post_meta( $post->ID, '_youtube_url', true );
    echo '<p><label for="youtube_url">' . __( 'YouTube Video URL', 'wp-charity' ) . '</label>';
    echo '<input type="url" id="youtube_url" name="youtube_url" value="' . esc_attr( $youtube_url ) . '" class="widefat" placeholder="https://www.youtube.com/watch?v=...">';
    echo '<p class="description">' . __( 'Add a YouTube video URL to showcase your campaign. This will be displayed on your campaign page.', 'wp-charity' ) . '</p>';
}

// Save all campaign settings
function cm_save_unified_campaign_settings( $post_id ) {
    if ( ! isset( $_POST['cm_unified_campaign_nonce'] ) ||
        ! wp_verify_nonce( $_POST['cm_unified_campaign_nonce'], 'cm_unified_campaign_nonce' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Save parent campaign
    if ( isset( $_POST['parent_campaign'] ) ) {
        $parent_id = intval( $_POST['parent_campaign'] );
        remove_action( 'save_post_campaign', 'cm_save_unified_campaign_settings' );
        wp_update_post(
            [
                'ID'          => $post_id,
                'post_parent' => $parent_id,
            ]
        );
        add_action( 'save_post_campaign', 'cm_save_unified_campaign_settings' );
    }

    // Save product ID (only if not a child or if child doesn't inherit from parent)
    if ( isset( $_POST['cm_product_id'] ) ) {
        $parent_id = wp_get_post_parent_id( $post_id );
        if ( ! $parent_id || ! get_post_meta( $parent_id, '_cm_product_id', true ) ) {
            update_post_meta( $post_id, '_cm_product_id', intval( $_POST['cm_product_id'] ) );
        }
    }

    // Save campaign details
    if ( isset( $_POST['donation_goal'] ) ) {
        update_post_meta( $post_id, '_donation_goal', floatval( $_POST['donation_goal'] ) );
    }

    // Save excerpt
    if ( isset( $_POST['excerpt'] ) ) {
        remove_action( 'save_post_campaign', 'cm_save_unified_campaign_settings' );
        wp_update_post(
            [
                'ID'           => $post_id,
                'post_excerpt' => sanitize_textarea_field( $_POST['excerpt'] ),
            ]
        );
        add_action( 'save_post_campaign', 'cm_save_unified_campaign_settings' );
    }

    if ( isset( $_POST['start_date'] ) ) {
        update_post_meta( $post_id, '_start_date', sanitize_text_field( $_POST['start_date'] ) );
    }

    if ( isset( $_POST['end_date'] ) ) {
        update_post_meta( $post_id, '_end_date', sanitize_text_field( $_POST['end_date'] ) );
    }

    // Save YouTube Video URL
    if ( isset( $_POST['youtube_url'] ) ) {
        update_post_meta( $post_id, '_youtube_url', esc_url_raw( $_POST['youtube_url'] ) );
    }

    // Save volunteer settings
    $allow_volunteers = isset( $_POST['allow_volunteer_campaigns'] ) ? '1' : '0';
    update_post_meta( $post_id, '_allow_volunteer_campaigns', $allow_volunteers );
}
add_action( 'save_post_campaign', 'cm_save_unified_campaign_settings' );

// Helper function to check if a campaign allows volunteers
function cm_campaign_allows_volunteers( $campaign_id ) {
    return get_post_meta( $campaign_id, '_allow_volunteer_campaigns', true ) === '1';
}

// Send notification to admin when a new campaign is created
function cm_notify_admin_new_campaign( $post_id, $post, $update ) {
    // Only proceed if this is a new campaign post (not an update)
    if ( $update || $post->post_type !== 'campaign' || $post->post_status !== 'draft' ) {
        return;
    }

    // Get notification emails from settings
    $options             = get_option( 'cm_settings' );
    $notification_emails = isset( $options['notification_emails'] ) ?
        array_map( 'trim', explode( ',', $options['notification_emails'] ) ) :
        [ get_option( 'admin_email' ) ];

    $subject = __( 'New Campaign Pending Review', 'wp-charity' );

    $author_id   = $post->post_author;
    $author      = get_userdata( $author_id );
    $author_name = $author ? $author->display_name : __( 'Unknown', 'wp-charity' );

    $message = sprintf(
        __(
            'A new campaign "%s" has been created by %s and is pending review.' . "\n\n" .
            'Campaign Details:' . "\n" .
            'Title: %s' . "\n" .
            'Author: %s' . "\n" .
            'Status: Draft' . "\n\n" .
            'Click here to review: %s',
            'wp-charity'
        ),
        $post->post_title,
        $author_name,
        $post->post_title,
        $author_name,
        admin_url( 'post.php?post=' . $post_id . '&action=edit' )
    );

    // Send email to each recipient
    foreach ( $notification_emails as $email ) {
        if ( ! empty( $email ) && is_email( $email ) ) {
            wp_mail( $email, $subject, $message );
        }
    }
}
add_action( 'wp_insert_post', 'cm_notify_admin_new_campaign', 10, 3 );

// Prevent status change for non-admins
function cm_prevent_status_change( $data, $postarr ) {
    // Only proceed for campaign post type
    if ( $data['post_type'] !== 'campaign' ) {
        return $data;
    }

    // Allow admins to change status
    if ( current_user_can( 'administrator' ) ) {
        return $data;
    }

    // For new posts, force draft status
    if ( empty( $postarr['ID'] ) ) {
        $data['post_status'] = 'draft';
        return $data;
    }

    // For existing posts, maintain current status unless admin
    $current_post = get_post( $postarr['ID'] );
    if ( $current_post ) {
        $data['post_status'] = $current_post->post_status;
    }

    return $data;
}
add_filter( 'wp_insert_post_data', 'cm_prevent_status_change', 10, 2 );
