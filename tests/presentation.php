<?php
/** Focused checks for campaign presentation markup and admin badges. */

$GLOBALS['hooks'] = [];
$GLOBALS['post_meta'] = [];

function add_action( $name, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['hooks'][ $name ][] = [ $callback, $accepted ]; }
function add_filter( $name, $callback, $priority = 10, $accepted = 1 ) { add_action( $name, $callback, $priority, $accepted ); }
function __( $value, $domain = '' ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_attr( $value ) { return esc_html( $value ); }
function get_post_meta( $post_id, $key, $single = true ) { return $GLOBALS['post_meta'][ $post_id ][ $key ] ?? ''; }
function get_post( $post_id ) { return $GLOBALS['posts'][ $post_id ] ?? null; }
function check( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    $GLOBALS['assertions'] = ( $GLOBALS['assertions'] ?? 0 ) + 1;
}

require __DIR__ . '/../includes/meta.php';

$parent = (object) [ 'ID' => 10, 'post_type' => 'campaign', 'post_parent' => 0 ];
$child  = (object) [ 'ID' => 11, 'post_type' => 'campaign', 'post_parent' => 10 ];
$page   = (object) [ 'ID' => 12, 'post_type' => 'page', 'post_parent' => 0 ];
$GLOBALS['posts'] = [ 10 => $parent, 11 => $child, 12 => $page ];

check( [ 'parent' => 'Parent' ] === cm_campaign_admin_badges( $parent ), 'A top-level campaign receives the Parent badge.' );

$GLOBALS['post_meta'][10]['_allow_volunteer_campaigns'] = '1';
check(
    [ 'parent' => 'Parent', 'volunteers' => 'Allow Volunteer Campaigns' ] === cm_campaign_admin_badges( $parent ),
    'An enabled parent receives both campaign badges.'
);

check( [ 'volunteer' => 'Volunteer' ] === cm_campaign_admin_badges( $child ), 'Every child campaign receives the Volunteer badge.' );
$GLOBALS['post_meta'][11]['_allow_volunteer_campaigns'] = '1';
check(
    [ 'volunteer' => 'Volunteer', 'volunteers' => 'Allow Volunteer Campaigns' ] === cm_campaign_admin_badges( $child ),
    'Volunteer meta is represented independently of hierarchy.'
);
check( [] === cm_campaign_admin_badges( $page ), 'Badges are limited to campaigns.' );

$columns = cm_campaign_columns( [ 'cb' => 'Select', 'title' => 'Title', 'date' => 'Date' ] );
check( [ 'cb', 'title', 'campaign_type', 'total_donated', 'date' ] === array_keys( $columns ), 'Campaign type and totals follow the title column.' );

ob_start();
cm_campaign_column_badges( 'campaign_type', 11 );
$badge_html = ob_get_clean();
check( str_contains( $badge_html, 'cm-campaign-badge--volunteer' ), 'Campaign type column renders the Volunteer badge.' );
check( str_contains( $badge_html, 'cm-campaign-badge--volunteers' ), 'Campaign type column renders the volunteer-enabled badge.' );

$plugin = file_get_contents( __DIR__ . '/../wp-charity.php' );
$css    = file_get_contents( __DIR__ . '/../assets/css/fxm-members.css' );
$admin_css = file_get_contents( __DIR__ . '/../assets/css/fxm-admin.css' );
$readme = file_get_contents( __DIR__ . '/../readme.txt' );
$icon_markup = $plugin . file_get_contents( __DIR__ . '/../includes/fxm-users.php' ) . file_get_contents( __DIR__ . '/../includes/shortcodes.php' );

check( str_contains( $plugin, 'class="fxm-campaign-page"' ), 'Campaign output uses the modern scoped wrapper.' );
check( str_contains( $plugin, '$rendered_campaign_content = $content;' ), 'Campaign output captures content after WordPress renders blocks, embeds and shortcodes.' );
check( str_contains( $plugin, "add_filter( 'the_content', 'cm_display_product_on_child_campaign', 20 );" ), 'Campaign layout runs after the standard content-rendering filters.' );
check( ! str_contains( $plugin, 'wpautop( wp_kses_post( $post->post_content ) )' ), 'Campaign stories are not reconstructed from raw post content.' );
check( str_contains( $plugin, 'wp_oembed_get( $youtube_url )' ), 'The dedicated campaign video field retains its oEmbed path.' );
check( str_contains( $plugin, '* Version: 1.2.6' ) && str_contains( $plugin, '* Requires at least: 7.1' ) && str_contains( $readme, 'Stable tag: 1.2.6' ), 'Release and minimum WordPress metadata are synchronized.' );
check( str_contains( $plugin, 'role="progressbar"' ) && str_contains( $plugin, 'aria-valuenow=' ), 'Fundraising progress exposes accessible values.' );
check( ! str_contains( $plugin, 'class="donation-status' ), 'Recent donations omit WooCommerce order-status labels.' );
check( substr_count( $plugin, 'name="campaign_id"' ) >= 2, 'Simple and variable donation forms retain campaign attribution.' );
check( str_contains( $plugin, "add_filter( 'woocommerce_add_cart_item_data', 'cm_store_campaign_id_in_cart_item'" ), 'Cart attribution hook remains registered.' );
check( str_contains( $plugin, "add_action( 'woocommerce_checkout_create_order_line_item', 'cm_save_campaign_id_as_order_meta'" ), 'Order-line attribution hook remains registered.' );
check( str_contains( $css, '.fxm-campaign-layout' ) && str_contains( $css, '@media (max-width: 900px)' ), 'Campaign layout has a responsive single-column breakpoint.' );
check( str_contains( $css, '.fxm--campaign-story .wp-block-embed__wrapper iframe' ) && str_contains( $css, 'aspect-ratio: 16 / 9;' ), 'Campaign-story video embeds are responsive.' );
check( str_contains( $css, '@media (prefers-reduced-motion: reduce)' ), 'Campaign presentation honors reduced-motion preferences.' );
preg_match_all( '/<i class="([^"]*\bai-[^"]*)"/', $icon_markup, $akar_icons );
check( 17 === count( $akar_icons[1] ), 'All expected Akar icon markup is covered by the presentation check.' );
check( [] === array_filter( $akar_icons[1], static fn( $classes ) => ! preg_match( '/(?:^|\s)ai-fw(?:\s|$)/', $classes ) ), 'Akar icons displayed with text use the fixed-width class.' );
check( str_contains( $admin_css, 'body.post-type-campaign .column-campaign_type .cm-campaign-badge' ), 'Admin badge styling is scoped to the campaign type column.' );
check( str_contains( $admin_css, '.cm-campaign-badge--parent' ) && str_contains( $admin_css, '.cm-campaign-badge--volunteer' ) && str_contains( $admin_css, '.cm-campaign-badge--volunteers' ), 'All admin badge variants have styles.' );

echo 'PASS: ' . $GLOBALS['assertions'] . " presentation assertions.\n";
