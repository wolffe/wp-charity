<?php
/**
 * Plugin Name: WP Charity for WooCommerce
 * Plugin URI: https://4property.com/wordpress-plugins/wp-charity-wordpress-donation-plugin-fundraising/
 * Description: The WordPress fundraising alternative for non-profits, created to help non-profits raise money on their own website.
 * Version: 1.2.6
 * Author: Ciprian Popescu
 * Author URI: https://getbutterfly.com/
 * Requires at least: 7.1
 * Requires Plugins: woocommerce
 * Tested up to: 7.1.3
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-charity
 *
 * WC requires at least: 7.0.0
 * WC tested up to: 11.2.1
 *
 * WP Charity for WooCommerce (c) 2024-2026 Ciprian Popescu (https://getbutterfly.com/)
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
 *
 * Documentation:
 * https://woocommerce.com/document/quick-guide-to-woocommerce-add-to-cart-urls/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CM_PLUGIN_VERSION', '1.2.6' );

require_once 'includes/donations.php';
require_once 'includes/account-actions.php';

require_once 'includes/meta.php';
require_once 'includes/settings.php';
require_once 'includes/account.php';
require_once 'includes/shortcodes.php';
require_once 'includes/email.php';

/**
 * Declare HPOS compatibility (WooCommerce High-Performance Order Storage).
 * This lets WooCommerce know the plugin works with the custom order tables.
 */
add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);

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
        --fxm-cta-background: ' . get_option( 'fxm_cta_background', '#2f3542' ) . ';
        --fxm-cta-text: ' . get_option( 'fxm_cta_text', '#ecf0f1' ) . ';
    }';

    wp_add_inline_style( 'fxm-members', $css );
}

add_action( 'wp_enqueue_scripts', 'fxm_enqueue_scripts' );

function fxm_admin_enqueue_scripts() {
    wp_enqueue_script( 'akar-icons', 'https://unpkg.com/akar-icons-fonts', [], '1.1.22', true );

    wp_enqueue_style( 'fxm-admin', plugins_url( '/assets/css/fxm-admin.css', __FILE__ ), [], CM_PLUGIN_VERSION );
}

add_action( 'admin_enqueue_scripts', 'fxm_admin_enqueue_scripts' );




// Add a new user role "volunteer": front-end campaigns only (no posts/pages in wp-admin).
function cm_add_volunteer_role() {
    add_role(
        'volunteer',
        __( 'Volunteer', 'wp-charity' ),
        [
            'read'                       => true,
            'upload_files'               => true,
            'edit_campaign'              => true,
            'read_campaign'              => true,
            'delete_campaign'            => true,
            'edit_campaigns'             => true,
            'delete_campaigns'           => true,
            'edit_published_campaigns'   => true,
            'publish_campaigns'          => true,
        ]
    );
}
register_activation_hook( __FILE__, 'cm_add_volunteer_role' );

/**
 * Ensure campaign capabilities exist on roles after CPT registration (upgrades + admin/editor access).
 */
function cm_sync_campaign_capabilities_for_roles() {
    $pto = get_post_type_object( 'campaign' );
    if ( ! $pto || empty( $pto->cap ) || ! is_object( $pto->cap ) ) {
        return;
    }
    $caps = array_unique( array_filter( array_values( (array) $pto->cap ) ) );
    foreach ( [ 'administrator', 'editor' ] as $role_name ) {
        $role = get_role( $role_name );
        if ( ! $role ) {
            continue;
        }
        foreach ( $caps as $cap ) {
            $role->add_cap( $cap );
        }
    }
    $volunteer = get_role( 'volunteer' );
    if ( ! $volunteer ) {
        return;
    }
    foreach ( [ 'edit_posts', 'delete_posts', 'edit_published_posts', 'publish_posts', 'edit_pages', 'delete_pages', 'publish_pages', 'edit_published_pages', 'edit_others_posts', 'edit_others_pages' ] as $remove ) {
        $volunteer->remove_cap( $remove );
    }
    foreach ( $caps as $cap ) {
        if ( strpos( (string) $cap, 'others' ) !== false || strpos( (string) $cap, 'private' ) !== false ) {
            continue;
        }
        $volunteer->add_cap( $cap );
    }
}
add_action( 'init', 'cm_sync_campaign_capabilities_for_roles', 20 );

/**
 * Volunteers cannot use wp-admin; campaigns are created on the front end only.
 */
function cm_redirect_volunteer_from_admin() {
    if ( ! is_user_logged_in() ) {
        return;
    }
    $user = wp_get_current_user();
    if ( ! in_array( 'volunteer', (array) $user->roles, true ) ) {
        return;
    }
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return;
    }
    if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
        return;
    }
    $account_id = (int) get_option( 'fxm_members_account_page_id' );
    $redirect   = $account_id ? get_permalink( $account_id ) : home_url( '/' );
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_init', 'cm_redirect_volunteer_from_admin', 1 );

/**
 * After login, send volunteers to the member account page instead of wp-admin.
 */
function cm_volunteer_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
    if ( $user instanceof WP_User && in_array( 'volunteer', (array) $user->roles, true ) ) {
        $account_id = (int) get_option( 'fxm_members_account_page_id' );
        if ( $account_id ) {
            return get_permalink( $account_id );
        }
        return home_url( '/' );
    }
    return $redirect_to;
}
add_filter( 'login_redirect', 'cm_volunteer_login_redirect', 10, 3 );

// Remove the volunteer role on plugin deactivation
function cm_remove_volunteer_role() {
    remove_role( 'volunteer' );
}
register_deactivation_hook( __FILE__, 'cm_remove_volunteer_role' );


// Display the campaign content and its associated donation product.
function cm_display_product_on_child_campaign( $content ) {
    if ( ! is_singular( 'campaign' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    global $post;

    $rendered_campaign_content = $content;
    $content                    = '';
    $parent_id                  = wp_get_post_parent_id( $post->ID );
    $parent_campaign            = $parent_id ? get_post( $parent_id ) : null;
    $is_child                   = $parent_campaign && 'campaign' === $parent_campaign->post_type;
    $product_source_id          = $is_child ? $parent_id : $post->ID;
    $product_id                 = get_post_meta( $product_source_id, '_cm_product_id', true );
    $parent_campaign_volunteers = '';
    $hide_donation_box          = get_post_meta( $post->ID, '_hide_donation_box', true ) === '1';

    $campaign_thumbnail = has_post_thumbnail( $post->ID ) ? '<div class="fxm--campaign-thumbnail">' . get_the_post_thumbnail( $post->ID, 'full' ) . '</div>' : '';
    $campaign_excerpt   = ! empty( $post->post_excerpt ) ? '<div class="fxm--campaign-summary">' . wpautop( wp_kses_post( $post->post_excerpt ) ) . '</div>' : '';
    $campaign_content   = '' !== trim( $rendered_campaign_content ) ? '<div class="fxm--campaign-story">' . $rendered_campaign_content . '</div>' : '';

    // Campaign dates
    $campaign_dates = '';
    $start_date     = get_post_meta( $post->ID, '_start_date', true );
    $end_date       = get_post_meta( $post->ID, '_end_date', true );

    if ( $start_date || $end_date ) {
        $campaign_dates = '<div class="fxm--campaign-dates">';
        if ( $start_date ) {
            $campaign_dates .= '<div><span>' . esc_html__( 'Starts', 'wp-charity' ) . '</span><strong>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ) . '</strong></div>';
        }
        if ( $end_date ) {
            $campaign_dates .= '<div><span>' . esc_html__( 'Donations available until', 'wp-charity' ) . '</span><strong>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $end_date ) ) ) . '</strong></div>';
        }
        $campaign_dates .= '</div>';
    }

    // QR code
    $qr_code = cm_generate_qr_code( get_permalink() );

    if ( ! $is_child && cm_campaign_allows_volunteers( $post->ID ) ) {
        $parent_campaign_volunteers = '<a href="' . esc_url( get_permalink( get_option( 'fxm_members_account_page_id' ) ) ) . '" class="fxm--button fxm--button-secondary"><i class="ai-credit-card ai-fw"></i> ' . esc_html__( 'Become a Volunteer', 'wp-charity' ) . '</a>';
    }

    $campaign_buttons = '';
    if ( $product_id && ! $hide_donation_box ) {
        $product = wc_get_product( $product_id );
        if ( $product ) {
            $campaign_buttons = '<div class="campaign-product">' . cm_generate_donation_form( $product_id, $post->ID, $is_child ? '' : $parent_campaign_volunteers ) . '</div>';
        }
    }

    $youtube_url    = get_post_meta( $post->ID, '_youtube_url', true );
    $youtube_embed  = $youtube_url ? wp_oembed_get( $youtube_url ) : '';
    $campaign_media = '';
    if ( $campaign_thumbnail && $youtube_embed ) {
        $tab_name = 'campaign-media-' . (int) $post->ID;
        $info_id  = 'campaign-info-' . (int) $post->ID;
        $video_id = 'campaign-video-' . (int) $post->ID;
        $campaign_media = '<div class="fxm--campaign-tabs">
            <input class="fxm--tab-control" type="radio" name="' . esc_attr( $tab_name ) . '" id="' . esc_attr( $info_id ) . '" checked="checked">
            <label for="' . esc_attr( $info_id ) . '">' . esc_html__( 'Info', 'wp-charity' ) . '</label>
            <div class="tab">' . $campaign_thumbnail . '</div>
            <input class="fxm--tab-control" type="radio" name="' . esc_attr( $tab_name ) . '" id="' . esc_attr( $video_id ) . '">
            <label for="' . esc_attr( $video_id ) . '">' . esc_html__( 'Video', 'wp-charity' ) . '</label>
            <div class="tab fxm--campaign-video">' . $youtube_embed . '</div>
        </div>';
    } elseif ( $campaign_thumbnail ) {
        $campaign_media = $campaign_thumbnail;
    } elseif ( $youtube_embed ) {
        $campaign_media = '<div class="fxm--campaign-video">' . $youtube_embed . '</div>';
    }

    $parent_context = '';
    if ( $is_child ) {
        if ( cm_campaign_allows_volunteers( $parent_id ) ) {
            $parent_campaign_volunteers = '<a href="' . esc_url( get_permalink( get_option( 'fxm_members_account_page_id' ) ) ) . '" class="fxm--button fxm--button-secondary fxm--button-small"><i class="ai-credit-card ai-fw"></i> ' . esc_html__( 'Become a Volunteer', 'wp-charity' ) . '</a>';
        }
        $parent_context = '<div class="fxm--campaign-parent" role="note" aria-label="' . esc_attr__( 'Parent campaign', 'wp-charity' ) . '">
            <i class="ai-info ai-fw" aria-hidden="true"></i>
            <div><span>' . esc_html__( 'Part of', 'wp-charity' ) . '</span>
            <a href="' . esc_url( get_permalink( $parent_id ) ) . '">' . esc_html( $parent_campaign->post_title ) . '</a></div>' .
            $parent_campaign_volunteers .
        '</div>';
    }

    $author = $is_child ? cm_display_campaign_author( $post ) : '';
    $content = '<div class="fxm-campaign-page">
        <div class="fxm-campaign-layout">
            <div class="fxm-campaign-main">' .
                $campaign_media .
                $author .
                $campaign_content .
                '<div class="fxm-campaign-share">' . $qr_code . '</div>
            </div>
            <div class="fxm-campaign-sidebar" role="complementary" aria-label="' . esc_attr__( 'Campaign donation', 'wp-charity' ) . '">' .
                $parent_context .
                '<div class="fxm--donation-goal">
                    <div class="fxm--box">' .
                        cm_display_campaign_goal( $post->ID ) .
                        $campaign_buttons .
                        $campaign_dates .
                    '</div>' .
                    $campaign_excerpt .
                    cm_display_campaign_orders() .
                '</div>
            </div>
        </div>
    </div>';

    return $content;
}

add_filter( 'the_content', 'cm_display_product_on_child_campaign', 20 );

/**
 * Generate donation form with variation support
 *
 * @param int    $product_id         WooCommerce product ID.
 * @param int    $campaign_id        Campaign post ID.
 * @param string $additional_buttons Extra button HTML appended after Donate/Share.
 * @param string $align              Button row alignment: left, center or right.
 */
function cm_generate_donation_form( $product_id, $campaign_id, $additional_buttons = '', $align = 'center' ) {
    static $form_instance = 0;
    static $js_enqueued   = false;

    if ( ! $product_id ) {
        return '';
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return '';
    }

    $form_instance++;

    switch ( strtolower( $align ) ) {
        case 'left':
            $justify_content = 'flex-start';
            break;
        case 'right':
            $justify_content = 'flex-end';
            break;
        default:
            $justify_content = 'center';
    }

    $form_content = '';

    // Check if product is variable
    if ( $product->is_type( 'variable' ) ) {
        // Variable product - show variations
        $variations = $product->get_available_variations();
        $attributes = $product->get_variation_attributes();

        if ( ! empty( $variations ) && ! empty( $attributes ) ) {
            $form_content = '<div class="campaign-donation">
                <form class="variations_form cart" action="' . esc_url( wc_get_cart_url() ) . '" method="post" enctype="multipart/form-data" data-product_id="' . esc_attr( $product_id ) . '" data-product_variations="' . esc_attr( json_encode( $variations ) ) . '">
                    <input type="hidden" name="campaign_id" value="' . esc_attr( $campaign_id ) . '">
                    
                    <div class="variations">
                        <div class="variations-container">';

            $default_attributes = $product->get_default_attributes();

            foreach ( $attributes as $attribute_name => $options ) {
                $attribute_label = wc_attribute_label( $attribute_name );
                $sanitized_name  = sanitize_title( $attribute_name );
                $default_value   = isset( $default_attributes[ $sanitized_name ] ) ? $default_attributes[ $sanitized_name ] : '';

                $form_content .= '<div class="variation-row">
                    <div class="variation-label">
                        <label>' . esc_html( $attribute_label ) . '</label>
                    </div>
                    <div class="variation-value">
                        <div class="variation-radios" data-attribute_name="attribute_' . esc_attr( $sanitized_name ) . '">';

                if ( ! empty( $options ) ) {
                    foreach ( $options as $option ) {
                        $option_label = is_numeric( $option )
                            ? sprintf( get_woocommerce_price_format(), get_woocommerce_currency_symbol(), $option )
                            : $option;
                        $input_id = $sanitized_name . '_' . sanitize_title( $option ) . '_' . $form_instance;

                        $form_content .= '<label class="variation-radio" for="' . esc_attr( $input_id ) . '">
                            <input type="radio" id="' . esc_attr( $input_id ) . '" name="attribute_' . esc_attr( $sanitized_name ) . '" value="' . esc_attr( $option ) . '" ' . checked( $default_value, $option, false ) . '>
                            <span>' . esc_html( $option_label ) . '</span>
                        </label>';
                    }
                }

                $form_content .= '</div>
                    </div>
                </div>';
            }

            $form_content .= '</div>
                    </div>
                    
                    <div class="single_variation_wrap">
                        <div class="woocommerce-variation single_variation" style="display: none;"></div>
                        <div class="woocommerce-variation-add-to-cart variations_button">
                            <p class="fxm--campaign-actions" style="--fxm-action-alignment: ' . esc_attr( $justify_content ) . ';">
                                <button type="submit" class="fxm--button single_add_to_cart_button" disabled><i class="ai-heart ai-fw"></i> Donate</button>
                                <button type="button" onclick="share(event)" class="fxm--button"><i class="ai-network ai-fw"></i> Share</button>
                                ' . $additional_buttons . '
                            </p>
                            <input type="hidden" name="add-to-cart" value="' . esc_attr( $product_id ) . '">
                            <input type="hidden" name="product_id" value="' . esc_attr( $product_id ) . '">
                            <input type="hidden" name="variation_id" class="variation_id" value="0">
                        </div>
                    </div>
                </form>
            </div>';

            // Enqueue WooCommerce variation scripts (once per request; the script handles every .variations_form on the page)
            if ( function_exists( 'wc_enqueue_js' ) && ! $js_enqueued ) {
                $js_enqueued = true;
                wc_enqueue_js(
                    "
                    jQuery(document).ready(function($) {
                        console.log('Variation form handler loaded');
                        $('.variations_form').each(function() {
                            var \$form = $(this);
                            var \$variationGroups = \$form.find('.variation-radios');
                            var \$submitButton = \$form.find('.single_add_to_cart_button');
                            var \$variationContainer = \$form.find('.single_variation');
                            var \$variationIdInput = \$form.find('.variation_id');
                            var variations = \$form.data('product_variations');
                            var nypActive = \$form.data('nyp_active') === 1;
                            
                            function checkAllSelected() {
                                var allSelected = true;
                                \$variationGroups.each(function() {
                                    if (!$(this).find('input:checked').val()) {
                                        allSelected = false;
                                        return false;
                                    }
                                });
                                return allSelected;
                            }
                            
                            function hasCustomVariation() {
                                var hasCustom = false;
                                \$variationGroups.each(function() {
                                    var val = $(this).find('input:checked').val();
                                    if (val && val.toLowerCase() === 'custom') {
                                        hasCustom = true;
                                        return false;
                                    }
                                });
                                return hasCustom;
                            }
                            
                            function findMatchingVariation() {
                                if (!variations) return null;
                                
                                var selectedAttributes = {};
                                \$variationGroups.each(function() {
                                    var attributeName = $(this).data('attribute_name');
                                    selectedAttributes[attributeName] = $(this).find('input:checked').val();
                                });
                                
                                for (var i = 0; i < variations.length; i++) {
                                    var variation = variations[i];
                                    var match = true;
                                    
                                    for (var attr in selectedAttributes) {
                                        if (variation.attributes[attr] !== selectedAttributes[attr] && variation.attributes[attr] !== '') {
                                            match = false;
                                            break;
                                        }
                                    }
                                    
                                    if (match) {
                                        return variation;
                                    }
                                }
                                return null;
                            }
                            
                            function updateVariation() {
                                if (!checkAllSelected()) {
                                    \$submitButton.prop('disabled', true);
                                    \$variationContainer.hide().html('');
                                    \$variationIdInput.val('0');
                                    return;
                                }
                                
                                var variation = findMatchingVariation();
                                var isCustom = hasCustomVariation();
                                
                                if (variation) {
                                    \$variationIdInput.val(variation.variation_id);
                                    
                                    var html = '';
                                    
                                    if (isCustom) {
                                        // Show custom price input
                                        html += '<div class=\"custom-price-field\">';
                                        html += '<label for=\"custom_price_' + \$form.data('product_id') + '\">Enter your donation amount:</label>';
                                        html += '<div class=\"custom-price-input-wrapper\">';
                                        html += '<input type=\"number\" id=\"custom_price_' + \$form.data('product_id') + '\" name=\"nyp\" class=\"custom-price-input\" placeholder=\"0.00\" min=\"0.01\" step=\"0.01\" required />';
                                        html += '</div>';
                                        html += '<small class=\"custom-price-note\">Please enter the amount you would like to donate.</small>';
                                        html += '</div>';
                                        
                                        \$submitButton.prop('disabled', true); // Disable until price is entered
                                    } else {
                                        // Show regular price
                                        if (variation.display_price) {
                                            html += '<div class=\"woocommerce-variation-price\">Donation Amount: ' + variation.display_price + '</div>';
                                        }
                                        \$submitButton.prop('disabled', false);
                                    }
                                    
                                    if (variation.variation_description) {
                                        html += '<div class=\"woocommerce-variation-description\">' + variation.variation_description + '</div>';
                                    }
                                    
                                    if (html) {
                                        \$variationContainer.html(html).show();
                                        
                                        // Add event listener for custom price input
                                        if (isCustom) {
                                            var \$customInput = \$variationContainer.find('.custom-price-input');
                                            \$customInput.on('input', function() {
                                                var hasValue = $(this).val() && parseFloat($(this).val()) > 0;
                                                \$submitButton.prop('disabled', !hasValue);
                                            });
                                        }
                                    }
                                } else {
                                    \$submitButton.prop('disabled', true);
                                    \$variationContainer.hide().html('');
                                    \$variationIdInput.val('0');
                                }
                            }
                            
                            \$variationGroups.find('input[type=radio]').on('change', updateVariation);
                            updateVariation();
                        });
                    });
                    "
                );
            }
        }
    } else {
        // Simple product - show regular donate button
        $form_content = '<div class="campaign-donation">
            <form class="cart" action="' . esc_url( wc_get_cart_url() ) . '" method="post">
                <input type="hidden" name="add-to-cart" value="' . esc_attr( $product_id ) . '">
                <input type="hidden" name="campaign_id" value="' . esc_attr( $campaign_id ) . '">
                <p class="fxm--campaign-actions" style="--fxm-action-alignment: ' . esc_attr( $justify_content ) . ';">
                    <button type="submit" class="fxm--button"><i class="ai-heart ai-fw"></i> Donate</button>
                    <button type="button" onclick="share(event)" class="fxm--button"><i class="ai-network ai-fw"></i> Share</button>
                    ' . $additional_buttons . '
                </p>
            </form>
        </div>';
    }

    return $form_content;
}

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
 * Campaign stats: cached totals and recent orders per campaign
 */
function cm_get_campaign_stats( $campaign_id ) {
    $summary = cm_campaign_summaries( [ (int) $campaign_id ] );
    return [ 'total_raised' => $summary[ (int) $campaign_id ]['order_total'], 'order_count' => $summary[ (int) $campaign_id ]['order_count'] ];
}

function cm_get_campaign_total_donated( $campaign_id ) {
    global $wp_query;
    if ( ! isset( $GLOBALS['cm_list_totals'] ) ) {
        $ids = is_admin() && isset( $wp_query->posts ) ? wp_list_pluck( $wp_query->posts, 'ID' ) : [];
        $ids[] = (int) $campaign_id;
        $GLOBALS['cm_list_totals'] = cm_campaign_list_totals( array_unique( $ids ) );
    }
    $totals = &$GLOBALS['cm_list_totals'];
    if ( ! isset( $totals[ (int) $campaign_id ] ) ) {
        $totals += cm_campaign_list_totals( [ (int) $campaign_id ] );
    }
    return $totals[ (int) $campaign_id ];
}

/**
 * Display campaign goal and recent donations
 */
function cm_display_campaign_goal( $post_id ) {
    $donation_goal = (float) get_post_meta( $post_id, '_donation_goal', true );
    if ( $donation_goal <= 0 ) {
        return '';
    }

    $stats        = cm_get_campaign_stats( $post_id );
    $total_raised = (float) ( $stats['total_raised'] ?? 0 );

    if ( $total_raised >= $donation_goal && '1' === get_post_meta( $post_id, '_hide_goal_after_reached', true ) ) {
        return '<div class="campaign-progress">
            <div class="progress-stats">
                <div class="progress-amount">
                    <span class="total-raised">' . wc_price( $total_raised ) . '</span>
                    <span class="goal-amount">' . esc_html__( 'raised', 'wp-charity' ) . '</span>
                </div>
            </div>
        </div>';
    }

    $progress     = $donation_goal > 0 ? min( 100, ( $total_raised / $donation_goal ) * 100 ) : 0;

    $content = '<div class="campaign-progress">
        <div class="progress-stats">
            <div class="progress-amount">
                <span class="total-raised">' . wc_price( $total_raised ) . '</span>
                <span class="goal-amount">raised of ' . wc_price( $donation_goal ) . ' goal</span>
            </div>
            <div class="progress-percentage">' . round( $progress ) . '%</div>
        </div>
        <div class="progress-bar-container" role="progressbar" aria-label="' . esc_attr__( 'Campaign fundraising progress', 'wp-charity' ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( (string) round( $progress ) ) . '">
            <div class="progress-bar" style="width: ' . esc_attr( $progress ) . '%;"></div>
        </div>';

    if ( $total_raised >= $donation_goal ) {
        $content .= '<div class="goal-reached">' . esc_html__( 'Goal reached. Thank you to all our donors!', 'wp-charity' ) . '</div>';
    }

    $content .= '</div>';

    return $content;
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
                <img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $first_name . ' ' . $last_name ) . '" width="96" height="96" loading="lazy">
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

    $out         = '';
    $campaign_id = $post->ID;

    try {
        $page = cm_donation_page( [ $campaign_id ], 1, 10, true );
        $orders = [];
        foreach ( $page['rows'] as $row ) {
            $order = wc_get_order( $row['order_id'] );
            if ( $order ) {
                $orders[] = [
                    'timestamp' => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
                    'total'     => (float) $order->get_total(),
                ];
            }
        }
        $limit  = 10; // show last 10 donations
        if ( ! empty( $orders ) ) {
            $out .= '<details class="donations-list-container">
                <summary>Recent Donations</summary>
                <ul class="donation-list">';

            $count = 0;
            foreach ( $orders as $o ) {
                if ( $count++ >= $limit ) {
                    break;
                }
                $order_date  = $o['timestamp'] ? date_i18n( 'F j, Y g:i a', $o['timestamp'] ) : '';
                $order_total = $o['total'];
                $out        .= '<li class="donation-item">
                        <div class="donation-details">
                            <span class="donation-amount">' . wc_price( $order_total ) . '</span>
                            <span class="donation-date">' . esc_html__( 'Donated on', 'wp-charity' ) . ' ' . esc_html( $order_date ) . '</span>
                        </div>
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
    $title      = '';
    $excerpt    = '';
    $content    = '';

    if ( $parent_id ) {
        $goal       = get_post_meta( $parent_id, '_donation_goal', true );
        $start_date = get_post_meta( $parent_id, '_start_date', true );
        $end_date   = get_post_meta( $parent_id, '_end_date', true );

        // Include additional parent campaign data for auto-populating fields
        $parent_post = get_post( $parent_id );
        if ( $parent_post ) {
            $title   = $parent_post->post_title;
            $excerpt = $parent_post->post_excerpt;
            $content = $parent_post->post_content;
        }
    }

    wp_send_json_success(
        [
            'goal'       => $goal,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'title'      => $title,
            'excerpt'    => $excerpt,
            'content'    => $content,
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

/**
 * Handle reassigning a donation line item to a different campaign (order edit screen).
 */
function cm_maybe_reassign_donation_campaign() {
    if ( ! isset( $_POST['cm_reassign_donation_nonce'] ) || ! isset( $_POST['cm_reassign_order_id'] ) || ! isset( $_POST['cm_reassign_item_id'] ) || ! isset( $_POST['cm_reassign_campaign_id'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cm_reassign_donation_nonce'] ) ), 'cm_reassign_donation_campaign' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        return;
    }
    $order_id    = (int) $_POST['cm_reassign_order_id'];
    $item_id     = (int) $_POST['cm_reassign_item_id'];
    $campaign_id = (int) $_POST['cm_reassign_campaign_id'];
    $order = wc_get_order( $order_id );
    $edit_url = $order ? $order->get_edit_order_url() : admin_url();
    if ( ! $order_id || ! $item_id || ! $campaign_id ) {
        wp_safe_redirect( add_query_arg( 'cm_reassign', 'error', $edit_url ) );
        exit;
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_safe_redirect( add_query_arg( 'cm_reassign', 'error', $edit_url ) );
        exit;
    }
    $item = $order->get_item( $item_id );
    if ( ! $item ) {
        wp_safe_redirect( add_query_arg( 'cm_reassign', 'error', $edit_url ) );
        exit;
    }
    $old_campaign_id = (int) $item->get_meta( '_campaign_id' );
    $campaign        = get_post( $campaign_id );
    if ( ! $campaign || $campaign->post_type !== 'campaign' ) {
        wp_safe_redirect( add_query_arg( 'cm_reassign', 'invalid', $edit_url ) );
        exit;
    }
    $item->update_meta_data( '_campaign_id', $campaign_id );
    $item->save();
    cm_clear_campaign_summaries( [ $old_campaign_id, $campaign_id ] );
    wp_safe_redirect( add_query_arg( 'cm_reassign', 'success', $edit_url ) );
    exit;
}
add_action( 'admin_init', 'cm_maybe_reassign_donation_campaign', 5 );

/**
 * Meta box: Reassign donation to another campaign (order edit screen).
 * Supports both classic orders (shop_order) and HPOS order screen.
 */
function cm_add_reassign_donation_meta_box() {
    $screen = 'shop_order';
    if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ) {
        $screen = wc_get_page_screen_id( 'shop-order' );
    }
    add_meta_box(
        'cm_reassign_donation_campaign',
        __( 'Donation campaign', 'wp-charity' ),
        'cm_reassign_donation_meta_box',
        $screen,
        'side'
    );
}
add_action( 'add_meta_boxes', 'cm_add_reassign_donation_meta_box' );

/**
 * Meta box content: list donation line items and dropdown to reassign campaign.
 *
 * @param WP_Post|WC_Order $post_or_order The order post (classic) or order object (HPOS).
 */
function cm_reassign_donation_meta_box( $post_or_order ) {
    $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );
    if ( ! $order ) {
        echo '<p>' . esc_html__( 'Order not found.', 'wp-charity' ) . '</p>';
        return;
    }

    if ( isset( $_GET['cm_reassign'] ) ) {
        if ( $_GET['cm_reassign'] === 'success' ) {
            echo '<p class="notice notice-success" style="margin: 0 0 1em 0;">' . esc_html__( 'Donation campaign updated.', 'wp-charity' ) . '</p>';
        } elseif ( $_GET['cm_reassign'] === 'error' || $_GET['cm_reassign'] === 'invalid' ) {
            echo '<p class="notice notice-error" style="margin: 0 0 1em 0;">' . esc_html__( 'Could not update donation campaign. Please try again.', 'wp-charity' ) . '</p>';
        }
    }

    $line_items = [];
    foreach ( $order->get_items() as $item_id => $item ) {
        $line_items[ $item_id ] = [
            'name'        => $item->get_name(),
            'total'       => $item->get_total(),
            'campaign_id' => (int) $item->get_meta( '_campaign_id' ),
        ];
    }

    if ( empty( $line_items ) ) {
        echo '<p>' . esc_html__( 'No line items in this order. Add products to the order first, then assign a line to a campaign below.', 'wp-charity' ) . '</p>';
        return;
    }

    $campaigns = get_posts(
        [
            'post_type'   => 'campaign',
            'post_status' => [ 'publish', 'draft' ],
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ]
    );

    echo '<p style="margin: 0 0 0.75em 0;"><small>' . esc_html__( 'To count a line as a donation: choose a campaign and click the button. If the donor bought the wrong product, add the correct donation product as a line item, then assign it here.', 'wp-charity' ) . '</small></p>';

    foreach ( $line_items as $item_id => $data ) {
        $has_campaign = $data['campaign_id'] > 0;
        $current_name = '';
        if ( $has_campaign ) {
            $current = get_post( $data['campaign_id'] );
            $current_name = $current && $current->post_type === 'campaign' ? $current->post_title : __( '(Unknown)', 'wp-charity' );
        }
        echo '<div style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ddd;">';
        echo '<p style="margin: 0 0 4px 0;"><strong>' . esc_html( $data['name'] ) . '</strong> ' . wp_kses_post( wc_price( $data['total'] ) ) . '</p>';
        if ( $has_campaign ) {
            echo '<p style="margin: 0 0 6px 0;"><small>' . esc_html__( 'Current campaign:', 'wp-charity' ) . ' ' . esc_html( $current_name ) . '</small></p>';
        } else {
            echo '<p style="margin: 0 0 6px 0;"><small>' . esc_html__( 'Not assigned to a campaign.', 'wp-charity' ) . '</small></p>';
        }
        // Associate controls with a separate footer form, outside WooCommerce's order form.
        $form_id = 'cm-assign-' . (int) $item_id;
        $GLOBALS['cm_assignment_forms'][ $item_id ] = $order;
        echo '<select form="' . esc_attr( $form_id ) . '" name="cm_reassign_campaign_id" style="width: 100%; margin-bottom: 6px;" required>';
        echo '<option value="">' . esc_html__( '— Select campaign —', 'wp-charity' ) . '</option>';
        foreach ( $campaigns as $c ) {
            $selected = (int) $c->ID === (int) $data['campaign_id'] ? ' selected' : '';
            echo '<option value="' . esc_attr( (string) $c->ID ) . '"' . $selected . '>' . esc_html( $c->post_title ) . ' (' . (int) $c->ID . ')</option>';
        }
        echo '</select>';
        echo '<button form="' . esc_attr( $form_id ) . '" type="submit" class="button button-primary" style="width: 100%;">' . ( $has_campaign ? esc_html__( 'Reassign to selected campaign', 'wp-charity' ) : esc_html__( 'Assign to selected campaign', 'wp-charity' ) ) . '</button>';
        echo '</div>';
    }
}

add_action( 'admin_footer', function () {
    foreach ( $GLOBALS['cm_assignment_forms'] ?? [] as $item_id => $order ) {
        echo '<form id="cm-assign-' . (int) $item_id . '" method="post" action="' . esc_url( $order->get_edit_order_url() ) . '">';
        wp_nonce_field( 'cm_reassign_donation_campaign', 'cm_reassign_donation_nonce' );
        echo '<input type="hidden" name="cm_reassign_order_id" value="' . (int) $order->get_id() . '">';
        echo '<input type="hidden" name="cm_reassign_item_id" value="' . (int) $item_id . '"></form>';
    }
} );

/**
 * Generate QR code for a given URL using QR Server API
 */
function cm_generate_qr_code( $url ) {
    if ( empty( $url ) ) {
        return '';
    }
    
    $encoded_url = urlencode( $url );
    $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . $encoded_url;
    
    return '<div class="campaign-qr-code">
        <img src="' . esc_url( $qr_code_url ) . '" alt="' . esc_attr__( 'QR code for this campaign', 'wp-charity' ) . '" width="200" height="200" loading="lazy">
        <p>' . esc_html__( 'Scan to share this campaign', 'wp-charity' ) . '</p>
    </div>';
}
