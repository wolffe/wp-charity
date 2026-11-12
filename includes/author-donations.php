<?php
/** Recipient-specific campaign donation notices, queued through WooCommerce's scheduler. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Resolve only authors of the campaigns attached to this order's donation lines. */
function cm_author_donation_groups( $order ) {
    $groups = [];
    $authors = [];
    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $campaign_id = (int) $item->get_meta( '_campaign_id', true );
        $campaign = $campaign_id > 0 ? get_post( $campaign_id ) : null;
        if ( ! $campaign || 'campaign' !== $campaign->post_type ) {
            continue;
        }
        $author_id = (int) $campaign->post_author;
        if ( ! array_key_exists( $author_id, $authors ) ) {
            $authors[ $author_id ] = get_userdata( $author_id );
        }
        $author = $authors[ $author_id ];
        if ( ! $author || ! is_email( $author->user_email ) ) {
            continue;
        }
        if ( ! isset( $groups[ $author_id ] ) ) {
            $groups[ $author_id ] = [ 'email' => $author->user_email, 'name' => $author->display_name, 'campaigns' => [] ];
        }
        if ( ! isset( $groups[ $author_id ]['campaigns'][ $campaign_id ] ) ) {
            $groups[ $author_id ]['campaigns'][ $campaign_id ] = [ 'name' => $campaign->post_title, 'url' => get_permalink( $campaign_id ), 'amount' => 0.0 ];
        }
        // Match account Donations: discounted line total, excluding tax, shipping and fees.
        $groups[ $author_id ]['campaigns'][ $campaign_id ]['amount'] += (float) $item->get_total();
    }
    return $groups;
}

function cm_author_donation_order_is_eligible( $order ) {
    return $order instanceof WC_Order && 'shop_order' === $order->get_type() && $order->has_status( [ 'processing', 'completed', 'on-hold' ] );
}

function cm_author_notification_state( $order, $key ) {
    $value = $order->get_meta( $key, true );
    return is_array( $value ) ? $value : [];
}

/** No history scan: only enqueue the order whose status just changed. */
function cm_queue_author_donation_notification( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! cm_author_donation_order_is_eligible( $order ) ) {
        return;
    }
    $sent = cm_author_notification_state( $order, '_cm_author_donation_sent' );
    $attempts = cm_author_notification_state( $order, '_cm_author_donation_attempts' );
    foreach ( cm_author_donation_groups( $order ) as $author_id => $group ) {
        if ( empty( $sent[ $author_id ] ) && (int) ( $attempts[ $author_id ] ?? 0 ) < 3 ) {
            $action_id = as_enqueue_async_action( 'cm_send_author_donation_notifications', [ (int) $order_id ], 'wp-charity', true );
            if ( ! $action_id && ! as_has_scheduled_action( 'cm_send_author_donation_notifications', [ (int) $order_id ], 'wp-charity' ) ) {
                wc_get_logger()->error( 'Could not queue campaign author donation notifications.', [ 'source' => 'wp-charity', 'order_id' => (int) $order_id ] );
            }
            break;
        }
    }
}
add_action( 'woocommerce_order_status_changed', 'cm_queue_author_donation_notification', 20, 1 );
add_action( 'woocommerce_payment_complete', 'cm_queue_author_donation_notification', 20, 1 );

/** Atomic option insertion serializes overlapping jobs; a crashed worker's lease expires. */
function cm_author_notification_lock( $order_id ) {
    $key = 'cm_author_mail_lock_' . (int) $order_id;
    $token = time() . ':' . wp_generate_uuid4();
    if ( add_option( $key, $token, '', false ) ) {
        return $token;
    }
    $previous = get_option( $key, '' );
    if ( $previous && (int) $previous < time() - 10 * MINUTE_IN_SECONDS ) {
        cm_release_author_notification_lock( $order_id, $previous );
        if ( add_option( $key, $token, '', false ) ) {
            return $token;
        }
    }
    return false;
}

function cm_release_author_notification_lock( $order_id, $token ) {
    global $wpdb;
    $key = 'cm_author_mail_lock_' . (int) $order_id;
    // Compare-and-delete: an expired worker must never release a newer worker's lease.
    $wpdb->delete( $wpdb->options, [ 'option_name' => $key, 'option_value' => $token ], [ '%s', '%s' ] );
    wp_cache_delete( $key, 'options' );
}

function cm_schedule_author_notification_retry( $order_id, $delay ) {
    // This runs inside the current action: uniqueness would reject its own successor.
    $id = as_schedule_single_action( time() + $delay, 'cm_send_author_donation_notifications', [ (int) $order_id ], 'wp-charity' );
    if ( ! $id ) {
        wc_get_logger()->error( 'Could not schedule a campaign author notification retry.', [ 'source' => 'wp-charity', 'order_id' => (int) $order_id ] );
    }
}

function cm_author_donation_message( $order, $group ) {
    $on_hold = $order->has_status( 'on-hold' );
    $message = '<p>' . sprintf( esc_html__( 'Hi %s,', 'wp-charity' ), esc_html( $group['name'] ) ) . '</p>';
    $message .= '<p>' . ( $on_hold
        ? esc_html__( 'A donation order has been recorded for your campaign. It is on hold and awaiting payment confirmation.', 'wp-charity' )
        : esc_html__( 'A new donation has been received for your campaign.', 'wp-charity' ) ) . '</p>';
    $message .= '<p>' . sprintf( esc_html__( 'Order: #%s', 'wp-charity' ), esc_html( $order->get_order_number() ) ) . '<br>';
    $message .= sprintf( esc_html__( 'Status: %s', 'wp-charity' ), esc_html( wc_get_order_status_name( $order->get_status() ) ) );
    $date = $order->get_date_created();
    if ( $date ) {
        $message .= '<br>' . sprintf( esc_html__( 'Date: %s', 'wp-charity' ), esc_html( wc_format_datetime( $date ) ) );
    }
    $donor = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    $message .= '<br>' . sprintf( esc_html__( 'Donor: %s', 'wp-charity' ), esc_html( $donor ?: __( 'Guest', 'wp-charity' ) ) ) . '</p><ul>';
    foreach ( $group['campaigns'] as $campaign ) {
        $message .= '<li><a href="' . esc_url( $campaign['url'] ) . '">' . esc_html( $campaign['name'] ) . '</a>: ' . wp_kses_post( wc_price( $campaign['amount'], [ 'currency' => $order->get_currency() ] ) ) . '</li>';
    }
    $message .= '</ul><p>' . esc_html__( 'Amounts shown are donation line amounts after discounts, excluding tax, shipping and fees.', 'wp-charity' ) . '</p>';
    $account_page = (int) get_option( 'fxm_members_account_page_id' );
    if ( $account_page && get_permalink( $account_page ) ) {
        $message .= '<p><a href="' . esc_url( get_permalink( $account_page ) . '#donations' ) . '">' . esc_html__( 'View donations in your account', 'wp-charity' ) . '</a></p>';
    }
    return $message;
}

/** One accepted message per author/order; failed recipients retry without resending successes. */
function cm_send_author_donation_notifications( $order_id ) {
    $token = cm_author_notification_lock( $order_id );
    if ( ! $token ) {
        cm_schedule_author_notification_retry( $order_id, MINUTE_IN_SECONDS );
        return;
    }
    try {
        $order = wc_get_order( $order_id );
        if ( ! cm_author_donation_order_is_eligible( $order ) ) {
            return;
        }
        // Reload metadata after obtaining the lease, including when WC has cached the object.
        $order->read_meta_data( true );
        $sent = cm_author_notification_state( $order, '_cm_author_donation_sent' );
        $attempts = cm_author_notification_state( $order, '_cm_author_donation_attempts' );
        $retry = false;
        $mailer = WC()->mailer();
        foreach ( cm_author_donation_groups( $order ) as $author_id => $group ) {
            if ( ! empty( $sent[ $author_id ] ) || (int) ( $attempts[ $author_id ] ?? 0 ) >= 3 ) {
                continue;
            }
            $attempts[ $author_id ] = (int) ( $attempts[ $author_id ] ?? 0 ) + 1;
            $order->update_meta_data( '_cm_author_donation_attempts', $attempts );
            $order->save_meta_data();
            $subject = sanitize_text_field( sprintf( __( 'New campaign donation — Order #%s', 'wp-charity' ), $order->get_order_number() ) );
            $accepted = false;
            try {
                $message = $mailer->wrap_message( __( 'New campaign donation', 'wp-charity' ), cm_author_donation_message( $order, $group ) );
                $accepted = $mailer->send( $group['email'], $subject, $message, "Content-Type: text/html; charset=UTF-8\r\n" );
            } catch ( Throwable $error ) {
                // Avoid logging mail bodies or donor details; log the affected IDs below.
                $accepted = false;
            }
            if ( $accepted ) {
                $sent[ $author_id ] = time();
                $order->update_meta_data( '_cm_author_donation_sent', $sent );
                $order->save_meta_data();
            } else {
                wc_get_logger()->warning( 'Campaign author donation email was not accepted by the mail system.', [ 'source' => 'wp-charity', 'order_id' => (int) $order_id, 'author_id' => $author_id, 'attempt' => $attempts[ $author_id ] ] );
                if ( $attempts[ $author_id ] < 3 ) {
                    $retry = true;
                } else {
                    $order->add_order_note( sprintf( __( 'Donation notification for campaign author #%d failed after three attempts. Check the email service, then use “Retry failed campaign author notifications” in Order actions.', 'wp-charity' ), $author_id ) );
                }
            }
        }
        if ( $retry ) {
            cm_schedule_author_notification_retry( $order_id, 5 * MINUTE_IN_SECONDS );
        }
    } finally {
        cm_release_author_notification_lock( $order_id, $token );
    }
}
add_action( 'cm_send_author_donation_notifications', 'cm_send_author_donation_notifications' );

add_filter( 'woocommerce_order_actions', function ( $actions, $order = null ) {
    if ( cm_author_donation_order_is_eligible( $order ) && cm_author_donation_groups( $order ) ) {
        $actions['cm_retry_author_notifications'] = __( 'Retry failed campaign author notifications', 'wp-charity' );
    }
    return $actions;
}, 10, 2 );
add_action( 'woocommerce_order_action_cm_retry_author_notifications', function ( $order ) {
    if ( ! current_user_can( 'edit_shop_orders' ) || ! cm_author_donation_order_is_eligible( $order ) ) {
        return;
    }
    $order->delete_meta_data( '_cm_author_donation_attempts' );
    $order->save_meta_data();
    cm_queue_author_donation_notification( $order->get_id() );
} );
