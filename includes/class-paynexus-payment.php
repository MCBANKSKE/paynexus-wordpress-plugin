<?php
/**
 * PayNexus Payment model — database operations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayNexus_Payment {

    /** @var string */
    const TABLE = 'paynexus_payments';

    /**
     * Full table name (with WP prefix).
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Create the payments table on activation.
     */
    public static function create_table() {
        global $wpdb;

        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            paynexus_payment_id bigint(20) UNSIGNED DEFAULT NULL,
            reference varchar(255) DEFAULT NULL,
            checkout_request_id varchar(255) DEFAULT NULL,
            merchant_request_id varchar(255) DEFAULT NULL,
            transaction_id varchar(255) DEFAULT NULL,
            amount decimal(14,2) NOT NULL DEFAULT 0.00,
            currency varchar(10) NOT NULL DEFAULT 'KES',
            phone varchar(20) DEFAULT NULL,
            description varchar(255) DEFAULT NULL,
            account_reference varchar(50) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            provider_reference varchar(255) DEFAULT NULL,
            failure_reason text DEFAULT NULL,
            payer_name varchar(255) DEFAULT NULL,
            verified_amount decimal(10,2) DEFAULT NULL,
            verified_phone varchar(20) DEFAULT NULL,
            verified_date datetime DEFAULT NULL,
            verification_method varchar(50) DEFAULT NULL,
            user_message varchar(255) DEFAULT NULL,
            retry_possible tinyint(1) NOT NULL DEFAULT 0,
            confirmed_manually tinyint(1) NOT NULL DEFAULT 0,
            confirmed_at datetime DEFAULT NULL,
            confirmed_by varchar(255) DEFAULT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            idempotency_key varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_paynexus_payment_id (paynexus_payment_id),
            KEY idx_reference (reference),
            KEY idx_checkout_request_id (checkout_request_id),
            KEY idx_transaction_id (transaction_id),
            KEY idx_status (status),
            KEY idx_order_id (order_id),
            UNIQUE KEY idx_idempotency_key (idempotency_key)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Insert a new payment record.
     *
     * @return int|false The inserted row ID, or false on failure.
     */
    public static function insert_payment( $data ) {
        global $wpdb;

        $data['created_at'] = current_time( 'mysql', true );
        $data['updated_at'] = current_time( 'mysql', true );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required for plugin transaction storage.
        $wpdb->insert( self::table_name(), $data );

        $insert_id = $wpdb->insert_id ?: false;

        // Clear stats cache after insert
        wp_cache_delete( 'paynexus_stats', 'paynexus' );

        // Clear order payments cache if order_id is set
        if ( isset( $data['order_id'] ) ) {
            wp_cache_delete( 'paynexus_order_payments_' . $data['order_id'], 'paynexus' );
        }

        return $insert_id;
    }

    /**
     * Update an existing payment record.
     */
    public static function update_payment( $id, $data ) {
        global $wpdb;

        $data['updated_at'] = current_time( 'mysql', true );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required for plugin transaction storage.
        $result = $wpdb->update(
            self::table_name(),
            $data,
            array( 'id' => intval( $id ) )
        );

        // Clear cache for this payment and stats after update
        wp_cache_delete( 'paynexus_payment_id_' . $id, 'paynexus' );
        wp_cache_delete( 'paynexus_stats', 'paynexus' );

        // Clear order payments cache if order_id is being updated
        if ( isset( $data['order_id'] ) ) {
            wp_cache_delete( 'paynexus_order_payments_' . $data['order_id'], 'paynexus' );
        }
        // Also clear old order_id cache if it changed
        $old_payment = self::find_by( 'id', $id );
        if ( $old_payment && isset( $old_payment->order_id ) && $old_payment->order_id != ( $data['order_id'] ?? null ) ) {
            wp_cache_delete( 'paynexus_order_payments_' . $old_payment->order_id, 'paynexus' );
        }

        // Clear cache for indexed columns if they were updated
        if ( isset( $data['reference'] ) ) {
            wp_cache_delete( 'paynexus_payment_reference_' . $data['reference'], 'paynexus' );
        }
        if ( isset( $data['checkout_request_id'] ) ) {
            wp_cache_delete( 'paynexus_payment_checkout_request_id_' . $data['checkout_request_id'], 'paynexus' );
        }
        if ( isset( $data['transaction_id'] ) ) {
            wp_cache_delete( 'paynexus_payment_transaction_id_' . $data['transaction_id'], 'paynexus' );
        }
        if ( isset( $data['order_id'] ) ) {
            wp_cache_delete( 'paynexus_payment_order_id_' . $data['order_id'], 'paynexus' );
        }

        return $result;
    }

    /**
     * Find a single payment by a column value.
     *
     * @return object|null
     */
    public static function find_by( $column, $value ) {
        global $wpdb;

        $table = self::table_name();
        $cache_key = 'paynexus_payment_' . $column . '_' . $value;
        $cached = wp_cache_get( $cache_key, 'paynexus' );

        if ( false !== $cached ) {
            return $cached;
        }

        // Use switch statement for SQL-safe column selection
        switch ( $column ) {
            case 'id':
                $sql = 'SELECT * FROM ' . $table . ' WHERE `id` = %s LIMIT 1';
                break;
            case 'paynexus_payment_id':
                $sql = 'SELECT * FROM ' . $table . ' WHERE `paynexus_payment_id` = %s LIMIT 1';
                break;
            case 'reference':
                $sql = 'SELECT * FROM ' . $table . ' WHERE `reference` = %s LIMIT 1';
                break;
            case 'checkout_request_id':
                $sql = 'SELECT * FROM ' . $table . ' WHERE `checkout_request_id` = %s LIMIT 1';
                break;
            case 'merchant_request_id':
                $sql = 'SELECT * FROM ' . $table . ' WHERE `merchant_request_id` = %s LIMIT 1';
                break;
            case 'transaction_id':
                $sql = 'SELECT * FROM ' . $table . ' WHERE `transaction_id` = %s LIMIT 1';
                break;
            case 'order_id':
                $sql = 'SELECT * FROM ' . $table . ' WHERE `order_id` = %s LIMIT 1';
                break;
            case 'idempotency_key':
                $sql = 'SELECT * FROM ' . $table . ' WHERE `idempotency_key` = %s LIMIT 1';
                break;
            default:
                return null;
        }

        $query = $wpdb->prepare( $sql, $value );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name comes from trusted internal method.
        $result = $wpdb->get_row( $query );
        wp_cache_set( $cache_key, $result, 'paynexus', HOUR_IN_SECONDS );

        return $result;
    }

    /**
     * Find all payments for a given order.
     */
    public static function find_by_order( $order_id ) {
        global $wpdb;

        $table = self::table_name();
        $cache_key = 'paynexus_order_payments_' . $order_id;
        $cached = wp_cache_get( $cache_key, 'paynexus' );

        if ( false !== $cached ) {
            return $cached;
        }

        $sql = 'SELECT * FROM ' . $table . ' WHERE order_id = %d ORDER BY created_at DESC';
        $query = $wpdb->prepare( $sql, intval( $order_id ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name comes from trusted internal method.
        $result = $wpdb->get_results( $query );

        wp_cache_set( $cache_key, $result, 'paynexus', MINUTE_IN_SECONDS );

        return $result;
    }

    /**
     * List payments with optional filters and pagination.
     *
     * @param array $args {
     *     @type string $status   Filter by status.
     *     @type int    $per_page Items per page. Default 20.
     *     @type int    $page     Page number. Default 1.
     *     @type string $orderby  Column to order by. Default 'created_at'.
     *     @type string $order    ASC or DESC. Default 'DESC'.
     *     @type string $search   Search by phone, reference, or transaction_id.
     * }
     * @return array { items: object[], total: int }
     */
    public static function list_payments( $args = array() ) {
        global $wpdb;

        $table = self::table_name();

        $defaults = array(
            'status'   => '',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'search'   => '',
        );

        $args     = wp_parse_args( $args, $defaults );
        $values   = array();

        // Create cache key based on args
        $cache_key = 'paynexus_list_' . md5( serialize( $args ) );
        $cached = wp_cache_get( $cache_key, 'paynexus' );

        if ( false !== $cached ) {
            return $cached;
        }

        $allowed_cols = array( 'id', 'amount', 'status', 'created_at', 'updated_at' );
        $orderby      = in_array( $args['orderby'], $allowed_cols, true ) ? $args['orderby'] : 'created_at';
        $order        = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        $per_page     = absint( $args['per_page'] );
        $offset       = absint( ( intval( $args['page'] ) - 1 ) * $per_page );

        // Use switch for SQL-safe orderby
        switch ( $orderby ) {
            case 'id':
                $orderby_sql = '`id`';
                break;
            case 'amount':
                $orderby_sql = '`amount`';
                break;
            case 'status':
                $orderby_sql = '`status`';
                break;
            case 'created_at':
                $orderby_sql = '`created_at`';
                break;
            case 'updated_at':
                $orderby_sql = '`updated_at`';
                break;
            default:
                $orderby_sql = '`created_at`';
        }

        // Use switch for SQL-safe order
        $order_sql = 'ASC' === $order ? 'ASC' : 'DESC';

        // Build COUNT query progressively
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE 1=1";

        if ( ! empty( $args['status'] ) ) {
            $count_sql .= ' AND status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $count_sql .= ' AND (phone LIKE %s OR reference LIKE %s OR transaction_id LIKE %s OR payer_name LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $count_query = $wpdb->prepare( $count_sql, $values );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name comes from trusted internal method.
        $total = (int) $wpdb->get_var( $count_query );

        // Build SELECT query progressively
        $select_sql = "SELECT * FROM {$table} WHERE 1=1";
        $select_values = array();

        if ( ! empty( $args['status'] ) ) {
            $select_sql .= ' AND status = %s';
            $select_values[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $select_sql .= ' AND (phone LIKE %s OR reference LIKE %s OR transaction_id LIKE %s OR payer_name LIKE %s)';
            $select_values[] = $like;
            $select_values[] = $like;
            $select_values[] = $like;
            $select_values[] = $like;
        }

        $select_sql .= " ORDER BY {$orderby_sql} {$order_sql} LIMIT %d OFFSET %d";
        $select_values[] = $per_page;
        $select_values[] = $offset;

        $select_query = $wpdb->prepare( $select_sql, $select_values );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name comes from trusted internal method.
        $items = $wpdb->get_results( $select_query );

        $result = array(
            'items' => $items,
            'total' => $total,
        );

        wp_cache_set( $cache_key, $result, 'paynexus', 30 );

        return $result;
    }

    /**
     * Get summary statistics for the payments dashboard.
     */
    public static function get_stats() {
        global $wpdb;

        $cache_key = 'paynexus_stats';
        $cached = wp_cache_get( $cache_key, 'paynexus' );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name comes from trusted internal method.
        $rows = $wpdb->get_results(
            'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM ' . $table . ' GROUP BY status'
        );

        $stats = array(
            'total_count'     => 0,
            'total_revenue'   => 0,
            'completed_count' => 0,
            'completed_sum'   => 0,
            'pending_count'   => 0,
            'failed_count'    => 0,
        );

        foreach ( $rows as $row ) {
            $stats['total_count'] += (int) $row->cnt;
            if ( 'completed' === $row->status ) {
                $stats['completed_count'] = (int) $row->cnt;
                $stats['completed_sum']   = (float) $row->total;
                $stats['total_revenue']   = (float) $row->total;
            } elseif ( 'pending' === $row->status ) {
                $stats['pending_count'] = (int) $row->cnt;
            } elseif ( 'failed' === $row->status ) {
                $stats['failed_count'] = (int) $row->cnt;
            }
        }

        wp_cache_set( $cache_key, $stats, 'paynexus', 5 * MINUTE_IN_SECONDS );

        return $stats;
    }

    /**
     * Drop the payments table (used on uninstall).
     */
    public static function drop_table() {
        global $wpdb;
        $table_name = self::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted internal table name and intentional uninstall cleanup, caching not applicable.
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    }
}
