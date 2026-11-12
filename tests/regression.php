<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/donations.php';
require __DIR__ . '/../includes/reports.php';
require __DIR__ . '/../includes/account-actions.php';
require __DIR__ . '/../includes/campaigns.php';
require __DIR__ . '/../includes/fxm-users.php';
require __DIR__ . '/../includes/how-to.php';

$wpdb = new TestDB();
$db = $wpdb->db;
$db->exec( 'CREATE TABLE wp_posts (ID INTEGER PRIMARY KEY, post_type TEXT, post_status TEXT, post_date_gmt TEXT, post_author INTEGER, post_parent INTEGER); CREATE TABLE wp_postmeta (meta_id INTEGER PRIMARY KEY, post_id INTEGER, meta_key TEXT, meta_value TEXT); CREATE TABLE wp_wc_orders (id INTEGER PRIMARY KEY, type TEXT, status TEXT, date_created_gmt TEXT, total_amount NUMERIC); CREATE TABLE wp_woocommerce_order_items (order_item_id INTEGER PRIMARY KEY, order_id INTEGER, order_item_type TEXT); CREATE TABLE wp_woocommerce_order_itemmeta (meta_id INTEGER PRIMARY KEY, order_item_id INTEGER, meta_key TEXT, meta_value TEXT)' );

function campaign( $id, $author, $parent = 0, $status = 'publish' ) {
    global $db;
    $GLOBALS['campaigns'][ $id ] = (object) [ 'ID' => $id, 'post_author' => $author, 'post_parent' => $parent, 'post_status' => $status, 'post_type' => 'campaign', 'post_title' => 'Campaign ' . $id ];
    $db->prepare( 'INSERT INTO wp_posts VALUES (?, ?, ?, ?, ?, ?)' )->execute( [ $id, 'campaign', $status, '2026-10-01', $author, $parent ] );
}
function order_fixture( $id, $total, $status, $lines, $type = 'shop_order' ) {
    global $db;
    $GLOBALS['orders'][ $id ] = compact( 'id', 'total', 'status', 'lines', 'type' );
    $db->prepare( 'INSERT INTO wp_wc_orders VALUES (?, ?, ?, ?, ?)' )->execute( [ $id, $type, 'wc-' . $status, '2026-10-08 12:00:00', $total ] );
    $db->prepare( 'INSERT INTO wp_posts VALUES (?, ?, ?, ?, 0, 0)' )->execute( [ $id, $type, 'wc-' . $status, '2026-10-08 12:00:00' ] );
    $db->prepare( 'INSERT INTO wp_postmeta(post_id, meta_key, meta_value) VALUES (?, ?, ?)' )->execute( [ $id, '_order_total', $total ] );
    foreach ( $lines as $index => [ $campaign_id, $amount ] ) {
        $item = $id * 10 + $index;
        $db->prepare( 'INSERT INTO wp_woocommerce_order_items VALUES (?, ?, ?)' )->execute( [ $item, $id, 'line_item' ] );
        foreach ( [ '_campaign_id' => $campaign_id, '_line_total' => $amount ] as $key => $value ) {
            $db->prepare( 'INSERT INTO wp_woocommerce_order_itemmeta(order_item_id, meta_key, meta_value) VALUES (?, ?, ?)' )->execute( [ $item, $key, $value ] );
        }
    }
}
function old_totals( $campaign_id ) {
    $whole = 0; $lines = 0; $count = 0;
    foreach ( $GLOBALS['orders'] as $order ) {
        if ( 'shop_order' !== $order['type'] || ! in_array( $order['status'], [ 'completed', 'processing', 'on-hold' ], true ) ) { continue; }
        $matched = false;
        foreach ( $order['lines'] as [ $id, $amount ] ) {
            if ( $id === $campaign_id ) { $lines += $amount; $matched = true; }
        }
        if ( $matched ) { $whole += $order['total']; ++$count; }
    }
    return [ $whole, $lines, $count ];
}

campaign( 1, 42 ); campaign( 2, 42, 1 ); campaign( 3, 43, 1, 'draft' ); campaign( 4, 43, 1, 'private' ); campaign( 5, 42 ); campaign( 6, 42, 2 );
$GLOBALS['post_meta'][1]['_allow_volunteer_campaigns'] = '1';
order_fixture( 100, 140, 'completed', [ [ 1, 30 ], [ 1, 20 ], [ 2, 40 ], [ 0, 35 ] ] ); // Mixed cart, tax/fees, repeated campaign lines.
order_fixture( 101, 140, 'processing', [ [ 1, 90 ] ] ); // Equal-value orders must both count.
order_fixture( 102, 25, 'on-hold', [ [ 3, 25 ] ] );
order_fixture( 103, 60, 'completed', [ [ 2, 60 ] ] ); // Partially refunded order keeps original amounts.
order_fixture( 104, -10, 'completed', [ [ 2, -10 ] ], 'shop_order_refund' );
order_fixture( 105, 30, 'refunded', [ [ 1, 30 ] ] );
foreach ( [ 'pending', 'failed', 'cancelled', 'trash' ] as $i => $status ) { order_fixture( 110 + $i, 1000, $status, [ [ 1, 1000 ] ] ); }
order_fixture( 120, 4, 'completed', [ [ 4, 4 ], [ 6, 0 ] ] );
for ( $i = 0; $i < 45; ++$i ) { order_fixture( 200 + $i, 1.25, 'completed', [ [ 2, 1.25 ] ] ); }
// Duplicate meta should not multiply donations; WC get_meta(..., true) reads the first.
$db->exec( "INSERT INTO wp_woocommerce_order_itemmeta(order_item_id, meta_key, meta_value) VALUES (1000, '_campaign_id', '5'), (1000, '_line_total', '999')" );

foreach ( [ true, false ] as $hpos ) {
    \Automattic\WooCommerce\Utilities\OrderUtil::$hpos = $hpos;
    $GLOBALS['cache'] = []; $wpdb->queries = [];
    $totals = cm_campaign_summaries( [ 1, 2, 3, 4, 5, 6 ] );
    check( count( $wpdb->queries ) === 1, 'One aggregate query for all visible campaigns' );
    foreach ( [ 1, 2, 3, 4, 5, 6 ] as $id ) {
        [ $whole, $line, $count ] = old_totals( $id );
        same_amount( $totals[ $id ]['order_total'], $whole, 'Whole-order parity' );
        same_amount( $totals[ $id ]['line_total'], $line, 'Line-total parity' );
        check( $totals[ $id ]['order_count'] === $count, 'Distinct order count' );
    }
    cm_campaign_summaries( [ 1, 2, 3 ] );
    check( count( $wpdb->queries ) === 1, 'Warm totals do not query orders' );
    $rollups = cm_campaign_list_totals( [ 1, 2 ] );
    same_amount( $rollups[1], $totals[1]['line_total'] + $totals[2]['line_total'] + $totals[3]['line_total'], 'Parent rollup includes eligible direct children' );
    same_amount( $rollups[2], $totals[2]['line_total'], 'Disabled parent does not include children' );
    $GLOBALS['campaigns'][3]->post_parent = 5;
    $rollups = cm_campaign_list_totals( [ 1 ] );
    same_amount( $rollups[1], $totals[1]['line_total'] + $totals[2]['line_total'], 'Reparented child immediately leaves total' );
    $GLOBALS['campaigns'][3]->post_parent = 1;
    $ids = []; $page = 1;
    do { $data = cm_donation_page( [ 2 ], $page++ ); check( count( $data['rows'] ) <= 20, 'Bounded page' ); $ids = array_merge( $ids, array_column( $data['rows'], 'item_id' ) ); } while ( $data['more'] );
    check( count( $ids ) === 47 && count( array_unique( $ids ) ) === 47, 'Complete pages without duplicate items' );
    $owned = cm_donation_page( null, 1, 200, false, 42 );
    check( ! in_array( 3, array_column( $owned['rows'], 'campaign_id' ) ), 'Other volunteers excluded' );
    check( in_array( 2, array_column( $owned['rows'], 'campaign_id' ) ), 'All owned campaigns queried independent of campaign page' );
    $csv = iterator_to_array( cm_donation_export_rows( 1 ), false );
    check( count( $csv ) === 2, 'Campaign export counts order once' );
    same_amount( array_sum( array_column( $csv, 3 ) ), $totals[1]['order_total'], 'Campaign export matches total' );
    $csv = iterator_to_array( cm_donation_export_rows(), false );
    same_amount( array_sum( array_column( $csv, 4 ) ), array_sum( array_column( $totals, 'line_total' ) ), 'Full export line parity' );
}

// The active store wins even with contradictory, unsynchronised legacy records.
$db->exec( "UPDATE wp_postmeta SET meta_value='9999' WHERE post_id=100 AND meta_key='_order_total'" );
$GLOBALS['cache'] = [];
\Automattic\WooCommerce\Utilities\OrderUtil::$hpos = true;
$hpos_sum = cm_campaign_summaries( [ 1 ] )[1]['order_total'];
\Automattic\WooCommerce\Utilities\OrderUtil::$hpos = false;
check( cm_campaign_summaries( [ 1 ] )[1]['order_total'] !== $hpos_sum, 'Storage-specific cache and source' );
$db->exec( "UPDATE wp_postmeta SET meta_value='140' WHERE post_id=100 AND meta_key='_order_total'" );
\Automattic\WooCommerce\Utilities\OrderUtil::$hpos = true;

// Persisted mutations invalidate old and new associations, including direct meta writes.
cm_campaign_summaries( [ 1, 5 ] );
do_action( 'update_order_item_meta', 1, 1010, '_campaign_id', 5 );
$db->exec( "UPDATE wp_woocommerce_order_itemmeta SET meta_value='5' WHERE order_item_id=1010 AND meta_key='_campaign_id'" );
do_action( 'updated_order_item_meta', 1, 1010, '_campaign_id', 5 );
check( false === get_transient( cm_summary_key( 1 ) ) && false === get_transient( cm_summary_key( 5 ) ), 'Reassignment invalidates both campaigns' );
$totals = cm_campaign_summaries( [ 1, 5 ] );
same_amount( $totals[1]['order_total'], 140, 'Old campaign updated' );
same_amount( $totals[5]['order_total'], 140, 'New campaign updated' );
do_action( 'update_order_item_meta', 2, 1010, '_line_total', 70 );
$db->exec( "UPDATE wp_woocommerce_order_itemmeta SET meta_value='70' WHERE order_item_id=1010 AND meta_key='_line_total'" );
do_action( 'updated_order_item_meta', 2, 1010, '_line_total', 70 );
same_amount( cm_campaign_summaries( [ 5 ] )[5]['line_total'], 70, 'Line amount edit refreshes summary' );
do_action( 'woocommerce_before_trash_order', 101 );
$db->exec( "UPDATE wp_wc_orders SET status='trash' WHERE id=101" );
do_action( 'woocommerce_trash_order', 101 );
same_amount( cm_campaign_summaries( [ 5 ] )[5]['order_total'], 0, 'Trashed order excluded' );
$db->exec( "UPDATE wp_wc_orders SET status='wc-processing' WHERE id=101" );
do_action( 'woocommerce_untrash_order', 101 );
same_amount( cm_campaign_summaries( [ 5 ] )[5]['order_total'], 140, 'Restored order included' );
do_action( 'woocommerce_before_delete_order_item', 1010 );
$db->exec( 'DELETE FROM wp_woocommerce_order_itemmeta WHERE order_item_id=1010; DELETE FROM wp_woocommerce_order_items WHERE order_item_id=1010' );
do_action( 'woocommerce_delete_order_item', 1010 );
same_amount( cm_campaign_summaries( [ 5 ] )[5]['order_total'], 0, 'Deleted line invalidates previous campaign' );
do_action( 'woocommerce_before_delete_order', 102 );
$db->exec( 'DELETE FROM wp_wc_orders WHERE id=102; DELETE FROM wp_woocommerce_order_items WHERE order_id=102' );
do_action( 'woocommerce_delete_order', 102 );
same_amount( cm_campaign_summaries( [ 3 ] )[3]['order_total'], 0, 'Deleted order invalidates summary' );

// Endpoint authorization checks happen before any private data query.
$wpdb->queries = [];
$_POST = [ 'action' => 'cm_campaign_orders', 'nonce' => 'valid', 'campaign_id' => 1 ];
check( ! json_call( 'cm_ajax_report_data' )->success && ! $wpdb->queries, 'Non-admin cannot query Help orders' );
$_POST['action'] = 'cm_account_donations'; $_POST['nonce'] = 'invalid';
check( ! json_call( 'cm_ajax_report_data' )->success && ! $wpdb->queries, 'Invalid nonce blocked before queries' );
$_POST['nonce'] = 'valid'; $GLOBALS['user_id'] = 0;
check( ! json_call( 'cm_ajax_report_data' )->success && ! $wpdb->queries, 'Logged-out request denied' );
$GLOBALS['user_id'] = 42;
$_POST['campaign_id'] = 3;
$response = json_call( 'cm_ajax_report_data' );
check( $response->success && ! str_contains( $response->payload['html'], 'Campaign 3' ), 'Client cannot override campaign ownership' );
$_GET = [ 'page' => 'fxm', 'export_donations_report' => 1 ];
try { fxm_handle_csv_export(); check( false, 'Export must deny non-admin' ); } catch ( RuntimeException $e ) { check( 'Access denied.' === $e->getMessage(), 'Export capability checked' ); }
$GLOBALS['admin'] = true;
try { fxm_handle_csv_export(); check( false, 'Export must require nonce' ); } catch ( RuntimeException $e ) { check( 'Invalid nonce' === $e->getMessage(), 'Export nonce checked' ); }
$GLOBALS['admin'] = false; $_GET = [];

// Account initial HTML never queries donations or loads order objects.
$_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = [];
$GLOBALS['caps']['edit_campaigns'] = false;
$wpdb->queries = []; $GLOBALS['order_loads'] = 0;
$html = fxm_account_page();
check( ! $wpdb->queries && 0 === $GLOBALS['order_loads'], 'Initial account performs no order queries' );
check( str_contains( $html, 'data-action="cm_account_donations"' ), 'Donations lazy container rendered' );
check( 20 === $GLOBALS['last_campaign_query']['posts_per_page'], 'Campaign page bounded' );
$GLOBALS['caps']['edit_campaigns'] = true;
check( cm_can_edit_account_campaign( get_post( 1 ) ), 'Owner can edit' );
check( ! cm_can_edit_account_campaign( get_post( 3 ) ), 'Other owner cannot edit' );
check( ! cm_can_edit_account_campaign( (object) [ 'ID' => 1, 'post_type' => 'page', 'post_author' => 42 ] ), 'Non-campaign cannot be edited' );
$_GET['edit_campaign'] = 3;
check( str_contains( fxm_create_campaign(), 'cannot edit' ), 'GET edit ownership enforced' );
$_GET = []; $_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [ 'submit_campaign' => '1', '_wpnonce' => 'valid', 'campaign_id' => '3', 'parent_campaign' => '1' ];
check( str_contains( fxm_create_campaign(), 'cannot edit' ), 'POST edit ownership enforced' );

// Browser encoding and PHP unslashing preserve passwords and phone numbers.
$password = "A&b+c=quote'\\ tail";
$GLOBALS['user_id'] = 0;
$_POST = array_map( 'addslashes', [ 'nonce' => 'valid', 'new_user_email' => 'new@example.test', 'new_firstname' => 'Jo', 'new_lastname' => 'Bloggs', 'new_user_password' => $password, 're-pwd' => $password, 'new_user_phone' => '+353 1234' ] );
check( json_call( 'cm_handle_registration' )->success, 'Registration without optional policy checkbox succeeds' );
check( $GLOBALS['inserted_user']['user_pass'] === $password, 'Registration preserves password bytes' );
check( '+353 1234' === $GLOBALS['inserted_user']['meta_input']['billing_phone'], 'Phone retained' );
$GLOBALS['options']['wp_page_for_privacy_policy'] = 10;
check( ! json_call( 'cm_handle_registration' )->success, 'Required agreement enforced server-side' );
$_POST['fxm_tc_agree'] = '1'; $_POST['re-pwd'] = 'wrong';
check( ! json_call( 'cm_handle_registration' )->success, 'Password confirmation enforced server-side' );
$GLOBALS['user_id'] = 42;
$_POST = array_map( 'addslashes', [ 'action' => 'wppd_update_user_profile', 'nonce' => 'valid', 'user_id' => '42', 'user_email' => 'new@example.test', 'user_pass' => $password, 'user_pass_confirm' => 'wrong', 'first_name' => 'Jo', 'last_name' => 'Bloggs', 'bio' => 'Hello' ] );
cm_handle_profile_update();
check( ! isset( $GLOBALS['updated_user'] ), 'No partial profile changes on password mismatch' );
$_POST['user_pass_confirm'] = addslashes( $password );
$_POST['user_email'] = 'used@example.test';
cm_handle_profile_update();
check( ! isset( $GLOBALS['updated_user'] ), 'Duplicate email rejected before saving' );
$_POST['user_email'] = 'new@example.test';
cm_handle_profile_update();
check( $GLOBALS['updated_user']['user_email'] === 'new@example.test' && $GLOBALS['updated_user']['user_pass'] === $password, 'Core profile email and password updated correctly' );
check( 'success' === $GLOBALS['cm_profile_notice'][0], 'Successful save reported' );

$_POST = [ 'cm_action' => 'cm_login', 'nonce' => 'valid', 'tiny_email' => 'new@example.test', 'password' => addslashes( $password ) ];
cm_handle_login();
check( $GLOBALS['login_creds']['user_password'] === $password, 'Login preserves password bytes' );
$_POST = [ 'nonce' => 'valid', 'user_login' => 'new@example.test' ];
check( json_call( 'cm_handle_password_reset' )->success && 'new@example.test' === $GLOBALS['reset_login'], 'Reset uses WordPress API' );
$GLOBALS['reset_result'] = new WP_Error( 'invalid_email' );
check( json_call( 'cm_handle_password_reset' )->success, 'Reset does not disclose unknown email' );
$GLOBALS['reset_result'] = new WP_Error( 'retrieve_password_email_failure' );
check( ! json_call( 'cm_handle_password_reset' )->success, 'Reset mail failure reported' );

// Order amount updates and deferred bulk deletion use the persisted save lifecycle.
cm_campaign_summaries( [ 1, 2 ] );
$order = new TestOrder( 100 );
do_action( 'woocommerce_before_order_object_save', $order );
$db->exec( 'UPDATE wp_wc_orders SET total_amount=150 WHERE id=100' );
do_action( 'woocommerce_after_order_object_save', $order );
same_amount( cm_campaign_summaries( [ 1 ] )[1]['order_total'], 150, 'Persisted order total edit refreshed' );
do_action( 'woocommerce_before_order_object_save', $order );
$db->exec( 'DELETE FROM wp_woocommerce_order_itemmeta WHERE order_item_id IN (SELECT order_item_id FROM wp_woocommerce_order_items WHERE order_id=100); DELETE FROM wp_woocommerce_order_items WHERE order_id=100' );
do_action( 'woocommerce_removed_order_items', $order, null );
do_action( 'woocommerce_after_order_object_save', $order );
same_amount( cm_campaign_summaries( [ 1 ] )[1]['order_total'], 0, 'Deferred bulk removal invalidates captured associations' );

// More than one export batch and more than one account page.
campaign( 7, 42 );
for ( $i = 0; $i < 225; ++$i ) { order_fixture( 1000 + $i, 2.5, 'completed', [ [ 7, 2.5 ] ] ); }
$wpdb->queries = [];
$csv = iterator_to_array( cm_donation_export_rows( 7 ), false );
check( count( $csv ) === 225 && count( array_unique( array_column( $csv, 0 ) ) ) === 225, 'Batched CSV complete without duplicates' );
same_amount( array_sum( array_column( $csv, 3 ) ), 562.5, 'Batched CSV total preserved' );
check( count( $wpdb->queries ) === 2, 'CSV queries only two bounded batches' );
$GLOBALS['admin'] = true;
$_POST = [ 'action' => 'cm_campaign_orders', 'nonce' => 'valid', 'campaign_id' => 7, 'page' => 1 ];
$response = json_call( 'cm_ajax_report_data' );
check( $response->success && $response->payload['more'] && substr_count( $response->payload['html'], '<p>' ) === 20, 'Help endpoint limits orders to twenty' );
$GLOBALS['admin'] = false;

$wpdb->queries = [];
ob_start(); cm_render_how_to(); $guide = ob_get_clean();
check( ! $wpdb->queries && 18 === substr_count( $guide, '<details>' ), 'How To renders eighteen FAQs without order queries' );

echo 'PASS: ' . $GLOBALS['assertions'] . " regression assertions (SQLite SQL fixtures; no live WordPress/WooCommerce runtime).\n";
