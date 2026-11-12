<?php
/**
 * Display campaign donation box (variation options + Donate, Share, Volunteer buttons)
 *
 * Usage: [campaign_buttons]
 * Optional parameters:
 * - campaign_id: Specific campaign ID (defaults to current campaign)
 * - align: Button alignment (left, center, right) - defaults to left
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cm_campaign_buttons_shortcode( $atts ) {
    // Parse attributes
    $atts = shortcode_atts(
        [
            'campaign_id' => get_the_ID(),
            'align'       => 'left',
        ],
        $atts,
        'campaign_buttons'
    );

    // Get campaign ID
    $campaign_id = intval( $atts['campaign_id'] );
    if ( ! $campaign_id ) {
        return '';
    }

    // Get product ID (check parent if this is a child campaign)
    $parent_id  = wp_get_post_parent_id( $campaign_id );
    $product_id = get_post_meta( $parent_id ? $parent_id : $campaign_id, '_cm_product_id', true );

    if ( ! $product_id ) {
        return '';
    }

    // Volunteer button (if campaign allows volunteers)
    $volunteer_button = '';
    if ( cm_campaign_allows_volunteers( $parent_id ? $parent_id : $campaign_id ) ) {
        $volunteer_button = '<a href="' . esc_url( get_permalink( get_option( 'fxm_members_account_page_id' ) ) ) . '" class="fxm--button fxm--button-secondary"><i class="ai-credit-card ai-fw"></i> ' . __( 'Become a Volunteer', 'wp-charity' ) . '</a>';
    }

    // Render the full donation form (variation radios for variable products, plain Donate for simple products)
    return cm_generate_donation_form( $product_id, $campaign_id, $volunteer_button, $atts['align'] );
}
add_shortcode( 'campaign_buttons', 'cm_campaign_buttons_shortcode' );

/**
 * Display complete donation box with goal, buttons, excerpt and orders
 *
 * Usage: [donation_box]
 * Optional parameters:
 * - campaign_id: Specific campaign ID (defaults to current campaign)
 * - sticky: Whether to make the box sticky (yes/no, defaults to yes)
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cm_donation_box_shortcode( $atts ) {
    // Parse attributes
    $atts = shortcode_atts(
        [
            'campaign_id' => get_the_ID(),
            'sticky'      => 'yes',
        ],
        $atts,
        'donation_box'
    );

    // Get campaign ID
    $campaign_id = intval( $atts['campaign_id'] );
    if ( ! $campaign_id ) {
        return '';
    }

    // Get campaign
    $post = get_post( $campaign_id );
    if ( ! $post ) {
        return '';
    }

    // Get campaign buttons
    ob_start();
    //cm_display_campaign_buttons( $post->ID );
    $campaign_buttons = ob_get_clean();

    // Get campaign excerpt
    $campaign_excerpt = '';
    if ( $post->post_excerpt ) {
        $campaign_excerpt = '<div class="fxm--campaign-excerpt">' . wpautop( $post->post_excerpt ) . '</div>';
    }

    // Build the output using the same structure
    $sticky_style = $atts['sticky'] === 'yes' ? ' style="position:sticky;top:0"' : '';
    $output       = '<div class="fxm--donation-goal"' . $sticky_style . '>
        <div class="fxm--box">' .
            cm_display_campaign_goal( $post->ID ) .
            $campaign_buttons .
        '</div>' .
        $campaign_excerpt .
        cm_display_campaign_orders() .
    '</div>';

    return $output;
}
add_shortcode( 'donation_box', 'cm_donation_box_shortcode' );
