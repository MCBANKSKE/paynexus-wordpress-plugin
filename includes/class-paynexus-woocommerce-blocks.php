<?php
/**
 * PayNexus WooCommerce Blocks Payment Method Integration.
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PayNexus_WooCommerce_Blocks extends AbstractPaymentMethodType {

    /** @var string */
    protected $name = 'paynexus';

    /** @var PayNexus_WooCommerce|null */
    private $gateway = null;

    /**
     * Initialise the block payment method type.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_paynexus_settings', array() );
    }

    /**
     * Returns true when the gateway is enabled and configured.
     */
    public function is_active() {
        return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Register the block checkout JS.
     */
    public function get_payment_method_script_handles() {
        $asset_url = PAYNEXUS_PLUGIN_URL . 'assets/js/paynexus-blocks.js';

        wp_register_script(
            'paynexus-blocks',
            $asset_url,
            array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
            PAYNEXUS_VERSION,
            true
        );

        return array( 'paynexus-blocks' );
    }

    /**
     * Data passed to the block checkout JS.
     */
    public function get_payment_method_data() {
        return array(
            'title'       => $this->get_setting( 'title', __( 'M-Pesa (PayNexus)', 'paynexus' ) ),
            'description' => $this->get_setting( 'description', __( 'Pay securely with M-Pesa. You will receive a payment prompt on your phone.', 'paynexus' ) ),
            'supports'    => array( 'products' ),
            'icon'        => PAYNEXUS_PLUGIN_URL . 'assets/images/logo.png',
        );
    }
}
