<?php
/**
 * Email functionality for WP Charity
 * 
 * @package WP_Charity
 * @since 1.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get campaign details (name and permalink) for a given order ID
 * 
 * @param int $order_id The WooCommerce order ID
 * @return array|false Array with 'name' and 'permalink' keys, or false if no campaign found
 */
function cm_get_campaign_details_by_order_id( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return false;
    }

    foreach ( $order->get_items() as $item ) {
        $campaign_id = $item->get_meta( '_campaign_id' );
        if ( $campaign_id ) {
            $campaign = get_post( $campaign_id );
            if ( $campaign && $campaign->post_type === 'campaign' ) {
                return [
                    'name'      => $campaign->post_title,
                    'permalink' => get_permalink( $campaign_id ),
                    'id'        => $campaign_id
                ];
            }
        }
    }

    return false;
}

/**
 * Add campaign information to the new order email
 * 
 * @param WC_Order $order The order object
 * @param bool $sent_to_admin Whether the email is being sent to admin
 * @param bool $plain_text Whether the email is plain text
 * @param WC_Email $email The email object
 */
function cm_add_campaign_info_to_new_order_email( $order, $sent_to_admin, $plain_text, $email ) {
    // Only add to new order emails (not other email types)
    if ( ! $email instanceof WC_Email_New_Order ) {
        return;
    }

    $campaign_details = cm_get_campaign_details_by_order_id( $order->get_id() );
    
    if ( ! $campaign_details ) {
        return;
    }

    if ( $plain_text ) {
        echo "\n\n";
        echo "Campaign Information:\n";
        echo "Campaign: " . $campaign_details['name'] . "\n";
        echo "Campaign URL: " . $campaign_details['permalink'] . "\n";
    } else {
        echo '<div style="margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #0073aa;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #0073aa;">Campaign Information</h3>';
        echo '<p style="margin: 0;"><strong>Campaign:</strong> <a href="' . esc_url( $campaign_details['permalink'] ) . '">' . esc_html( $campaign_details['name'] ) . '</a></p>';
        echo '</div>';
    }
}
add_action( 'woocommerce_email_order_details', 'cm_add_campaign_info_to_new_order_email', 10, 4 );

/**
 * Add campaign information to the new order email for WooCommerce 10+ editable templates
 * This hook works with the new email template system
 */
function cm_add_campaign_info_to_new_order_email_template( $order, $sent_to_admin, $plain_text, $email ) {
    // Only add to new order emails
    if ( ! $email instanceof WC_Email_New_Order ) {
        return;
    }

    $campaign_details = cm_get_campaign_details_by_order_id( $order->get_id() );
    
    if ( ! $campaign_details ) {
        return;
    }

    if ( $plain_text ) {
        echo "\n\n";
        echo "Campaign Information:\n";
        echo "Campaign: " . $campaign_details['name'] . "\n";
        echo "Campaign URL: " . $campaign_details['permalink'] . "\n";
    } else {
        echo '<div style="margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #0073aa;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #0073aa;">Campaign Information</h3>';
        echo '<p style="margin: 0;"><strong>Campaign:</strong> <a href="' . esc_url( $campaign_details['permalink'] ) . '">' . esc_html( $campaign_details['name'] ) . '</a></p>';
        echo '</div>';
    }
}
add_action( 'woocommerce_email_order_meta', 'cm_add_campaign_info_to_new_order_email_template', 10, 4 );

/**
 * Add campaign information to the customer new order email as well
 */
function cm_add_campaign_info_to_customer_new_order_email( $order, $sent_to_admin, $plain_text, $email ) {
    // Only add to customer new order emails
    if ( ! $email instanceof WC_Email_Customer_New_Order ) {
        return;
    }

    $campaign_details = cm_get_campaign_details_by_order_id( $order->get_id() );
    
    if ( ! $campaign_details ) {
        return;
    }

    if ( $plain_text ) {
        echo "\n\n";
        echo "Campaign Information:\n";
        echo "Campaign: " . $campaign_details['name'] . "\n";
        echo "Campaign URL: " . $campaign_details['permalink'] . "\n";
    } else {
        echo '<div style="margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #0073aa;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #0073aa;">Campaign Information</h3>';
        echo '<p style="margin: 0;"><strong>Campaign:</strong> <a href="' . esc_url( $campaign_details['permalink'] ) . '">' . esc_html( $campaign_details['name'] ) . '</a></p>';
        echo '</div>';
    }
}
add_action( 'woocommerce_email_order_details', 'cm_add_campaign_info_to_customer_new_order_email', 10, 4 );
add_action( 'woocommerce_email_order_meta', 'cm_add_campaign_info_to_customer_new_order_email', 10, 4 );
