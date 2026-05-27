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
            __( 'PayNexus', 'paynexus' ),
            __( 'PayNexus', 'paynexus' ),
            'manage_options',
            'paynexus',
            array( $this, 'render_settings_page' ),
            PAYNEXUS_PLUGIN_URL . 'assets/images/logo.png',
            58
        );

        add_submenu_page(
            'paynexus',
            __( 'Settings', 'paynexus' ),
            __( 'Settings', 'paynexus' ),
            'manage_options',
            'paynexus',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'paynexus',
            __( 'Payments', 'paynexus' ),
            __( 'Payments', 'paynexus' ),
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
            __( 'API Keys', 'paynexus' ),
            function () {
                printf(
                    '<p>%s <a href="https://paynexus.co.ke/merchant/merchant-api-keys" target="_blank">%s</a></p>',
                    esc_html__( 'Get your API keys from the PayNexus merchant dashboard:', 'paynexus' ),
                    esc_html__( 'paynexus.co.ke/merchant/merchant-api-keys', 'paynexus' )
                );
            },
            'paynexus'
        );

        $this->add_field( 'secret_key', __( 'Secret Key (sk_)', 'paynexus' ), 'password', 'paynexus_api' );
        $this->add_field( 'public_key', __( 'Public Key (pk_)', 'paynexus' ), 'password', 'paynexus_api' );
        $this->add_field( 'base_url', __( 'Base URL', 'paynexus' ), 'url', 'paynexus_api', 'https://paynexus.co.ke' );

        // Webhook section
        add_settings_section(
            'paynexus_webhook',
            __( 'Webhook', 'paynexus' ),
            function () {
                $webhook_url = rest_url( 'paynexus/v1/webhook' );
                printf(
                    '<p>%s</p><code>%s</code>',
                    esc_html__( 'Configure this URL as your webhook endpoint in the PayNexus dashboard:', 'paynexus' ),
                    esc_html( $webhook_url )
                );
            },
            'paynexus'
        );

        $this->add_field( 'webhook_secret', __( 'Webhook Secret', 'paynexus' ), 'password', 'paynexus_webhook' );

        // General section
        add_settings_section(
            'paynexus_general',
            __( 'General Settings', 'paynexus' ),
            null,
            'paynexus'
        );

        $this->add_field( 'currency', __( 'Currency', 'paynexus' ), 'text', 'paynexus_general', 'KES' );
        $this->add_field( 'poll_interval', __( 'Poll Interval (seconds)', 'paynexus' ), 'number', 'paynexus_general', '3' );
        $this->add_field( 'poll_timeout', __( 'Poll Timeout (seconds)', 'paynexus' ), 'number', 'paynexus_general', '120' );
        $this->add_field( 'http_timeout', __( 'HTTP Timeout (seconds)', 'paynexus' ), 'number', 'paynexus_general', '30' );
        $this->add_field( 'http_retries', __( 'HTTP Retries', 'paynexus' ), 'number', 'paynexus_general', '2' );
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
        $sanitized['webhook_secret']  = sanitize_text_field( $input['webhook_secret'] ?? '' );
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
                <img src="<?php echo esc_url( PAYNEXUS_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="PayNexus" style="height:30px;margin-right:8px;vertical-align:middle;">
                <?php esc_html_e( 'PayNexus Settings', 'paynexus' ); ?>
            </h1>

            <?php settings_errors(); ?>

            <div class="paynexus-info-box">
                <h3><?php esc_html_e( 'Quick Start', 'paynexus' ); ?></h3>
                <ol>
                    <li><?php printf(
                        /* translators: %s: link */
                        esc_html__( 'Get your API keys from %s', 'paynexus' ),
                        '<a href="https://paynexus.co.ke/merchant/merchant-api-keys" target="_blank">paynexus.co.ke</a>'
                    ); ?></li>
                    <li><?php esc_html_e( 'Enter your Secret Key and (optional) Public Key below.', 'paynexus' ); ?></li>
                    <li><?php printf(
                        /* translators: %s: webhook URL */
                        esc_html__( 'Set your webhook URL to: %s', 'paynexus' ),
                        '<code>' . esc_html( rest_url( 'paynexus/v1/webhook' ) ) . '</code>'
                    ); ?></li>
                    <li><?php esc_html_e( 'Use the [paynexus_payment_form] shortcode or WooCommerce gateway.', 'paynexus' ); ?></li>
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
            <h3><?php esc_html_e( 'Connection Test', 'paynexus' ); ?></h3>
            <?php if ( $ok ) : ?>
                <p><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Connected to PayNexus successfully.', 'paynexus' ); ?></p>
                <?php
                $data = $result['data'] ?? $result;
                if ( ! empty( $data['name'] ) ) {
                    printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Merchant', 'paynexus' ), esc_html( $data['name'] ) );
                }
                if ( ! empty( $data['email'] ) ) {
                    printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Email', 'paynexus' ), esc_html( $data['email'] ) );
                }
                ?>
            <?php else : ?>
                <p><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Connection failed.', 'paynexus' ); ?></p>
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
        $page     = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $status   = sanitize_text_field( $_GET['status'] ?? '' );

        $result = PayNexus_Payment::list_payments( array(
            'status'   => $status,
            'per_page' => $per_page,
            'page'     => $page,
        ) );

        $items = $result['items'];
        $total = $result['total'];
        $pages = ceil( $total / $per_page );
        ?>
        <div class="wrap paynexus-admin">
            <h1>
                <img src="<?php echo esc_url( PAYNEXUS_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="PayNexus" style="height:30px;margin-right:8px;vertical-align:middle;">
                <?php esc_html_e( 'PayNexus Payments', 'paynexus' ); ?>
            </h1>

            <div class="paynexus-filters">
                <form method="get">
                    <input type="hidden" name="page" value="paynexus-payments" />
                    <select name="status">
                        <option value=""><?php esc_html_e( 'All statuses', 'paynexus' ); ?></option>
                        <?php foreach ( array( 'pending', 'completed', 'failed', 'timeout' ) as $s ) : ?>
                            <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php submit_button( __( 'Filter', 'paynexus' ), 'secondary', 'filter', false ); ?>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'paynexus' ); ?></th>
                        <th><?php esc_html_e( 'Reference', 'paynexus' ); ?></th>
                        <th><?php esc_html_e( 'Amount', 'paynexus' ); ?></th>
                        <th><?php esc_html_e( 'Phone', 'paynexus' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'paynexus' ); ?></th>
                        <th><?php esc_html_e( 'Transaction ID', 'paynexus' ); ?></th>
                        <th><?php esc_html_e( 'Payer', 'paynexus' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'paynexus' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $items ) ) : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'No payments found.', 'paynexus' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $items as $item ) : ?>
                            <tr>
                                <td><?php echo esc_html( $item->id ); ?></td>
                                <td><code><?php echo esc_html( $item->reference ); ?></code></td>
                                <td><?php echo esc_html( $item->currency . ' ' . number_format( (float) $item->amount, 2 ) ); ?></td>
                                <td><?php echo esc_html( $item->phone ); ?></td>
                                <td>
                                    <span class="paynexus-status paynexus-status-<?php echo esc_attr( $item->status ); ?>">
                                        <?php echo esc_html( ucfirst( $item->status ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $item->transaction_id ?? '—' ); ?></td>
                                <td><?php echo esc_html( $item->payer_name ?? '—' ); ?></td>
                                <td><?php echo esc_html( $item->created_at ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links( array(
                            'base'    => add_query_arg( 'paged', '%#%' ),
                            'format'  => '',
                            'current' => $page,
                            'total'   => $pages,
                        ) );
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
