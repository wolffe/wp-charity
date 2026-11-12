<?php
/** Campaign reporting. Reads only the authoritative order store; writes use WC CRUD. */

function cm_donation_sql( $campaign_ids = null, $author_id = 0 ) {
    global $wpdb;
    $hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    $orders = $hpos ? "{$wpdb->prefix}wc_orders" : $wpdb->posts;
    $id = $hpos ? 'o.id' : 'o.ID';
    $status = $hpos ? 'o.status' : 'o.post_status';
    $type = $hpos ? 'o.type' : 'o.post_type';
    $date = $hpos ? 'o.date_created_gmt' : 'o.post_date_gmt';
    $total = $hpos ? 'o.total_amount' : "COALESCE((SELECT om.meta_value FROM {$wpdb->postmeta} om WHERE om.post_id = o.ID AND om.meta_key = '_order_total' ORDER BY om.meta_id LIMIT 1), 0)";
    // The first value mirrors get_meta(..., true), without multiplying duplicate metadata.
    $campaign = 'cm.meta_value';
    $line = "COALESCE((SELECT lm.meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta lm WHERE lm.order_item_id = i.order_item_id AND lm.meta_key = '_line_total' ORDER BY lm.meta_id LIMIT 1), 0)";
    $from = "FROM {$wpdb->prefix}woocommerce_order_itemmeta cm JOIN {$wpdb->prefix}woocommerce_order_items i ON i.order_item_id = cm.order_item_id JOIN $orders o ON $id = i.order_id";
    $where = $wpdb->prepare( "WHERE cm.meta_key = %s AND cm.meta_id = (SELECT MIN(first_meta.meta_id) FROM {$wpdb->prefix}woocommerce_order_itemmeta first_meta WHERE first_meta.order_item_id = i.order_item_id AND first_meta.meta_key = '_campaign_id') AND i.order_item_type = %s AND $type = %s AND $status IN (%s, %s, %s) AND CAST($campaign AS UNSIGNED) > 0", '_campaign_id', 'line_item', 'shop_order', 'wc-completed', 'wc-processing', 'wc-on-hold' );
    if ( null !== $campaign_ids ) {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $campaign_ids ) ) ) );
        $where .= $ids ? $wpdb->prepare( ' AND CAST(' . $campaign . ' AS UNSIGNED) IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', $ids ) : ' AND 1=0';
    }
    if ( $author_id ) {
        $where .= $wpdb->prepare( " AND EXISTS (SELECT 1 FROM {$wpdb->posts} c WHERE c.ID = CAST($campaign AS UNSIGNED) AND c.post_type = 'campaign' AND c.post_author = %d AND c.post_status IN ('publish', 'draft'))", $author_id );
    }
    return compact( 'from', 'where', 'id', 'status', 'date', 'total', 'campaign', 'line' );
}

/** Compact per-campaign summaries, never order objects or donor data. */
function cm_campaign_summaries( $ids ) {
    global $wpdb;
    $result = [];
    $missing = [];
    foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
        $cached = get_transient( cm_summary_key( $id ) );
        if ( false === $cached ) {
            $missing[] = $id;
        } else {
            $result[ $id ] = $cached;
        }
    }
    if ( $missing ) {
        $s = cm_donation_sql( $missing );
        // Group by campaign AND order before summing whole orders (never SUM DISTINCT amount).
        $sql = "SELECT campaign_id, SUM(order_total) AS order_total, SUM(line_total) AS line_total, COUNT(*) AS order_count FROM (
            SELECT CAST({$s['campaign']} AS UNSIGNED) AS campaign_id, {$s['id']} AS order_id,
            MAX(CAST({$s['total']} AS DECIMAL(26,8))) AS order_total, SUM(CAST({$s['line']} AS DECIMAL(26,8))) AS line_total
            {$s['from']} {$s['where']} GROUP BY campaign_id, {$s['id']}
        ) donations GROUP BY campaign_id";
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( $wpdb->last_error ) {
            throw new RuntimeException( 'Could not read donation totals.' );
        }
        foreach ( $missing as $id ) {
            $result[ $id ] = [ 'order_total' => 0.0, 'line_total' => 0.0, 'order_count' => 0 ];
        }
        foreach ( $rows as $row ) {
            $result[ (int) $row['campaign_id'] ] = [ 'order_total' => (float) $row['order_total'], 'line_total' => (float) $row['line_total'], 'order_count' => (int) $row['order_count'] ];
        }
        foreach ( $missing as $id ) {
            set_transient( cm_summary_key( $id ), $result[ $id ], 12 * HOUR_IN_SECONDS );
        }
    }
    return $result;
}

/** Batch list totals, including published/draft direct children only when enabled. */
function cm_campaign_list_totals( $ids ) {
    $parents = array_values( array_filter( $ids, 'cm_campaign_allows_volunteers' ) );
    $children = $parents ? get_posts( [ 'post_type' => 'campaign', 'post_parent__in' => $parents, 'post_status' => [ 'publish', 'draft' ], 'numberposts' => -1 ] ) : [];
    $summaries = cm_campaign_summaries( array_merge( $ids, wp_list_pluck( $children, 'ID' ) ) );
    $totals = [];
    foreach ( $ids as $id ) {
        $totals[ $id ] = $summaries[ $id ]['line_total'];
    }
    foreach ( $children as $child ) {
        $totals[ $child->post_parent ] += $summaries[ $child->ID ]['line_total'];
    }
    return $totals;
}

/** Bounded pages; one extra row determines whether another page exists. */
function cm_donation_page( $ids = null, $page = 1, $size = 20, $whole_orders = false, $author_id = 0 ) {
    global $wpdb;
    $page = max( 1, (int) $page );
    $size = max( 1, min( 200, (int) $size ) );
    $s = cm_donation_sql( $ids, $author_id );
    $fields = $whole_orders ? "DISTINCT {$s['id']} AS order_id, {$s['date']} AS created" : "i.order_item_id AS item_id, {$s['id']} AS order_id, CAST({$s['campaign']} AS UNSIGNED) AS campaign_id, {$s['line']} AS amount, {$s['date']} AS created";
    $sort = $whole_orders ? 'order_id DESC' : 'order_id DESC, item_id DESC';
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT $fields {$s['from']} {$s['where']} ORDER BY created DESC, $sort LIMIT %d OFFSET %d", $size + 1, ( $page - 1 ) * $size ), ARRAY_A );
    if ( $wpdb->last_error ) {
        throw new RuntimeException( 'Could not read donations.' );
    }
    $more = count( $rows ) > $size;
    return [ 'rows' => array_slice( $rows, 0, $size ), 'more' => $more, 'page' => $page ];
}

function cm_clear_campaign_summaries( $ids ) {
    unset( $GLOBALS['cm_list_totals'] );
    foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
        delete_transient( 'cm_summary_v2_hpos_' . $id );
        delete_transient( 'cm_summary_v2_posts_' . $id );
        delete_transient( 'cm_campaign_stats_' . $id );
    }
}

function cm_summary_key( $id ) {
    return 'cm_summary_v2_' . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'hpos_' : 'posts_' ) . (int) $id;
}

/** Read persisted associations, including before a line is deleted or reassigned. */
function cm_order_campaign_ids( $order_id ) {
    global $wpdb;
    return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta m JOIN {$wpdb->prefix}woocommerce_order_items i ON i.order_item_id = m.order_item_id WHERE i.order_id = %d AND m.meta_key = '_campaign_id'", $order_id ) );
}

function cm_capture_order_campaigns( $order_id ) {
    $order_id = is_object( $order_id ) ? $order_id->get_id() : (int) $order_id;
    $ids = cm_order_campaign_ids( $order_id );
    $GLOBALS['cm_changed_campaigns'][ $order_id ] = array_unique( array_merge( $GLOBALS['cm_changed_campaigns'][ $order_id ] ?? [], $ids ) );
    cm_clear_campaign_summaries( $ids );
}

function cm_refresh_order_campaigns( $order_id ) {
    $order_id = is_object( $order_id ) ? $order_id->get_id() : (int) $order_id;
    cm_clear_campaign_summaries( array_merge( $GLOBALS['cm_changed_campaigns'][ $order_id ] ?? [], cm_order_campaign_ids( $order_id ) ) );
}

function cm_capture_item_campaigns( $item_id ) {
    global $wpdb;
    $order_id = is_object( $item_id ) ? $item_id->get_order_id() : (int) $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $item_id ) );
    if ( $order_id ) {
        $GLOBALS['cm_item_orders'][ is_object( $item_id ) ? $item_id->get_id() : $item_id ] = $order_id;
        cm_capture_order_campaigns( $order_id );
    }
}

function cm_refresh_item_campaigns( $item_id, $item = null, $order_id = 0 ) {
    global $wpdb;
    if ( is_object( $item_id ) ) {
        $order_id = $item_id->get_order_id();
        $item_id = $item_id->get_id();
    }
    $order_id = $order_id ?: ( $GLOBALS['cm_item_orders'][ $item_id ] ?? (int) $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $item_id ) ) );
    if ( $order_id ) {
        cm_refresh_order_campaigns( $order_id );
    }
}

foreach ( [ 'woocommerce_before_order_object_save', 'woocommerce_before_delete_order', 'woocommerce_before_trash_order' ] as $hook ) {
    add_action( $hook, 'cm_capture_order_campaigns' );
}
// Classic order deletion can originate from WordPress's own post actions.
foreach ( [ 'before_delete_post', 'wp_trash_post', 'untrash_post' ] as $hook ) {
    add_action( $hook, function ( $id ) {
        if ( 'shop_order' === get_post_type( $id ) ) { cm_capture_order_campaigns( $id ); }
    } );
}
foreach ( [ 'deleted_post', 'trashed_post', 'untrashed_post' ] as $hook ) {
    add_action( $hook, function ( $id ) {
        if ( isset( $GLOBALS['cm_changed_campaigns'][ $id ] ) ) { cm_refresh_order_campaigns( $id ); }
    } );
}
foreach ( [ 'woocommerce_after_order_object_save', 'woocommerce_order_status_changed', 'woocommerce_delete_order', 'woocommerce_trash_order', 'woocommerce_untrash_order', 'woocommerce_removed_order_items' ] as $hook ) {
    add_action( $hook, 'cm_refresh_order_campaigns' );
}
foreach ( [ 'woocommerce_before_order_item_object_save', 'woocommerce_before_delete_order_item' ] as $hook ) {
    add_action( $hook, 'cm_capture_item_campaigns' );
}
foreach ( [ 'woocommerce_after_order_item_object_save', 'woocommerce_new_order_item', 'woocommerce_update_order_item', 'woocommerce_delete_order_item' ] as $hook ) {
    add_action( $hook, 'cm_refresh_item_campaigns', 10, 3 );
}
// Raw item metadata updates are also used by the WooCommerce order editor.
foreach ( [ 'update', 'delete' ] as $operation ) {
    add_action( $operation . '_order_item_meta', function ( $meta_ids, $item_id, $key ) {
        if ( in_array( $key, [ '_campaign_id', '_line_total' ], true ) ) { cm_capture_item_campaigns( $item_id ); }
    }, 10, 3 );
}
foreach ( [ 'added', 'updated', 'deleted' ] as $operation ) {
    add_action( $operation . '_order_item_meta', function ( $meta_ids, $item_id, $key ) {
        if ( in_array( $key, [ '_campaign_id', '_line_total' ], true ) ) { cm_refresh_item_campaigns( $item_id ); }
    }, 10, 3 );
}
// Parent rollups are composed at read time; only the request-local list needs clearing.
add_action( 'save_post_campaign', function () { unset( $GLOBALS['cm_list_totals'] ); }, 100 );
add_action( 'deleted_post', function () { unset( $GLOBALS['cm_list_totals'] ); } );
foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
    add_action( $hook, function ( $meta_id, $post_id, $key ) {
        if ( '_allow_volunteer_campaigns' === $key ) { unset( $GLOBALS['cm_list_totals'] ); }
    }, 10, 3 );
}
// Clear again at request end if another callback read totals between related writes.
add_action( 'shutdown', function () {
    foreach ( array_keys( $GLOBALS['cm_changed_campaigns'] ?? [] ) as $order_id ) {
        cm_refresh_order_campaigns( $order_id );
    }
} );

function cm_render_page_links( $page, $more, $parameter, $fragment = '' ) {
    $out = '<nav class="cm-pagination" aria-label="' . esc_attr__( 'Pagination', 'wp-charity' ) . '">';
    if ( $page > 1 ) {
        $out .= '<a class="button" href="' . esc_url( add_query_arg( $parameter, $page - 1 ) . $fragment ) . '">' . esc_html__( 'Previous', 'wp-charity' ) . '</a> ';
    }
    $out .= '<span>' . sprintf( esc_html__( 'Page %d', 'wp-charity' ), $page ) . '</span> ';
    if ( $more ) {
        $out .= '<a class="button" href="' . esc_url( add_query_arg( $parameter, $page + 1 ) . $fragment ) . '">' . esc_html__( 'Next', 'wp-charity' ) . '</a>';
    }
    return $out . '</nav>';
}
