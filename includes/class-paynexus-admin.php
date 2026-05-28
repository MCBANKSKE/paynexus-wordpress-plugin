<?php
/**
 * PayNexus Admin settings page & payments list.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayNexus_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    // -----------------------------------------------------------------
    //  Menu
    // -----------------------------------------------------------------

    public function register_menu() {
        add_menu_page(
            __( 'PayNexus', 'paynexus-payment-gateway' ),
            __( 'PayNexus', 'paynexus-payment-gateway' ),
            'manage_options',
            'paynexus',
            array( $this, 'render_settings_page' ),
            'dashicons-money-alt',
            58
        );

        add_submenu_page(
            'paynexus',
            __( 'Settings', 'paynexus-payment-gateway' ),
            __( 'Settings', 'paynexus-payment-gateway' ),
            'manage_options',
            'paynexus',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'paynexus',
            __( 'Payments', 'paynexus-payment-gateway' ),
            __( 'Payments', 'paynexus-payment-gateway' ),
            'manage_options',
            'paynexus-payments',
            array( $this, 'render_payments_page' )
        );
    }

    // -----------------------------------------------------------------
    //  Settings registration
    // -----------------------------------------------------------------

    public function register_settings() {
        register_setting( 'paynexus_settings_group', 'paynexus_settings', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );

        // API Keys section
        add_settings_section(
            'paynexus_api',
            __( 'API Keys', 'paynexus-payment-gateway' ),
            function () {
                printf(
                    '<p>%s <a href="https://paynexus.co.ke/merchant/merchant-api-keys" target="_blank">%s</a></p>',
                    esc_html__( 'Get your API keys from the PayNexus merchant dashboard:', 'paynexus-payment-gateway' ),
                    esc_html__( 'paynexus.co.ke/merchant/merchant-api-keys', 'paynexus-payment-gateway' )
                );
            },
            'paynexus'
        );

        $this->add_field( 'secret_key', __( 'Secret Key (sk_)', 'paynexus-payment-gateway' ), 'password', 'paynexus_api' );
        $this->add_field( 'public_key', __( 'Public Key (pk_)', 'paynexus-payment-gateway' ), 'password', 'paynexus_api' );
        $this->add_field( 'base_url', __( 'Base URL', 'paynexus-payment-gateway' ), 'url', 'paynexus_api', 'https://paynexus.co.ke' );

        // Webhook section
        add_settings_section(
            'paynexus_webhook',
            __( 'Webhook', 'paynexus-payment-gateway' ),
            function () {
                $webhook_url = rest_url( 'paynexus/v1/webhook' );
                printf(
                    '<p>%s</p><code>%s</code>',
                    esc_html__( 'Configure this URL as your webhook endpoint in the PayNexus dashboard:', 'paynexus-payment-gateway' ),
                    esc_html( $webhook_url )
                );
            },
            'paynexus'
        );

        $this->add_field( 'webhook_secret', __( 'Webhook Secret', 'paynexus-payment-gateway' ), 'password', 'paynexus_webhook' );

        // General section
        add_settings_section(
            'paynexus_general',
            __( 'General Settings', 'paynexus-payment-gateway' ),
            null,
            'paynexus'
        );

        $this->add_field( 'currency', __( 'Currency', 'paynexus-payment-gateway' ), 'text', 'paynexus_general', 'KES' );
        $this->add_field( 'poll_interval', __( 'Poll Interval (seconds)', 'paynexus-payment-gateway' ), 'number', 'paynexus_general', '3' );
        $this->add_field( 'poll_timeout', __( 'Poll Timeout (seconds)', 'paynexus-payment-gateway' ), 'number', 'paynexus_general', '120' );
        $this->add_field( 'http_timeout', __( 'HTTP Timeout (seconds)', 'paynexus-payment-gateway' ), 'number', 'paynexus_general', '30' );
        $this->add_field( 'http_retries', __( 'HTTP Retries', 'paynexus-payment-gateway' ), 'number', 'paynexus_general', '2' );
    }

    /**
     * Add a settings field.
     */
    private function add_field( $id, $title, $type, $section, $default = '' ) {
        add_settings_field(
            'paynexus_' . $id,
            $title,
            array( $this, 'render_field' ),
            'paynexus',
            $section,
            array(
                'id'      => $id,
                'type'    => $type,
                'default' => $default,
            )
        );
    }

    /**
     * Render a settings field.
     */
    public function render_field( $args ) {
        $options = get_option( 'paynexus_settings', array() );
        $value   = $options[ $args['id'] ] ?? $args['default'];
        $name    = 'paynexus_settings[' . esc_attr( $args['id'] ) . ']';

        printf(
            '<input type="%s" name="%s" value="%s" class="regular-text" />',
            esc_attr( $args['type'] ),
            esc_attr( $name ),
            esc_attr( $value )
        );
    }

    /**
     * Sanitize settings on save.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        $sanitized['secret_key']      = sanitize_text_field( $input['secret_key'] ?? '' );
        $sanitized['public_key']      = sanitize_text_field( $input['public_key'] ?? '' );
        $sanitized['base_url']        = esc_url_raw( $input['base_url'] ?? 'https://paynexus.co.ke' );
        $sanitized['webhook_secret']  = isset( $input['webhook_secret'] ) ? wp_unslash( $input['webhook_secret'] ) : '';
        $sanitized['currency']        = sanitize_text_field( $input['currency'] ?? 'KES' );
        $sanitized['poll_interval']   = absint( $input['poll_interval'] ?? 3 );
        $sanitized['poll_timeout']    = absint( $input['poll_timeout'] ?? 120 );
        $sanitized['http_timeout']    = absint( $input['http_timeout'] ?? 30 );
        $sanitized['http_retries']    = absint( $input['http_retries'] ?? 2 );

        if ( empty( $sanitized['base_url'] ) ) {
            $sanitized['base_url'] = 'https://paynexus.co.ke';
        }

        return $sanitized;
    }

    // -----------------------------------------------------------------
    //  Admin assets
    // -----------------------------------------------------------------

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'paynexus' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'paynexus-admin',
            PAYNEXUS_PLUGIN_URL . 'assets/css/paynexus-admin.css',
            array(),
            PAYNEXUS_VERSION
        );
    }

    // -----------------------------------------------------------------
    //  Settings page
    // -----------------------------------------------------------------

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap paynexus-admin">
            <h1>
                <img src="<?php echo esc_url( PAYNEXUS_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="PayNexus" style="max-height:32px;vertical-align:middle;margin-right:8px;" />
                <?php esc_html_e( 'PayNexus Settings', 'paynexus-payment-gateway' ); ?>
                <?php
                $sk = paynexus()->get_option( 'secret_key', '' );
                if ( $sk ) {
                    $prefix = substr( $sk, 0, 3 );
                    $is_test = ( 'tk_' === $prefix );
                    printf(
                        '<span class="pnx-env-badge pnx-env-badge--%s">%s</span>',
                        $is_test ? 'test' : 'live',
                        $is_test ? esc_html__( 'Test Mode', 'paynexus-payment-gateway' ) : esc_html__( 'Live', 'paynexus-payment-gateway' )
                    );
                }
                ?>
            </h1>

            <?php settings_errors(); ?>

            <div class="paynexus-info-box">
                <h3><?php esc_html_e( 'Quick Start', 'paynexus-payment-gateway' ); ?></h3>
                <ol>
                    <li><?php printf(
                        /* translators: %s: link */
                        esc_html__( 'Get your API keys from %s', 'paynexus-payment-gateway' ),
                        '<a href="https://paynexus.co.ke/merchant/merchant-api-keys" target="_blank">paynexus.co.ke</a>'
                    ); ?></li>
                    <li><?php esc_html_e( 'Enter your Secret Key and (optional) Public Key below.', 'paynexus-payment-gateway' ); ?></li>
                    <li><?php printf(
                        /* translators: %s: webhook URL */
                        esc_html__( 'Set your webhook URL to: %s', 'paynexus-payment-gateway' ),
                        '<code>' . esc_html( rest_url( 'paynexus/v1/webhook' ) ) . '</code>'
                    ); ?></li>
                    <li><?php esc_html_e( 'Use the [paynexus_payment_form] shortcode or WooCommerce gateway.', 'paynexus-payment-gateway' ); ?></li>
                </ol>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'paynexus_settings_group' );
                do_settings_sections( 'paynexus' );
                submit_button();
                ?>
            </form>

            <?php $this->render_connection_test(); ?>
        </div>
        <?php
    }

    /**
     * Connection test section.
     */
    private function render_connection_test() {
        $secret_key = paynexus()->get_option( 'secret_key', '' );
        if ( empty( $secret_key ) ) {
            return;
        }

        $result = paynexus()->client->get_merchant();
        $ok     = ! empty( $result['success'] ) || ! empty( $result['data'] );
        ?>
        <div class="paynexus-connection-test <?php echo $ok ? 'success' : 'error'; ?>">
            <h3><?php esc_html_e( 'Connection Test', 'paynexus-payment-gateway' ); ?></h3>
            <?php if ( $ok ) : ?>
                <p><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Connected to PayNexus successfully.', 'paynexus-payment-gateway' ); ?></p>
                <?php
                $data = $result['data'] ?? $result;
                if ( ! empty( $data['name'] ) ) {
                    printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Merchant', 'paynexus-payment-gateway' ), esc_html( $data['name'] ) );
                }
                if ( ! empty( $data['email'] ) ) {
                    printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Email', 'paynexus-payment-gateway' ), esc_html( $data['email'] ) );
                }
                ?>
            <?php else : ?>
                <p><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Connection failed.', 'paynexus-payment-gateway' ); ?></p>
                <p><?php echo esc_html( $result['message'] ?? 'Unknown error.' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    // -----------------------------------------------------------------
    //  Payments page
    // -----------------------------------------------------------------

    public function render_payments_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $per_page = 20;
        $page     = max( 1, intval( wp_unslash( $_GET['paged'] ?? 1 ) ) );
        $status   = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
        $search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
        $stats    = PayNexus_Payment::get_stats();
        $currency = paynexus()->get_option( 'currency', 'KES' );

        $result = PayNexus_Payment::list_payments( array(
            'status'   => $status,
            'per_page' => $per_page,
            'page'     => $page,
            'search'   => $search,
        ) );

        $items = $result['items'];
        $total = $result['total'];
        $pages = ceil( $total / $per_page );
        ?>
        <div class="wrap paynexus-admin">
            <h1>
                <img src="<?php echo esc_url( PAYNEXUS_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="PayNexus" style="max-height:32px;vertical-align:middle;margin-right:8px;" />
                <?php esc_html_e( 'PayNexus Payments', 'paynexus-payment-gateway' ); ?>
            </h1>

            <div class="pnx-stats-grid">
                <div class="pnx-stat-card">
                    <span class="pnx-stat-icon" style="background:#e3f2fd;color:#1565c0;">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </span>
                    <div class="pnx-stat-body">
                        <span class="pnx-stat-value"><?php echo esc_html( $stats['total_count'] ); ?></span>
                        <span class="pnx-stat-label"><?php esc_html_e( 'Total Payments', 'paynexus-payment-gateway' ); ?></span>
                    </div>
                </div>
                <div class="pnx-stat-card">
                    <span class="pnx-stat-icon" style="background:#e8f5e9;color:#2e7d32;">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </span>
                    <div class="pnx-stat-body">
                        <span class="pnx-stat-value"><?php echo esc_html( $stats['completed_count'] ); ?></span>
                        <span class="pnx-stat-label"><?php esc_html_e( 'Completed', 'paynexus-payment-gateway' ); ?></span>
                    </div>
                </div>
                <div class="pnx-stat-card">
                    <span class="pnx-stat-icon" style="background:#fff3e0;color:#e65100;">
                        <span class="dashicons dashicons-clock"></span>
                    </span>
                    <div class="pnx-stat-body">
                        <span class="pnx-stat-value"><?php echo esc_html( $stats['pending_count'] ); ?></span>
                        <span class="pnx-stat-label"><?php esc_html_e( 'Pending', 'paynexus-payment-gateway' ); ?></span>
                    </div>
                </div>
                <div class="pnx-stat-card">
                    <span class="pnx-stat-icon" style="background:#e8f5e9;color:#1b5e20;">
                        <span class="dashicons dashicons-money-alt"></span>
                    </span>
                    <div class="pnx-stat-body">
                        <span class="pnx-stat-value"><?php echo esc_html( $currency . ' ' . number_format( $stats['total_revenue'], 2 ) ); ?></span>
                        <span class="pnx-stat-label"><?php esc_html_e( 'Revenue', 'paynexus-payment-gateway' ); ?></span>
                    </div>
                </div>
            </div>

            <div class="pnx-toolbar">
                <form method="get" class="pnx-toolbar__form">
                    <input type="hidden" name="page" value="paynexus-payments" />
                    <select name="status" class="pnx-toolbar__select">
                        <option value=""><?php esc_html_e( 'All statuses', 'paynexus-payment-gateway' ); ?></option>
                        <?php foreach ( array( 'pending', 'completed', 'failed', 'timeout' ) as $s ) : ?>
                            <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Search phone, reference, txn ID...', 'paynexus-payment-gateway' ); ?>"
                           class="pnx-toolbar__search" />
                    <?php submit_button( __( 'Filter', 'paynexus-payment-gateway' ), 'secondary', 'filter', false ); ?>
                </form>
                <?php if ( ! empty( $items ) ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'paynexus-payments', 'export' => 'csv', 'status' => $status, 's' => $search ), admin_url( 'admin.php' ) ) ); ?>" class="button pnx-toolbar__export">
                        <span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:2px;"></span>
                        <?php esc_html_e( 'Export CSV', 'paynexus-payment-gateway' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <table class="wp-list-table widefat fixed striped pnx-payments-table">
                <thead>
                    <tr>
                        <th style="width:40px;"><?php esc_html_e( 'ID', 'paynexus-payment-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Reference', 'paynexus-payment-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Amount', 'paynexus-payment-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Phone', 'paynexus-payment-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'paynexus-payment-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Transaction ID', 'paynexus-payment-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Payer', 'paynexus-payment-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Order', 'paynexus-payment-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'paynexus-payment-gateway' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $items ) ) : ?>
                        <tr><td colspan="9" style="text-align:center;padding:40px 20px;color:#888;">
                            <span class="dashicons dashicons-search" style="font-size:32px;display:block;margin:0 auto 8px;color:#ccc;"></span>
                            <?php esc_html_e( 'No payments found.', 'paynexus-payment-gateway' ); ?>
                        </td></tr>
                    <?php else : ?>
                        <?php foreach ( $items as $item ) :
                            $amount_class = 'completed' === $item->status ? 'pnx-amount--success' : ( 'failed' === $item->status ? 'pnx-amount--failed' : '' );
                        ?>
                            <tr>
                                <td><?php echo esc_html( $item->id ); ?></td>
                                <td><code style="font-size:12px;"><?php echo esc_html( $item->reference ); ?></code></td>
                                <td class="<?php echo esc_attr( $amount_class ); ?>"><?php echo esc_html( ( $item->currency ?? 'KES' ) . ' ' . number_format( (float) $item->amount, 2 ) ); ?></td>
                                <td><?php echo esc_html( $item->phone ); ?></td>
                                <td>
                                    <span class="paynexus-status paynexus-status-<?php echo esc_attr( $item->status ); ?>">
                                        <?php echo esc_html( ucfirst( $item->status ) ); ?>
                                    </span>
                                </td>
                                <td><?php if ( ! empty( $item->transaction_id ) ) : ?>
                                    <code style="font-size:12px;"><?php echo esc_html( $item->transaction_id ); ?></code>
                                <?php else : ?>
                                    <span style="color:#ccc;">—</span>
                                <?php endif; ?></td>
                                <td><?php echo esc_html( $item->payer_name ?? '—' ); ?></td>
                                <td><?php if ( ! empty( $item->order_id ) ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . intval( $item->order_id ) . '&action=edit' ) ); ?>" title="<?php esc_attr_e( 'View Order', 'paynexus' ); ?>">
                                        #<?php echo esc_html( $item->order_id ); ?>
                                    </a>
                                <?php else : ?>
                                    <span style="color:#ccc;">—</span>
                                <?php endif; ?></td>
                                <td style="font-size:12px;color:#666;"><?php echo esc_html( $item->created_at ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post( paginate_links( array(
                            'base'    => add_query_arg( 'paged', '%#%' ),
                            'format'  => '',
                            'current' => $page,
                            'total'   => $pages,
                        ) ) );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialise admin if on admin pages.
if ( is_admin() ) {
    new PayNexus_Admin();
}
