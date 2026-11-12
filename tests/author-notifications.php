<?php
/** Email transport and scheduler doubles: no real mail or live WordPress writes. */
require __DIR__ . '/bootstrap.php';
define( 'MINUTE_IN_SECONDS', 60 );

class WC_Order extends TestOrder {
    public $state; private $meta = [];
    public function __construct( $id, $items = [], $status = 'processing' ) {
        parent::__construct( $id );
        $this->state = [ 'items' => $items, 'status' => $status, 'type' => 'shop_order', 'meta' => [], 'notes' => [] ];
    }
    public function get_type() { return $this->state['type']; }
    public function get_status() { return $this->state['status']; }
    public function has_status( $statuses ) { return in_array( $this->get_status(), (array) $statuses, true ); }
    public function get_items( $type = 'line_item' ) { return $this->state['items']; }
    public function get_currency() { return 'GBP'; }
    public function get_meta( $key, $single = true ) { return $this->meta[ $key ] ?? ''; }
    public function read_meta_data( $force ) { $this->meta = $this->state['meta']; }
    public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
    public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
    public function save_meta_data() { $this->state['meta'] = $this->meta; }
    public function add_order_note( $note ) { $this->state['notes'][] = $note; }
}
class DonationItem {
    private $campaign; private $total;
    public function __construct( $campaign, $total ) { $this->campaign = $campaign; $this->total = $total; }
    public function get_meta( $key, $single = true ) { return '_campaign_id' === $key ? $this->campaign : ''; }
    public function get_total() { return $this->total; }
}
function test_order( $id, $items, $status = 'processing' ) {
    $order = new WC_Order( $id, $items, $status );
    $GLOBALS['test_order_objects'][ $id ] = $order;
    return $order;
}
function test_campaign( $id, $author, $title, $parent = 0, $type = 'campaign' ) {
    $GLOBALS['campaigns'][ $id ] = (object) [ 'ID' => $id, 'post_author' => $author, 'post_title' => $title, 'post_parent' => $parent, 'post_type' => $type ];
}
function wc_get_order_status_name( $status ) { return ucfirst( $status ); }
function wc_format_datetime( $date ) { return $date->date_i18n( 'Y-m-d' ); }
function wp_generate_uuid4() { static $i = 0; return 'test-token-' . ++$i; }
function wp_cache_delete( $key, $group ) {}
function add_option( $key, $value, $deprecated = '', $autoload = false ) {
    if ( array_key_exists( $key, $GLOBALS['options'] ) ) { return false; }
    check( false === $autoload, 'Notification leases are not autoloaded' );
    $GLOBALS['options'][ $key ] = $value;
    return true;
}
class LockDB {
    public $options = 'wp_options';
    public function delete( $table, $where, $formats ) {
        check( $table === $this->options, 'Lock never writes order/post tables directly' );
        if ( ( $GLOBALS['options'][ $where['option_name'] ] ?? null ) === $where['option_value'] ) {
            unset( $GLOBALS['options'][ $where['option_name'] ] ); return 1;
        }
        return 0;
    }
}
$wpdb = new LockDB();
function as_has_scheduled_action( $hook, $args, $group ) {
    foreach ( $GLOBALS['scheduled'] ?? [] as $job ) {
        if ( $job['hook'] === $hook && $job['args'] === $args && $job['group'] === $group ) { return true; }
    }
    return false;
}
function as_enqueue_async_action( $hook, $args, $group, $unique = false ) {
    if ( $unique && as_has_scheduled_action( $hook, $args, $group ) ) { return 0; }
    $GLOBALS['scheduled'][] = compact( 'hook', 'args', 'group' ); return count( $GLOBALS['scheduled'] );
}
function as_schedule_single_action( $time, $hook, $args, $group ) {
    $GLOBALS['scheduled'][] = compact( 'time', 'hook', 'args', 'group' ); return count( $GLOBALS['scheduled'] );
}
class NotificationLogger {
    public function error( $message, $context ) { $GLOBALS['logs'][] = compact( 'message', 'context' ); }
    public function warning( $message, $context ) { $GLOBALS['logs'][] = compact( 'message', 'context' ); }
}
function wc_get_logger() { return new NotificationLogger(); }
class NotificationMailer {
    public function wrap_message( $heading, $body ) { return '<h1>' . $heading . '</h1>' . $body; }
    public function send( $email, $subject, $body, $headers ) {
        $GLOBALS['mail_attempts'][] = compact( 'email', 'subject', 'body', 'headers' );
        if ( ! empty( $GLOBALS['mail_exceptions'][ $email ] ) ) { throw new RuntimeException( 'Transport unavailable' ); }
        if ( ! empty( $GLOBALS['mail_failures'][ $email ] ) ) { --$GLOBALS['mail_failures'][ $email ]; return false; }
        $GLOBALS['accepted_mail'][] = compact( 'email', 'subject', 'body', 'headers' );
        return true;
    }
}
function WC() { return new class { public function mailer() { return new NotificationMailer(); } }; }

require __DIR__ . '/../includes/author-donations.php';
$GLOBALS['test_users'] = [
    100 => (object) [ 'user_email' => 'parent@example.test', 'display_name' => 'Main charity' ],
    200 => (object) [ 'user_email' => 'alice@example.test', 'display_name' => '<Alice & friends>' ],
    300 => (object) [ 'user_email' => 'bob@example.test', 'display_name' => 'Bob' ],
    400 => (object) [ 'user_email' => 'not-an-email', 'display_name' => 'Invalid' ],
    500 => false,
];
test_campaign( 10, 100, 'Main campaign' );
test_campaign( 20, 200, 'Alice <run>', 10 );
test_campaign( 21, 200, 'Alice swim', 10 );
test_campaign( 30, 300, 'Bob fundraiser', 10 );
test_campaign( 40, 400, 'Invalid email campaign' );
test_campaign( 50, 500, 'Deleted author campaign' );
test_campaign( 60, 200, 'Not a campaign', 0, 'page' );
$items = [ new DonationItem( 20, 10 ), new DonationItem( 20, 15 ), new DonationItem( 21, 7 ), new DonationItem( 30, 50 ), new DonationItem( 0, 500 ), new DonationItem( 999, 600 ), new DonationItem( 60, 700 ), new DonationItem( 40, 800 ), new DonationItem( 50, 900 ) ];
$order = test_order( 1, $items );
$groups = cm_author_donation_groups( $order );
check( array_keys( $groups ) === [ 200, 300 ], 'Only valid direct authors included; never parent authors or missing records' );
same_amount( $groups[200]['campaigns'][20]['amount'], 25, 'Repeated lines grouped by campaign' );
check( count( $groups[200]['campaigns'] ) === 2, 'Multiple campaigns grouped per author' );
same_amount( $groups[300]['campaigns'][30]['amount'], 50, 'Other author receives only own line amount' );

foreach ( [ 'pending', 'failed', 'cancelled', 'refunded', 'checkout-draft', 'trash' ] as $status ) {
    $order->state['status'] = $status; $GLOBALS['scheduled'] = [];
    cm_queue_author_donation_notification( 1 );
    check( empty( $GLOBALS['scheduled'] ), 'No notification for ' . $status );
}
$order->state['status'] = 'on-hold';
do_action( 'woocommerce_order_status_changed', 1, 'pending', 'on-hold' );
do_action( 'woocommerce_payment_complete', 1 );
check( count( $GLOBALS['scheduled'] ) === 1, 'Duplicate qualifying events queue one action' );
check( empty( $GLOBALS['accepted_mail'] ), 'Checkout queues mail without sending synchronously' );
$GLOBALS['options']['fxm_members_account_page_id'] = 99;
do_action( 'cm_send_author_donation_notifications', 1 );
check( count( $GLOBALS['accepted_mail'] ) === 2, 'One separate email per author' );
$alice = $GLOBALS['accepted_mail'][0]; $bob = $GLOBALS['accepted_mail'][1];
check( 'alice@example.test' === $alice['email'] && 'bob@example.test' === $bob['email'], 'Recipients automatically resolved' );
check( str_contains( $alice['body'], 'GBP25.00' ) && str_contains( $alice['body'], 'GBP7.00' ), 'Only own campaign amounts in order currency' );
check( ! str_contains( $alice['body'], 'Bob fundraiser' ) && ! str_contains( $alice['body'], 'GBP50.00' ) && ! str_contains( $alice['body'], 'GBP500.00' ), 'Other campaigns and non-donation purchases not disclosed' );
check( ! str_contains( $bob['body'], 'Alice' ), 'Author messages isolated' );
check( str_contains( $alice['body'], 'awaiting payment confirmation' ), 'On-hold is not described as paid' );
check( str_contains( $alice['body'], '&lt;Alice &amp; friends&gt;' ) && str_contains( $alice['body'], 'Alice &lt;run&gt;' ), 'Author and campaign HTML escaped' );
check( str_contains( $alice['body'], '#donations' ), 'Author account link provided' );
check( count( $order->state['meta']['_cm_author_donation_sent'] ) === 2, 'Accepted sends persisted through order CRUD' );
check( ! isset( $GLOBALS['options']['cm_author_mail_lock_1'] ), 'Worker lease released' );
$GLOBALS['scheduled'] = [];
$order->state['status'] = 'processing';
do_action( 'woocommerce_order_status_changed', 1, 'on-hold', 'processing' );
$order->state['status'] = 'completed';
do_action( 'woocommerce_order_status_changed', 1, 'processing', 'completed' );
check( empty( $GLOBALS['scheduled'] ), 'Status changes do not requeue successful authors' );
cm_send_author_donation_notifications( 1 );
check( count( $GLOBALS['accepted_mail'] ) === 2, 'Replayed worker does not resend accepted messages' );

// A failure for one author cannot resend the other author's accepted message.
$retry_order = test_order( 2, [ new DonationItem( 20, 12 ), new DonationItem( 30, 20 ) ] );
$GLOBALS['mail_failures']['bob@example.test'] = 2;
$GLOBALS['scheduled'] = [];
cm_send_author_donation_notifications( 2 );
check( isset( $retry_order->state['meta']['_cm_author_donation_sent'][200] ) && ! isset( $retry_order->state['meta']['_cm_author_donation_sent'][300] ), 'Only accepted recipient marked sent' );
check( count( $GLOBALS['scheduled'] ) === 1 && $GLOBALS['scheduled'][0]['time'] >= time() + 299, 'Failed recipient queued for five-minute retry' );
$mail_count = count( $GLOBALS['accepted_mail'] );
cm_send_author_donation_notifications( 2 );
check( count( $GLOBALS['accepted_mail'] ) === $mail_count, 'Second failure does not resend successful author' );
cm_send_author_donation_notifications( 2 );
check( count( $GLOBALS['accepted_mail'] ) === $mail_count + 1 && isset( $retry_order->state['meta']['_cm_author_donation_sent'][300] ), 'Failed author succeeds on third attempt' );
check( $retry_order->state['meta']['_cm_author_donation_attempts'][200] === 1, 'Successful recipient attempted once' );

$failed = test_order( 3, [ new DonationItem( 20, 12 ) ] );
$GLOBALS['mail_exceptions']['alice@example.test'] = true;
$GLOBALS['scheduled'] = [];
for ( $i = 0; $i < 4; ++$i ) { cm_send_author_donation_notifications( 3 ); }
check( $failed->state['meta']['_cm_author_donation_attempts'][200] === 3 && count( $failed->state['notes'] ) === 1, 'Exceptions stop after three attempts with an order note' );
check( count( $GLOBALS['scheduled'] ) === 2, 'Only two automatic retries' );
check( ! isset( $GLOBALS['options']['cm_author_mail_lock_3'] ), 'Exception releases worker lock' );
$GLOBALS['mail_exceptions'] = [];
$GLOBALS['caps']['edit_shop_orders'] = true;
do_action( 'woocommerce_order_action_cm_retry_author_notifications', $failed );
check( ! isset( $failed->state['meta']['_cm_author_donation_attempts'] ), 'Admin retry resets failure budget' );
cm_send_author_donation_notifications( 3 );
check( isset( $failed->state['meta']['_cm_author_donation_sent'][200] ), 'Manual retry recovers failed notification' );
$before = count( $GLOBALS['accepted_mail'] );
do_action( 'woocommerce_order_action_cm_retry_author_notifications', $retry_order );
cm_send_author_donation_notifications( 2 );
check( count( $GLOBALS['accepted_mail'] ) === $before, 'Manual retry retains successful-recipient markers' );

$cancelled = test_order( 4, [ new DonationItem( 20, 5 ) ] );
cm_queue_author_donation_notification( 4 );
$cancelled->state['status'] = 'cancelled';
cm_send_author_donation_notifications( 4 );
check( count( $GLOBALS['accepted_mail'] ) === $before, 'Order cancelled before processing sends nothing' );
$refund = test_order( 5, [ new DonationItem( 20, -5 ) ] ); $refund->state['type'] = 'shop_order_refund';
cm_send_author_donation_notifications( 5 );
check( count( $GLOBALS['accepted_mail'] ) === $before, 'Refund objects never send new donation notices' );
$ordinary = test_order( 6, [ new DonationItem( 0, 5 ) ] ); $GLOBALS['scheduled'] = [];
cm_queue_author_donation_notification( 6 );
check( empty( $GLOBALS['scheduled'] ), 'Ordinary orders do not schedule jobs' );

// Author resolution is fresh at send time and locks stop overlapping workers.
$moved = test_order( 7, [ new DonationItem( 20, 5 ) ] );
cm_queue_author_donation_notification( 7 );
$GLOBALS['campaigns'][20]->post_author = 300;
$lease = cm_author_notification_lock( 7 );
cm_send_author_donation_notifications( 7 );
check( count( $GLOBALS['accepted_mail'] ) === $before, 'Overlapping worker cannot send while lease held' );
cm_release_author_notification_lock( 7, $lease );
cm_send_author_donation_notifications( 7 );
check( end( $GLOBALS['accepted_mail'] )['email'] === 'bob@example.test', 'Current author resolved when job runs' );
$GLOBALS['options']['cm_author_mail_lock_8'] = ( time() - 601 ) . ':expired';
$new_lease = cm_author_notification_lock( 8 );
check( false !== $new_lease, 'Crashed-worker lease can be reclaimed' );
cm_release_author_notification_lock( 8, 'old-owner-token' );
check( isset( $GLOBALS['options']['cm_author_mail_lock_8'] ), 'Old worker cannot release a new lease' );
cm_release_author_notification_lock( 8, $new_lease );

echo 'PASS: ' . $GLOBALS['assertions'] . " author notification assertions; transport and scheduler mocked, no emails sent.\n";
