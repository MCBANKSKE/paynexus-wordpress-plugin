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

        $wpdb->insert( self::table_name(), $data );

        return $wpdb->insert_id ?: false;
    }

    /**
     * Update an existing payment record.
     */
    public static function update_payment( $id, $data ) {
        global $wpdb;

        $data['updated_at'] = current_time( 'mysql', true );

        return $wpdb->update(
            self::table_name(),
            $data,
            array( 'id' => intval( $id ) )
        );
    }

    /**
     * Find a single payment by a column value.
     *
     * @return object|null
     */
    public static function find_by( $column, $value ) {
        global $wpdb;

        $table    = self::table_name();
        $allowed  = array(
            'id', 'paynexus_payment_id', 'reference',
            'checkout_request_id', 'merchant_request_id',
            'transaction_id', 'order_id', 'idempotency_key',
        );

        if ( ! in_array( $column, $allowed, true ) ) {
            return null;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE `{$column}` = %s LIMIT 1",
            $value
        ) );
    }

    /**
     * Find all payments for a given order.
     */
    public static function find_by_order( $order_id ) {
        global $wpdb;

        $table = self::table_name();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC",
            intval( $order_id )
        ) );
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
        $where    = '1=1';
        $values   = array();

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where   .= ' AND (phone LIKE %s OR reference LIKE %s OR transaction_id LIKE %s OR payer_name LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $allowed_cols = array( 'id', 'amount', 'status', 'created_at', 'updated_at' );
        $orderby      = in_array( $args['orderby'], $allowed_cols, true ) ? $args['orderby'] : 'created_at';
        $order        = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        $per_page     = max( 1, intval( $args['per_page'] ) );
        $offset       = max( 0, ( intval( $args['page'] ) - 1 ) * $per_page );

        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE {$where}",
                ...$values
            ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $items = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d",
                ...array_merge( $values, array( $per_page, $offset ) )
            ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $items = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ) );
        }

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    /**
     * Get summary statistics for the payments dashboard.
     */
    public static function get_stats() {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM {$table} GROUP BY status"
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

        return $stats;
    }

    /**
     * Drop the payments table (used on uninstall).
     */
    public static function drop_table() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS " . self::table_name() );
    }
}
