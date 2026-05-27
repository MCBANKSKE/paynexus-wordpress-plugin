<?php
/**
 * Main PayNexus plugin class.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PayNexus {

    /** @var PayNexus|null */
    private static $instance = null;

    /** @var PayNexus_Client|null */
    public $client = null;

    /**
     * Singleton accessor.
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once PAYNEXUS_PLUGIN_DIR . 'includes/class-paynexus-client.php';
        require_once PAYNEXUS_PLUGIN_DIR . 'includes/class-paynexus-payment.php';
        require_once PAYNEXUS_PLUGIN_DIR . 'includes/class-paynexus-webhook.php';
        require_once PAYNEXUS_PLUGIN_DIR . 'includes/class-paynexus-shortcode.php';

        if ( is_admin() ) {
            require_once PAYNEXUS_PLUGIN_DIR . 'includes/class-paynexus-admin.php';
        }
    }

    /**
     * Hook into WordPress.
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'rest_api_init', array( 'PayNexus_Webhook', 'register_routes' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'wp_ajax_paynexus_initiate_payment', array( $this, 'ajax_initiate_payment' ) );
        add_action( 'wp_ajax_nopriv_paynexus_initiate_payment', array( $this, 'ajax_initiate_payment' ) );
        add_action( 'wp_ajax_paynexus_check_status', array( $this, 'ajax_check_status' ) );
        add_action( 'wp_ajax_nopriv_paynexus_check_status', array( $this, 'ajax_check_status' ) );

        // WooCommerce integration
        add_action( 'plugins_loaded', array( $this, 'init_woocommerce' ) );
        add_filter( 'plugin_action_links_' . PAYNEXUS_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

        // Declare WooCommerce HPOS compatibility
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }

    /**
     * Plugin initialisation.
     */
    public function init() {
        $this->client = new PayNexus_Client(
            $this->get_option( 'secret_key', '' ),
            $this->get_option( 'base_url', 'https://paynexus.co.ke' ),
            $this->get_option( 'public_key', '' )
        );

        PayNexus_Shortcode::init();
    }

    /**
     * Load WooCommerce gateway when WC is active.
     */
    public function init_woocommerce() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        require_once PAYNEXUS_PLUGIN_DIR . 'includes/class-paynexus-woocommerce.php';
        add_filter( 'woocommerce_payment_gateways', array( 'PayNexus_WooCommerce', 'add_gateway' ) );

        // Register block checkout support.
        add_action( 'woocommerce_blocks_loaded', array( $this, 'register_block_payment_method' ) );
    }

    /**
     * Register the block-based checkout payment method.
     */
    public function register_block_payment_method() {
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry' ) ) {
            return;
        }
        require_once PAYNEXUS_PLUGIN_DIR . 'includes/class-paynexus-woocommerce-blocks.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function ( $registry ) {
                $registry->register( new PayNexus_WooCommerce_Blocks() );
            }
        );
    }

    /**
     * Declare HPOS compatibility for WooCommerce.
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                PAYNEXUS_PLUGIN_FILE,
                true
            );
        }
    }

    /**
     * Enqueue frontend scripts.
     */
    public function enqueue_frontend_assets() {
        wp_register_style(
            'paynexus-payment',
            PAYNEXUS_PLUGIN_URL . 'assets/css/paynexus-payment.css',
            array(),
            PAYNEXUS_VERSION
        );
        wp_register_script(
            'paynexus-payment',
            PAYNEXUS_PLUGIN_URL . 'assets/js/paynexus-payment.js',
            array( 'jquery' ),
            PAYNEXUS_VERSION,
            true
        );
        wp_localize_script( 'paynexus-payment', 'paynexus_params', array(
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'paynexus_payment' ),
            'poll_interval'  => absint( $this->get_option( 'poll_interval', 3 ) ) * 1000,
            'poll_timeout'   => absint( $this->get_option( 'poll_timeout', 120 ) ) * 1000,
            'currency'       => $this->get_option( 'currency', 'KES' ),
            'i18n'           => array(
                'processing'   => __( 'Processing payment...', 'paynexus' ),
                'waiting'      => __( 'Waiting for M-Pesa confirmation...', 'paynexus' ),
                'completed'    => __( 'Payment completed successfully!', 'paynexus' ),
                'failed'       => __( 'Payment failed. Please try again.', 'paynexus' ),
                'timeout'      => __( 'Payment timed out. Please try again.', 'paynexus' ),
                'error'        => __( 'An error occurred. Please try again.', 'paynexus' ),
            ),
        ) );
    }

    /**
     * AJAX: initiate payment.
     */
    public function ajax_initiate_payment() {
        check_ajax_referer( 'paynexus_payment', 'nonce' );

        $phone  = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $amount = floatval( $_POST['amount'] ?? 0 );

        if ( empty( $phone ) || $amount <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Phone number and amount are required.', 'paynexus' ) ) );
        }

        $data = array(
            'amount'      => $amount,
            'phone'       => $phone,
            'description' => sanitize_text_field( wp_unslash( $_POST['description'] ?? 'Payment via PayNexus' ) ),
        );

        $payment_account_id = intval( $_POST['payment_account_id'] ?? 0 );
        if ( $payment_account_id > 0 ) {
            $data['payment_account_id'] = $payment_account_id;
        }

        $result = $this->client->initiate_payment( $data );

        if ( ! empty( $result['success'] ) ) {
            wp_send_json_success( $result['data'] ?? $result );
        } else {
            wp_send_json_error( array( 'message' => $result['message'] ?? __( 'Payment initiation failed.', 'paynexus' ) ) );
        }
    }

    /**
     * AJAX: check payment status.
     *
     * Queries the PayNexus API for real-time M-Pesa transaction status
     * and updates the WooCommerce order when the payment completes or fails.
     */
    public function ajax_check_status() {
        check_ajax_referer( 'paynexus_payment', 'nonce' );

        $checkout_request_id = sanitize_text_field( wp_unslash( $_POST['checkout_request_id'] ?? '' ) );
        $reference           = sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) );

        if ( empty( $checkout_request_id ) && empty( $reference ) ) {
            wp_send_json_error( array( 'message' => __( 'A checkout request ID or reference is required.', 'paynexus' ) ) );
        }

        // Query the PayNexus server database for payment status.
        $result = array( 'success' => false );
        if ( ! empty( $checkout_request_id ) ) {
            $result = $this->client->get_payment_by_checkout_id( $checkout_request_id );
        }
        if ( empty( $result['success'] ) && ! empty( $reference ) ) {
            $result = $this->client->get_payment_by_reference( $reference );
        }

        if ( empty( $result['success'] ) ) {
            wp_send_json_error( array( 'message' => $result['message'] ?? __( 'Status check failed.', 'paynexus' ) ) );
        }

        $data   = $result['data'] ?? $result;
        $status = $data['status'] ?? '';

        // Sync local payment record with API response.
        // Note: get_payment_by_checkout_id() already calls sync_local_record(),
        // but get_payment_by_reference() does not, so we sync here for consistency.
        if ( ! empty( $reference ) && empty( $checkout_request_id ) ) {
            $local = PayNexus_Payment::find_by( 'reference', $reference );
            if ( $local ) {
                $updates = array();
                if ( $status && $status !== $local->status ) {
                    $updates['status'] = $status;
                }
                $txn_id = $data['provider_transaction_id'] ?? $data['transaction_id'] ?? null;
                if ( $txn_id && empty( $local->transaction_id ) ) {
                    $updates['transaction_id'] = $txn_id;
                }
                $provider_ref = $data['provider_reference'] ?? null;
                if ( $provider_ref ) {
                    $updates['provider_reference'] = $provider_ref;
                }
                $payer_name = $data['payer_name'] ?? null;
                if ( $payer_name && empty( $local->payer_name ) ) {
                    $updates['payer_name'] = $payer_name;
                }
                $failure_reason = $data['failure_reason'] ?? null;
                if ( $failure_reason && empty( $local->failure_reason ) ) {
                    $updates['failure_reason'] = $failure_reason;
                }
                if ( ! empty( $updates ) ) {
                    PayNexus_Payment::update_payment( $local->id, $updates );
                }
            }
        }

        // Update WooCommerce order when payment reaches a final state.
        if ( in_array( $status, array( 'completed', 'failed' ), true ) && function_exists( 'wc_get_order' ) ) {
            $this->sync_woo_order_status( $checkout_request_id, $reference, $data, $status );
        }

        wp_send_json_success( $data );
    }

    /**
     * Update the WooCommerce order linked to a payment.
     */
    private function sync_woo_order_status( $checkout_request_id, $reference, $data, $status ) {
        $local = null;
        if ( $checkout_request_id ) {
            $local = PayNexus_Payment::find_by( 'checkout_request_id', $checkout_request_id );
        }
        if ( ! $local && $reference ) {
            $local = PayNexus_Payment::find_by( 'reference', $reference );
        }
        if ( ! $local || empty( $local->order_id ) ) {
            return;
        }

        $order = wc_get_order( $local->order_id );
        if ( ! $order || $order->is_paid() ) {
            return;
        }

        if ( 'completed' === $status ) {
            $txn_id = $data['provider_transaction_id'] ?? $data['transaction_id'] ?? '';
            $order->payment_complete( $txn_id );
            $order->add_order_note(
                sprintf(
                    __( 'PayNexus payment completed (via status poll). Ref: %1$s | Txn: %2$s', 'paynexus' ),
                    $data['reference'] ?? $reference,
                    $txn_id
                )
            );
        } elseif ( 'failed' === $status ) {
            $reason = $data['failure_reason'] ?? $data['result_description'] ?? __( 'Payment failed.', 'paynexus' );
            $order->update_status( 'failed', sprintf(
                __( 'PayNexus payment failed (via status poll): %s', 'paynexus' ),
                $reason
            ) );
        }
    }

    /**
     * Add settings link on the plugins page.
     */
    public function plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=paynexus' ),
            __( 'Settings', 'paynexus' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    // ------------------------------------------------------------------
    //  Options helper
    // ------------------------------------------------------------------

    /**
     * Get a plugin option.
     */
    public function get_option( $key, $default = '' ) {
        $options = get_option( 'paynexus_settings', array() );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    // ------------------------------------------------------------------
    //  Activation / Deactivation
    // ------------------------------------------------------------------

    /**
     * Run on plugin activation.
     */
    public static function activate() {
        require_once PAYNEXUS_PLUGIN_DIR . 'includes/class-paynexus-payment.php';
        PayNexus_Payment::create_table();

        // Set default options if not already present.
        if ( false === get_option( 'paynexus_settings' ) ) {
            update_option( 'paynexus_settings', array(
                'base_url'       => 'https://paynexus.co.ke',
                'currency'       => 'KES',
                'poll_interval'  => 3,
                'poll_timeout'   => 120,
                'http_timeout'   => 30,
                'http_retries'   => 2,
            ) );
        }

        flush_rewrite_rules();
    }

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
