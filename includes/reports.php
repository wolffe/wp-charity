<?php
/** Paginated reports and streaming exports. */

function cm_report_row( $row, $whole_order = false ) {
    $order = wc_get_order( $row['order_id'] );
    if ( ! $order ) {
        return null;
    }
    $campaign_id = (int) $row['campaign_id'];
    $campaign = get_post( $campaign_id );
    $creator = $campaign ? get_userdata( $campaign->post_author ) : null;
    return [
        'order_id' => $order->get_id(),
        'order_number' => $order->get_order_number(),
        'order_url' => $order->get_edit_order_url(),
        'customer_name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
        'email' => $order->get_billing_email(),
        'customer_number' => $order->get_customer_id() ? (string) $order->get_customer_id() : '—',
        'amount' => $whole_order ? $order->get_total() : $row['amount'],
        'date' => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ) : '',
        'campaign_id' => $campaign_id,
        'campaign_name' => $campaign && 'campaign' === $campaign->post_type ? $campaign->post_title : __( '(Unknown campaign)', 'wp-charity' ),
        'campaign_creator_name' => $creator ? $creator->display_name : '—',
        'campaign_url' => get_permalink( $campaign_id ),
    ];
}

function cm_render_donations_report() {
    $page = max( 1, absint( $_GET['donations_page'] ?? 1 ) );
    $data = cm_donation_page( null, $page );
    $url = wp_nonce_url( admin_url( 'edit.php?post_type=campaign&page=fxm&tab=reports&export_donations_report=1' ), 'export_donations_report' );
    echo '<h2>' . esc_html__( 'Reports', 'wp-charity' ) . '</h2><h3>' . esc_html__( 'Donations report', 'wp-charity' ) . '</h3>';
    echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Export full report as CSV', 'wp-charity' ) . '</a></p>';
    echo '<div class="campaign-report"><table class="widefat"><thead><tr>';
    foreach ( [ __( 'Order #', 'wp-charity' ), __( 'Customer name', 'wp-charity' ), __( 'Email', 'wp-charity' ), __( 'Customer number', 'wp-charity' ), __( 'Amount', 'wp-charity' ), __( 'Date', 'wp-charity' ), __( 'Campaign', 'wp-charity' ), __( 'Campaign created by', 'wp-charity' ) ] as $heading ) {
        echo '<th>' . esc_html( $heading ) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ( $data['rows'] as $raw ) {
        $row = cm_report_row( $raw );
        if ( ! $row ) { continue; }
        echo '<tr><td><a href="' . esc_url( $row['order_url'] ) . '">' . esc_html( $row['order_number'] ) . '</a></td>';
        foreach ( [ 'customer_name', 'email', 'customer_number', 'amount', 'date', 'campaign_name', 'campaign_creator_name' ] as $field ) {
            echo '<td>' . ( 'amount' === $field ? wp_kses_post( wc_price( $row[ $field ] ) ) : esc_html( $row[ $field ] ) ) . ( 'campaign_name' === $field ? ' (ID: ' . (int) $row['campaign_id'] . ')' : '' ) . '</td>';
        }
        echo '</tr>';
    }
    if ( ! $data['rows'] ) { echo '<tr><td colspan="8">' . esc_html__( 'No donation data found.', 'wp-charity' ) . '</td></tr>'; }
    echo '</tbody></table></div>' . cm_render_page_links( $page, $data['more'], 'donations_page' );
}

function cm_render_campaign_report() {
    $page = max( 1, absint( $_GET['campaign_page'] ?? 1 ) );
    $query = new WP_Query( [ 'post_type' => 'campaign', 'post_status' => 'any', 'posts_per_page' => 20, 'paged' => $page, 'orderby' => [ 'date' => 'DESC', 'ID' => 'DESC' ] ] );
    $summaries = cm_campaign_summaries( wp_list_pluck( $query->posts, 'ID' ) );
    echo '<h3>' . esc_html__( 'Campaign Report', 'wp-charity' ) . '</h3><table class="widefat"><thead><tr>';
    foreach ( [ __( 'Campaign', 'wp-charity' ), __( 'Status', 'wp-charity' ), __( 'Goal', 'wp-charity' ), __( 'Total Raised', 'wp-charity' ), __( 'Orders', 'wp-charity' ) ] as $heading ) {
        echo '<th>' . esc_html( $heading ) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ( $query->posts as $campaign ) {
        $summary = $summaries[ $campaign->ID ];
        $goal = (float) get_post_meta( $campaign->ID, '_donation_goal', true );
        $status = get_post_status_object( $campaign->post_status );
        $export = wp_nonce_url( add_query_arg( [ 'export_campaign_orders' => 1, 'campaign_id' => $campaign->ID ], admin_url( 'edit.php?post_type=campaign&page=fxm&tab=help' ) ), 'export_campaign_orders_' . $campaign->ID );
        echo '<tr><td><a href="' . esc_url( get_edit_post_link( $campaign->ID ) ) . '">' . esc_html( $campaign->post_title ) . '</a>';
        if ( $campaign->post_parent ) { echo '<br><small>' . sprintf( esc_html__( 'Child of: %s', 'wp-charity' ), esc_html( get_the_title( $campaign->post_parent ) ) ) . '</small>'; }
        echo '</td><td>' . esc_html( $status ? $status->label : $campaign->post_status ) . '</td><td>' . ( $goal ? wp_kses_post( wc_price( $goal ) ) : esc_html__( 'No goal set', 'wp-charity' ) ) . '</td><td>' . wp_kses_post( wc_price( $summary['order_total'] ) );
        if ( $goal > 0 ) { echo '<br><small>' . sprintf( esc_html__( '%s%% of goal', 'wp-charity' ), round( min( 100, $summary['order_total'] / $goal * 100 ) ) ) . '</small>'; }
        echo '</td><td>' . (int) $summary['order_count'];
        if ( $summary['order_count'] ) {
            echo ' <button type="button" class="button cm-show-orders" aria-expanded="false" aria-controls="cm-orders-' . (int) $campaign->ID . '">' . esc_html__( 'View orders', 'wp-charity' ) . '</button> <a class="button" href="' . esc_url( $export ) . '">' . esc_html__( 'Export CSV', 'wp-charity' ) . '</a>';
            echo '<div id="cm-orders-' . (int) $campaign->ID . '" class="cm-remote-list" data-action="cm_campaign_orders" data-campaign="' . (int) $campaign->ID . '" data-nonce="' . esc_attr( wp_create_nonce( 'cm_report_data' ) ) . '" hidden></div>';
        }
        echo '</td></tr>';
    }
    if ( ! $query->posts ) { echo '<tr><td colspan="5">' . esc_html__( 'No campaigns found.', 'wp-charity' ) . '</td></tr>'; }
    echo '</tbody></table>' . cm_render_page_links( $page, $page < $query->max_num_pages, 'campaign_page' );
}

function cm_ajax_report_data() {
    if ( ! is_user_logged_in() ) { wp_send_json_error( [ 'message' => __( 'Please log in again.', 'wp-charity' ) ], 401 ); }
    if ( ! check_ajax_referer( 'cm_report_data', 'nonce', false ) ) { wp_send_json_error( [ 'message' => __( 'Please reload the page and try again.', 'wp-charity' ) ], 403 ); }
    $admin = 'cm_campaign_orders' === ( $_POST['action'] ?? '' );
    if ( $admin && ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => __( 'Access denied.', 'wp-charity' ) ], 403 ); }
    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
    if ( $admin && ( ! $campaign_id || 'campaign' !== get_post_type( $campaign_id ) ) ) { wp_send_json_error( [ 'message' => __( 'Invalid campaign.', 'wp-charity' ) ], 400 ); }
    try {
        $data = cm_donation_page( $admin ? [ $campaign_id ] : null, max( 1, absint( $_POST['page'] ?? 1 ) ), 20, $admin, $admin ? 0 : get_current_user_id() );
        $html = '';
        foreach ( $data['rows'] as $raw ) {
            if ( $admin ) { $raw['campaign_id'] = $campaign_id; }
            $row = cm_report_row( $raw, $admin );
            if ( ! $row ) { continue; }
            if ( $admin ) {
                $html .= '<p><a href="' . esc_url( $row['order_url'] ) . '">' . sprintf( esc_html__( 'Order #%s', 'wp-charity' ), esc_html( $row['order_number'] ) ) . '</a> — ' . wp_kses_post( wc_price( $row['amount'] ) ) . '</p>';
            } else {
                $html .= '<div class="fxm--box"><p><strong>' . wp_kses_post( wc_price( $row['amount'] ) ) . '</strong> <small>' . esc_html( $row['date'] ) . '</small></p><p>' . esc_html( $row['customer_name'] ?: __( 'Guest', 'wp-charity' ) ) . ' · ' . esc_html( $row['email'] ) . '</p><a href="' . esc_url( $row['campaign_url'] ) . '">' . esc_html( $row['campaign_name'] ) . '</a></div>';
            }
        }
        wp_send_json_success( [ 'html' => $html ?: '<p>' . esc_html__( 'No donations found.', 'wp-charity' ) . '</p>', 'page' => $data['page'], 'more' => $data['more'] ] );
    } catch ( RuntimeException $error ) {
        wp_send_json_error( [ 'message' => __( 'Donations could not be loaded. Please try again.', 'wp-charity' ) ], 500 );
    }
}
add_action( 'wp_ajax_cm_campaign_orders', 'cm_ajax_report_data' );
add_action( 'wp_ajax_cm_account_donations', 'cm_ajax_report_data' );

function fxm_handle_csv_export() {
    if ( 'fxm' !== ( $_GET['page'] ?? '' ) || ( ! isset( $_GET['export_campaign_orders'] ) && ! isset( $_GET['export_donations_report'] ) ) ) { return; }
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'wp-charity' ) ); }
    $campaign_id = isset( $_GET['export_campaign_orders'] ) ? absint( $_GET['campaign_id'] ?? 0 ) : 0;
    check_admin_referer( isset( $_GET['export_campaign_orders'] ) ? 'export_campaign_orders_' . $campaign_id : 'export_donations_report' );
    if ( isset( $_GET['export_campaign_orders'] ) && ( ! $campaign_id || 'campaign' !== get_post_type( $campaign_id ) ) ) { wp_die( esc_html__( 'Invalid campaign.', 'wp-charity' ) ); }
    cm_stream_donations_csv( $campaign_id );
}
add_action( 'admin_init', 'fxm_handle_csv_export', 1 );

function cm_stream_donations_csv( $campaign_id = 0 ) {
    $whole = $campaign_id > 0;
    $filename = $whole ? 'campaign-orders-' . sanitize_file_name( get_the_title( $campaign_id ) ) : 'donations-report';
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
    $output = fopen( 'php://output', 'w' );
    fwrite( $output, "\xEF\xBB\xBF" );
    $headers = $whole ? [ 'Order ID', 'Name', 'Email', 'Amount', 'Date of Donation', 'Campaign ID', 'Campaign Name', 'Campaign URL' ] : [ 'Order #', 'Customer name', 'Email', 'Customer number', 'Amount', 'Date', 'Campaign ID', 'Campaign name', 'Campaign created by' ];
    fputcsv( $output, $headers, ',', '"', '' );
    // Export records are yielded in batches; never accumulate the full report in memory.
    $was_suspended = wp_suspend_cache_addition( true );
    foreach ( cm_donation_export_rows( $campaign_id ) as $row ) {
        fputcsv( $output, $row, ',', '"', '' );
    }
    wp_suspend_cache_addition( $was_suspended );
    fclose( $output );
    exit;
}

function cm_donation_export_rows( $campaign_id = 0 ) {
    $whole = $campaign_id > 0;
    $page = 1;
    do {
        $data = cm_donation_page( $whole ? [ $campaign_id ] : null, $page++, 200, $whole );
        foreach ( $data['rows'] as $raw ) {
            if ( $whole ) { $raw['campaign_id'] = $campaign_id; }
            $row = cm_report_row( $raw, $whole );
            if ( ! $row ) { continue; }
            $fields = $whole ? [ 'order_id', 'customer_name', 'email', 'amount', 'date', 'campaign_id', 'campaign_name', 'campaign_url' ] : [ 'order_number', 'customer_name', 'email', 'customer_number', 'amount', 'date', 'campaign_id', 'campaign_name', 'campaign_creator_name' ];
            yield array_map( function ( $field ) use ( $row ) { return $row[ $field ]; }, $fields );
        }
    } while ( $data['more'] );
}
