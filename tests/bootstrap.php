<?php
/** Isolated regression harness: real SQL on SQLite, minimal WP/WC API doubles. */
namespace Automattic\WooCommerce\Utilities {
    class OrderUtil {
        public static $hpos = true;
        public static function custom_orders_table_usage_is_enabled() { return self::$hpos; }
    }
}
namespace {
    define( 'ARRAY_A', 'ARRAY_A' );
    define( 'HOUR_IN_SECONDS', 3600 );
    define( 'ABSPATH', __DIR__ . '/' );
    $GLOBALS['hooks'] = [];
    $GLOBALS['cache'] = [];
    $GLOBALS['options'] = [];
    $GLOBALS['user_id'] = 42;
    $GLOBALS['admin'] = false;
    $GLOBALS['caps'] = [ 'edit_campaigns' => true, 'edit_post' => true ];
    function add_action( $name, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['hooks'][ $name ][] = [ $callback, $accepted ]; }
    function add_filter( $name, $callback, $priority = 10, $accepted = 1 ) { add_action( $name, $callback, $priority, $accepted ); }
    function do_action( $name, ...$args ) { foreach ( $GLOBALS['hooks'][ $name ] ?? [] as [ $cb, $accepted ] ) { $cb( ...array_slice( $args, 0, $accepted ) ); } }
    function absint( $v ) { return abs( (int) $v ); }
    function __( $s, $domain = '' ) { return $s; }
    function esc_html__( $s, $domain = '' ) { return htmlspecialchars( $s, ENT_QUOTES ); }
    function esc_attr__( $s, $domain = '' ) { return esc_html__( $s ); }
    function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
    function esc_attr( $s ) { return esc_html( $s ); }
    function esc_url( $s ) { return esc_html( $s ); }
    function esc_textarea( $s ) { return esc_html( $s ); }
    function esc_url_raw( $s ) { return $s; }
    function wp_kses_post( $s ) { return $s; }
    function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
    function wp_unslash( $s ) { return stripslashes( $s ); }
    function sanitize_text_field( $s ) { return trim( strip_tags( $s ) ); }
    function sanitize_textarea_field( $s ) { return trim( strip_tags( $s ) ); }
    function sanitize_email( $s ) { return filter_var( $s, FILTER_SANITIZE_EMAIL ); }
    function is_email( $s ) { return filter_var( $s, FILTER_VALIDATE_EMAIL ); }
    function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce; }
    function wp_create_nonce( $action ) { return 'valid'; }
    function check_ajax_referer( $action, $key, $die ) { return 'valid' === ( $_POST[ $key ] ?? '' ); }
    function check_admin_referer( $action ) { if ( 'valid' !== ( $_GET['_wpnonce'] ?? '' ) ) { wp_die( 'Invalid nonce' ); } }
    function get_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
    function set_transient( $key, $value, $ttl ) { $GLOBALS['cache'][ $key ] = $value; }
    function delete_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); }
    function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
    function get_current_user_id() { return $GLOBALS['user_id']; }
    function is_user_logged_in() { return get_current_user_id() > 0; }
    function current_user_can( $cap, ...$args ) { return 'manage_options' === $cap ? $GLOBALS['admin'] : ( $GLOBALS['caps'][ $cap ] ?? false ); }
    function wp_list_pluck( $rows, $field ) { return array_map( function ( $r ) use ( $field ) { return is_object( $r ) ? $r->$field : $r[ $field ]; }, $rows ); }
    function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['post_meta'][ $id ][ $key ] ?? ''; }
    function cm_campaign_allows_volunteers( $id ) { return '1' === get_post_meta( $id, '_allow_volunteer_campaigns' ); }
    function get_post( $id ) { return $GLOBALS['campaigns'][ $id ] ?? null; }
    function get_post_type( $id ) { $p = get_post( $id ); return $p ? $p->post_type : ''; }
    function get_post_status( $id ) { $p = get_post( $id ); return $p ? $p->post_status : ''; }
    function get_post_ancestors( $id ) { return []; }
    function get_posts( $args ) {
        return array_values( array_filter( $GLOBALS['campaigns'], function ( $p ) use ( $args ) {
            return ( ! isset( $args['post_parent__in'] ) || in_array( $p->post_parent, $args['post_parent__in'], true ) ) && ( ! isset( $args['post_status'] ) || in_array( $p->post_status, (array) $args['post_status'], true ) );
        } ) );
    }
    function get_permalink( $id ) { return 'https://example.test/campaign/' . $id; }
    function get_the_title( $id ) { $p = get_post( $id ); return $p ? $p->post_title : ''; }
    function get_userdata( $id ) { return array_key_exists( $id, $GLOBALS['test_users'] ?? [] ) ? $GLOBALS['test_users'][ $id ] : (object) [ 'ID' => $id, 'display_name' => 'Volunteer ' . $id, 'first_name' => 'Alex', 'last_name' => 'Test' ]; }
    function get_the_author_meta( $key, $id ) { return 'alex@example.test'; }
    function get_user_meta( $id, $key, $single = true ) { return ''; }
    function get_bloginfo( $key ) { return 'Test charity'; }
    function get_avatar_url( $id, $args ) { return 'https://example.test/avatar.png'; }
    function wp_logout_url( $url ) { return $url; }
    function home_url() { return 'https://example.test/'; }
    function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
    function wc_price( $v, $args = [] ) { return ( $args['currency'] ?? '€' ) . number_format( (float) $v, 2 ); }
    function wp_update_user( $data ) { $GLOBALS['updated_user'] = $data; return $data['ID']; }
    function wp_insert_user( $data ) { $GLOBALS['inserted_user'] = $data; return 100; }
    function wp_send_new_user_notifications( $id, $to ) {}
    function update_user_meta( $id, $key, $value ) { $GLOBALS['updated_meta'][ $key ] = $value; }
    function email_exists( $email ) { return 'used@example.test' === $email ? 99 : 0; }
    function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=valid'; }
    function add_query_arg( $key, $value = null, $url = '' ) { return ( $url ?: 'https://example.test/account' ) . '?' . http_build_query( is_array( $key ) ? $key : [ $key => $value ] ); }
    function is_wp_error( $v ) { return $v instanceof WP_Error; }
    class WP_Error { private $code; public function __construct( $code = 'error' ) { $this->code = $code; } public function get_error_message() { return 'Error'; } public function get_error_code() { return $this->code; } }
    function is_ssl() { return true; }
    function wp_signon( $creds, $secure ) { $GLOBALS['login_creds'] = $creds; return new WP_Error(); }
    function retrieve_password( $login ) { $GLOBALS['reset_login'] = $login; return $GLOBALS['reset_result'] ?? true; }
    class JsonStop extends \Exception { public $payload; public $success; public function __construct( $payload, $success ) { $this->payload = $payload; $this->success = $success; parent::__construct(); } }
    function wp_send_json_error( $data, $status = 400 ) { throw new JsonStop( $data, false ); }
    function wp_send_json_success( $data ) { throw new JsonStop( $data, true ); }
    function wp_die( $message ) { throw new \RuntimeException( $message ); }
    class WP_Query {
        public $posts = []; public $max_num_pages = 1;
        public function __construct( $args ) { $GLOBALS['last_campaign_query'] = $args; }
    }
    class TestDB {
        public $prefix = 'wp_'; public $posts = 'wp_posts'; public $postmeta = 'wp_postmeta'; public $last_error = ''; public $queries = []; public $db;
        public function __construct() { $this->db = new \PDO( 'sqlite::memory:' ); $this->db->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION ); }
        public function prepare( $sql, ...$args ) {
            if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
            return preg_replace_callback( '/%[ds]/', function ( $m ) use ( &$args ) { $value = array_shift( $args ); return '%d' === $m[0] ? (string) (int) $value : $this->db->quote( $value ); }, $sql );
        }
        public function get_results( $sql, $mode = null ) { $this->queries[] = $sql; return $this->db->query( $sql )->fetchAll( \PDO::FETCH_ASSOC ); }
        public function get_col( $sql ) { $this->queries[] = $sql; return $this->db->query( $sql )->fetchAll( \PDO::FETCH_COLUMN ); }
        public function get_var( $sql ) { $this->queries[] = $sql; return $this->db->query( $sql )->fetchColumn(); }
    }
    class TestOrder {
        private $id;
        public function __construct( $id ) { $this->id = $id; }
        public function get_id() { return $this->id; }
        public function get_order_number() { return 'DON-' . $this->id; }
        public function get_edit_order_url() { return admin_url( 'admin.php?page=wc-orders&id=' . $this->id ); }
        public function get_billing_first_name() { return 'Donor'; }
        public function get_billing_last_name() { return (string) $this->id; }
        public function get_billing_email() { return 'donor' . $this->id . '@example.test'; }
        public function get_customer_id() { return 0; }
        public function get_total() { return $GLOBALS['orders'][ $this->id ]['total']; }
        public function get_date_created() { return new class { public function date_i18n( $format ) { return '2026-10-08 12:00:00'; } }; }
    }
    function wc_get_order( $id ) { $GLOBALS['order_loads'] = ( $GLOBALS['order_loads'] ?? 0 ) + 1; return $GLOBALS['test_order_objects'][ $id ] ?? ( isset( $GLOBALS['orders'][ $id ] ) ? new TestOrder( $id ) : false ); }
    function check( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } $GLOBALS['assertions'] = ( $GLOBALS['assertions'] ?? 0 ) + 1; }
    function same_amount( $a, $b, $message ) { check( abs( (float) $a - (float) $b ) < 0.000001, $message . ': ' . $a . ' vs ' . $b ); }
    function json_call( $callback ) { try { $callback(); } catch ( JsonStop $stop ) { return $stop; } throw new \RuntimeException( 'Missing JSON response' ); }
}
