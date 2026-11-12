<?php
/** Goal visibility behavior, isolated from WordPress bootstrap and live payments. */
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce; }
function current_user_can( ...$args ) { return $GLOBALS['can_edit']; }
function esc_html__( $text, $domain = '' ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_attr__( $text, $domain = '' ) { return esc_html__( $text ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $text ) { return esc_attr( $text ); }
function wc_price( $amount ) { return 'EUR ' . number_format( $amount, 2 ); }
function cm_get_campaign_stats( $id ) { return [ 'total_raised' => $GLOBALS['raised'] ]; }
function wc_get_cart_url() { return '/cart'; }
function wc_get_product( $id ) { return new class { public function is_type( $type ) { return false; } }; }
function check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    $GLOBALS['assertions'] = ( $GLOBALS['assertions'] ?? 0 ) + 1;
}

require __DIR__ . '/../includes/meta.php';
// Load the production functions without executing the plugin's unrelated bootstrap hooks.
$source = file_get_contents( __DIR__ . '/../wp-charity.php' );
foreach ( [ 'cm_display_campaign_goal', 'cm_generate_donation_form' ] as $function ) {
    if ( ! preg_match( '/^function ' . $function . '\(.*?^}/ms', $source, $match ) ) {
        throw new RuntimeException( 'Production function not found: ' . $function );
    }
    eval( $match[0] );
}

$GLOBALS['meta'][1]['_donation_goal'] = 5000;
$GLOBALS['can_edit'] = true;
foreach ( [ '', '0', '1' ] as $setting ) {
    $GLOBALS['meta'][1]['_hide_goal_after_reached'] = $setting;
    foreach ( [ 4999, 5000, 6500, 4500 ] as $raised ) {
        $GLOBALS['raised'] = $raised;
        $html = cm_display_campaign_goal( 1 );
        $hidden = '1' === $setting && $raised >= 5000;
        check( str_contains( $html, wc_price( $raised ) ), 'Amount raised always remains visible.' );
        check( str_contains( $html, 'role="progressbar"' ) === ! $hidden, 'Progress visibility follows the exact goal threshold and opt-in.' );
        check( str_contains( $html, 'progress-percentage' ) === ! $hidden, 'Percentage follows progress visibility.' );
        check( str_contains( $html, wc_price( 5000 ) . ' goal' ) === ! $hidden, 'Target follows progress visibility.' );
        check( str_contains( $html, 'goal-reached' ) === ( ! $hidden && $raised >= 5000 ), 'Success message respects visibility.' );
        $form = cm_generate_donation_form( 123, 1 );
        check( str_contains( $form, '> Donate</button>' ) && ! str_contains( $form, 'disabled' ), 'Donation submission remains enabled.' );
        check( str_contains( $form, 'name="campaign_id" value="1"' ), 'Donations retain campaign attribution.' );
    }
}
$GLOBALS['raised'] = 6500;
$GLOBALS['meta'][2]['_donation_goal'] = 5000;
check( str_contains( cm_display_campaign_goal( 2 ), 'goal-reached' ), 'Another campaign does not inherit the opt-in.' );
$GLOBALS['meta'][1]['_donation_goal'] = 0;
check( '' === cm_display_campaign_goal( 1 ), 'Zero-goal campaigns retain their existing behavior.' );

$_POST = [ 'cm_unified_campaign_nonce' => 'valid', 'hide_goal_after_reached' => '1' ];
cm_save_unified_campaign_settings( 1 );
check( '1' === get_post_meta( 1, '_hide_goal_after_reached' ), 'Admin can enable the setting.' );
unset( $_POST['hide_goal_after_reached'] );
cm_save_unified_campaign_settings( 1 );
check( '0' === get_post_meta( 1, '_hide_goal_after_reached' ), 'Unchecking disables the setting.' );
$_POST['hide_goal_after_reached'] = '1';
$_POST['cm_unified_campaign_nonce'] = 'invalid';
cm_save_unified_campaign_settings( 1 );
check( '0' === get_post_meta( 1, '_hide_goal_after_reached' ), 'Invalid nonce cannot change visibility.' );
$_POST['cm_unified_campaign_nonce'] = 'valid';
$GLOBALS['can_edit'] = false;
cm_save_unified_campaign_settings( 1 );
check( '0' === get_post_meta( 1, '_hide_goal_after_reached' ), 'Unauthorized user cannot change visibility.' );
echo 'PASS: ' . $GLOBALS['assertions'] . " goal visibility assertions.\n";
