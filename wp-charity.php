<?php
/**
 * Plugin Name: WP Charity for WooCommerce
 * Plugin URI: https://getbutterfly.com/wordpress-plugins/wp-charity-wordpress-donation-plugin-fundraising/
 * Description: The WordPress fundraising alternative for non-profits, created to help non-profits raise money on their own website.
 * Version: 1.0.5
 * Author: Ciprian Popescu
 * Author URI: https://getbutterfly.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-charity
 *
 * WP Charity for WooCommerce (c) 2024-2025 Ciprian Popescu (https://getbutterfly.com/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CM_PLUGIN_VERSION', '1.0.5' );

require CM_PLUGIN_DIR . '/includes/updater.php';

require_once 'includes/meta.php';
require_once 'includes/settings.php';
require_once 'includes/account.php';
require_once 'includes/shortcodes.php';

function fxm_enqueue_scripts() {
    wp_enqueue_script( 'akar-icons', 'https://unpkg.com/akar-icons-fonts', [], '1.1.22', true );

    wp_enqueue_style( 'fxm-members', plugins_url( '/assets/css/fxm-members.css', __FILE__ ), [], CM_PLUGIN_VERSION );
    wp_enqueue_script( 'fxm-members', plugins_url( '/assets/js/fxm-members-init.js', __FILE__ ), [], CM_PLUGIN_VERSION, true );

    wp_add_inline_script(
        'fxm-members',
        'const wp4pmAjaxVar = ' . json_encode(
            [
                'ajaxurl'  => admin_url( 'admin-ajax.php' ),
                'loginurl' => get_permalink( (int) get_option( 'fxm_members_account_page_id' ) ),
            ]
        ),
        'before'
    );

    $css = ':root {
        --fxm-accent-background: ' . get_option( 'fxm_accent_background', '#2f3542' ) . ';
        --fxm-accent-text: ' . get_option( 'fxm_accent_text', '#ecf0f1' ) . ';
    }';

    wp_add_inline_style( 'fxm-members', $css );
}

add_action( 'wp_enqueue_scripts', 'fxm_enqueue_scripts' );

function fxm_admin_enqueue_scripts() {
    wp_enqueue_script( 'akar-icons', 'https://unpkg.com/akar-icons-fonts', [], '1.1.22', true );

    wp_enqueue_style( 'fxm-admin', plugins_url( '/assets/css/fxm-admin.css', __FILE__ ), [], CM_PLUGIN_VERSION );
}

add_action( 'admin_enqueue_scripts', 'fxm_admin_enqueue_scripts' );




// Add a new user role "volunteer" with capabilities similar to "author"
function cm_add_volunteer_role() {
    add_role(
        'volunteer',
        __( 'Volunteer', 'wp-charity' ),
        [
            'read'                    => true,
            'edit_posts'              => true,
            'delete_posts'            => true,
            'edit_published_posts'    => true,
            'publish_posts'           => true,
            'upload_files'            => true,
            'edit_campaign'           => true,
            'edit_others_campaigns'   => false,
            'delete_others_campaigns' => false,
        ]
    );
}
register_activation_hook( __FILE__, 'cm_add_volunteer_role' );

// Remove the volunteer role on plugin deactivation
function cm_remove_volunteer_role() {
    remove_role( 'volunteer' );
}
register_deactivation_hook( __FILE__, 'cm_remove_volunteer_role' );


// Display the parent campaign's associated product on child campaign pages
function cm_display_product_on_child_campaign( $content ) {
    if ( ! is_singular( 'campaign' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    global $post;

    $content                    = '';
    $parent_id                  = wp_get_post_parent_id( $post->ID );
    $product_id                 = get_post_meta( $post->ID, '_cm_product_id', true );
    $parent_campaign            = get_post( $parent_id );
    $parent_campaign_volunteers = '';

    $campaign_thumbnail = ( has_post_thumbnail( $post->ID ) ) ? '<div class="fxm--campaign-thumbnail">' . get_the_post_thumbnail( $post->ID, 'full' ) . '</div>' : '';
    $campaign_excerpt   = ( ! empty( $post->post_excerpt ) ) ? '<p>' . wp_kses_post( $post->post_excerpt ) . '</p>' : '';
    $campaign_content   = ( ! empty( $post->post_content ) ) ? wpautop( wp_kses_post( $post->post_content ) ) : '';

    // Campaign dates
    $campaign_dates = '';
    $start_date     = get_post_meta( $post->ID, '_start_date', true );
    $end_date       = get_post_meta( $post->ID, '_end_date', true );

    if ( $start_date ) {
        $campaign_dates .= '<div style="text-align:center"><small>Starts on <strong>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ) . '</strong></small></div>';
    }
    if ( $end_date ) {
        $campaign_dates .= '<div style="text-align:center"><small>Donations available until ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $end_date ) ) ) . '</small></div>';
    }

    if ( ! $parent_id ) {
        // This is a campaign without a parent campaign, show simplified content

        // If the campaign allows volunteers, show the volunteer link
        if ( cm_campaign_allows_volunteers( $post->ID ) ) {
            $parent_campaign_volunteers = '<a href="' . esc_url( get_permalink( get_option( 'fxm_members_account_page_id' ) ) ) . '" class="fxm--button fxm--button-secondary"><i class="ai-credit-card"></i> Become a Volunteer</a>';
        }

        $content .= '<div class="campaign-content">';

        //$content .= $campaign_thumbnail;
        //$content .= $campaign_excerpt;
        $content .= $campaign_content;

        // Add donation button
        if ( $product_id ) {
            $content .= '<div class="campaign-donation">
                <form class="cart" action="' . esc_url( wc_get_cart_url() ) . '" method="post">
                    <input type="hidden" name="add-to-cart" value="' . esc_attr( $product_id ) . '">
                    <input type="hidden" name="campaign_id" value="' . esc_attr( $post->ID ) . '">
                    <p style="display: flex; gap: 1em; justify-content: center;">
                        <button type="submit" class="fxm--button"><i class="ai-heart"></i> Donate</button>
                        <button type="button" onclick="share()" class="fxm--button"><i class="ai-network"></i> Share</button>' .
                        $parent_campaign_volunteers .
                    '</p>
                </form>
            </div>';
        }

        // Add the orders display
        $content .= cm_display_campaign_orders();

        $content .= '</div>';

        return $content;
    }

    // This is a child campaign, show the full layout (parent details + child details)
    if ( $parent_campaign ) {
        $parent_campaign_thumbnail = ( has_post_thumbnail( $parent_id ) ) ? '<p>' . get_the_post_thumbnail( $parent_id, 'medium' ) . '</p>' : '';
        $parent_campaign_excerpt   = ( ! empty( $parent_campaign->post_excerpt ) ) ? '<p>' . wp_kses_post( $parent_campaign->post_excerpt ) . '</p>' : '';
        $campaign_buttons          = '';

        // If the campaign allows volunteers, show the volunteer link
        if ( cm_campaign_allows_volunteers( $parent_id ) ) {
            $parent_campaign_volunteers = '<a href="' . esc_url( get_permalink( get_option( 'fxm_members_account_page_id' ) ) ) . '" class="fxm--button fxm--button-secondary"><i class="ai-credit-card"></i> Become a Volunteer</a>';
        }

        $product_id = get_post_meta( $parent_id ? $parent_id : $post->ID, '_cm_product_id', true );

        if ( $product_id ) {
            $product = wc_get_product( $product_id );

            if ( $product ) {
                $campaign_buttons = '<div class="campaign-product">
                    <form class="cart" action="' . esc_url( wc_get_cart_url() ) . '" method="post">
                        <input type="hidden" name="add-to-cart" value="' . esc_attr( $product_id ) . '">
                        <input type="hidden" name="campaign_id" value="' . esc_attr( $post->ID ) . '">
                        <p style="display: flex; gap: 1em; justify-content: center;">
                            <button type="submit" class="fxm--button"><i class="ai-heart"></i> Donate</button>
                            <button type="button" onclick="share()" class="fxm--button"><i class="ai-network"></i> Share</button>
                        </p>' .
                        $campaign_dates .
                    '</form>
                </div>';
            }
        }

        // Parent campaign summary
        $content .= '<details class="parent-campaign-details">
            <summary>
                <i class="ai-circle-plus-fill"></i> This campaign is part of the <strong>' . esc_html( $parent_campaign->post_title ) . '</strong>
                <br><small>Click to view details</small>
            </summary>

            <hr class="campaign-separator">' .

            $parent_campaign_thumbnail .
            $parent_campaign_excerpt .

            '<p class="parent-campaign-links">
                <a href="' . esc_url( get_permalink( $parent_id ) ) . '" class="button">View Main Campaign</a>' .
                $parent_campaign_volunteers .
            '</p>
        </details>';

        // Check if a video is available, and if it is, build a tab for it
        $youtube_video_tab = '';
        $youtube_url       = get_post_meta( $post->ID, '_youtube_url', true );

        if ( $youtube_url ) {
            $youtube_video_tab = '<input type="radio" name="tabs" id="tabtwo">
            <label for="tabtwo">Video</label>
            <div class="tab">' . wp_oembed_get( $youtube_url ) . '</div>';
        }

        // Child campaign details
        $content .= '<div class="fxm--grid-container" style="--columns:2; grid-template-columns: 2fr 1fr;">
            <div>
                <div class="fxm--campaign-tabs">
                    <input type="radio" name="tabs" id="tabone" checked="checked">
                    <label for="tabone">Info</label>
                    <div class="tab">' .
                        $campaign_thumbnail .
                    '</div>' .
                    $youtube_video_tab .
                '</div>' .

                cm_display_campaign_author( $post ) .
                $campaign_content .

            '</div>
            <div style="display:block">
                <div class="fxm--donation-goal" style="position:sticky;top:0">
                    <div class="fxm--box">' .

                        cm_display_campaign_goal( $post->ID ) .
                        $campaign_buttons .

                    '</div>' .

                    $campaign_excerpt .
                    cm_display_campaign_orders() .

                '</div>
            </div>
        </div>';
    }

    return $content;
}

add_filter( 'the_content', 'cm_display_product_on_child_campaign' );

// Store the campaign ID in the cart item data
function cm_store_campaign_id_in_cart_item( $cart_item_data, $product_id, $variation_id ) {
    if ( isset( $_POST['campaign_id'] ) ) {
        $cart_item_data['campaign_id'] = sanitize_text_field( $_POST['campaign_id'] );
    }
    return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'cm_store_campaign_id_in_cart_item', 10, 3 );

// Save the campaign ID as order item meta
function cm_save_campaign_id_as_order_meta( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['campaign_id'] ) ) {
        $item->add_meta_data( '_campaign_id', $values['campaign_id'], true );
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'cm_save_campaign_id_as_order_meta', 10, 4 );



/**
 * Display campaign goal and recent donations
 */
function cm_display_campaign_goal( $post_id ) {
    $donation_goal = floatval( get_post_meta( $post_id, '_donation_goal', true ) );

    if ( $donation_goal > 0 ) {
        // Get orders using HPOS compatible method
        $orders = wc_get_orders(
            [
                'limit'  => -1,
                'status' => [ 'completed', 'processing', 'on-hold' ],
                'type'   => 'shop_order',
            ]
        );

        $filtered_orders = [];
        $total_raised    = 0;
        $parent_id       = wp_get_post_parent_id( $post_id );
        $product_id      = get_post_meta( $parent_id ? $parent_id : $post_id, '_cm_product_id', true );

        foreach ( $orders as $order ) {
            foreach ( $order->get_items() as $item ) {
                $item_campaign_id = $item->get_meta( '_campaign_id' );
                if ( $item_campaign_id == $post_id ) {
                    $filtered_orders[] = $order;
                    $total_raised     += $order->get_total();
                    break;
                }
            }
        }

        $progress = min( 100, ( $total_raised / $donation_goal ) * 100 );

        $content = '<div class="campaign-progress">
            <div class="progress-stats">
                <div class="progress-amount">
                    <span class="total-raised">' . wc_price( $total_raised ) . '</span>
                    <span class="goal-amount">raised of ' . wc_price( $donation_goal ) . ' goal</span>
                </div>
                <div class="progress-percentage">' . round( $progress ) . '%</div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: ' . esc_attr( $progress ) . '%;"></div>
            </div>';

            // Goal reached message
        if ( $total_raised >= $donation_goal ) {
                $content .= '<div class="goal-reached">🎉 Goal has been reached! Thank you to all our donors! 🎉</div>';
        }

        $content .= '</div>';

        return $content;
    }
}



/**
 * Display the campaign creator's details.
 */
function cm_display_campaign_author( $post ) {
    $author_id  = $post->post_author;
    $first_name = get_user_meta( $author_id, 'first_name', true );
    $last_name  = get_user_meta( $author_id, 'last_name', true );
    $bio        = get_user_meta( $author_id, 'description', true );
    $avatar     = get_user_meta( $author_id, 'avatar', true );
    $avatar_url = $avatar ? wp_get_attachment_url( $avatar ) : get_avatar_url( $author_id, [ 'size' => 150 ] );

    if ( ! empty( $bio ) ) {
        $bio = '<div class="creator-bio">' . wp_kses_post( $bio ) . '</div>';
    }

    $content = '<div class="campaign-creator">
        <div class="creator-profile">
            <div class="creator-avatar">
                <img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $first_name . ' ' . $last_name ) . '" style="max-width: 128px; height: auto; border-radius: 50%;">
            </div>
            <div class="creator-info">
                <h3>' . esc_html( $first_name . ' ' . $last_name ) . '</h3>' .
                $bio .
            '</div>
        </div>
    </div>';

    return $content;
}


/**
 * Display orders associated with the campaign.
 * Based on the product associated with the campaign.
 */
function cm_display_campaign_orders() {
    if ( ! is_singular( 'campaign' ) ) {
        return '';
    }

    global $post;

    $out             = '';
    $campaign_id     = $post->ID;
    $filtered_orders = [];
    $total_raised    = 0;

    try {
        // Get orders using HPOS compatible method
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
                if ( $item_campaign_id == $campaign_id ) {
                    $filtered_orders[] = $order;
                    $total_raised     += $order->get_total();
                    break;
                }
            }
        }

        if ( ! empty( $filtered_orders ) ) {
            // Sort orders by date, newest first
            usort(
                $filtered_orders,
                function ( $a, $b ) {
                    return $b->get_date_created()->getTimestamp() - $a->get_date_created()->getTimestamp();
                }
            );

            $out .= '<details class="donations-list-container">
                <summary>Recent Donations</summary>
                <ul class="donation-list">';

            foreach ( $filtered_orders as $order ) {
                $order_date   = $order->get_date_created()->date( 'F j, Y g:i a' );
                $order_total  = $order->get_total();
                $order_status = wc_get_order_status_name( $order->get_status() );

                $out .= '<li class="donation-item">
                        <div class="donation-details">
                            <span class="donation-amount">' . wc_price( $order_total ) . '</span>
                            <span class="donation-date">Donated on ' . esc_html( $order_date ) . '</span>
                        </div>
                        <span class="donation-status status-' . esc_attr( strtolower( $order_status ) ) . '">' . esc_html( $order_status ) . '</span>
                    </li>';
            }

                $out .= '</ul>
            </details>';
        }

        return $out;
    } catch ( Exception $e ) {
        // error_log( 'Error in campaign orders: ' . $e->getMessage() );

        return '';
    }

    return '';
}

// Add AJAX handler for getting parent campaign goal
function cm_get_parent_campaign_goal() {
    check_ajax_referer( 'get_parent_goal', 'nonce' );

    $parent_id  = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;
    $goal       = 0;
    $start_date = '';
    $end_date   = '';

    if ( $parent_id ) {
        $goal       = get_post_meta( $parent_id, '_donation_goal', true );
        $start_date = get_post_meta( $parent_id, '_start_date', true );
        $end_date   = get_post_meta( $parent_id, '_end_date', true );
    }

    wp_send_json_success(
        [
            'goal'       => $goal,
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ]
    );
}
add_action( 'wp_ajax_get_parent_campaign_goal', 'cm_get_parent_campaign_goal' );

// Modify the display of campaign ID meta key
function cm_modify_campaign_meta_key( $display_key, $meta, $item ) {
    if ( $meta->key === '_campaign_id' ) {
        return 'Campaign';
    }
    return $display_key;
}
add_filter( 'woocommerce_order_item_display_meta_key', 'cm_modify_campaign_meta_key', 10, 3 );

// Modify the display of campaign ID meta value
function cm_modify_campaign_meta_value( $display_value, $meta, $item ) {
    if ( $meta->key === '_campaign_id' ) {
        $campaign_id = $meta->value;
        $campaign    = get_post( $campaign_id );
        if ( $campaign ) {
            return '<a href="' . get_edit_post_link( $campaign_id ) . '">' . esc_html( $campaign->post_title ) . '</a>';
        }
        return 'Campaign not found';
    }
    return $display_value;
}
add_filter( 'woocommerce_order_item_display_meta_value', 'cm_modify_campaign_meta_value', 10, 3 );
