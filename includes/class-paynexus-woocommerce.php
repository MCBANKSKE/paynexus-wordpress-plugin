<?php
/**
 * PayNexus WooCommerce Payment Gateway.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayNexus_WooCommerce extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'paynexus';
        $this->icon               = PAYNEXUS_PLUGIN_URL . 'assets/images/mpesa-logo.png';
        $this->has_fields         = true;
        $this->method_title       = __( 'PayNexus (M-Pesa)', 'paynexus' );
        $this->method_description = __( 'Accept M-Pesa payments via PayNexus. Customers receive an STK Push on their phone to complete payment.', 'paynexus' );
        $this->supports           = array( 'products', 'refunds' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title', __( 'M-Pesa (PayNexus)', 'paynexus' ) );
        $this->description = $this->get_option( 'description', __( 'Pay securely with M-Pesa. You will receive a payment prompt on your phone.', 'paynexus' ) );
        $this->enabled     = $this->get_option( 'enabled', 'no' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Add the gateway to WooCommerce.
     */
    public static function add_gateway( $gateways ) {
        $gateways[] = __CLASS__;
        return $gateways;
    }

    /**
     * Gateway settings fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'     => array(
                'title'   => __( 'Enable/Disable', 'paynexus' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable PayNexus M-Pesa payments', 'paynexus' ),
                'default' => 'no',
            ),
            'title'       => array(
                'title'       => __( 'Title', 'paynexus' ),
                'type'        => 'text',
                'description' => __( 'Title shown at checkout.', 'paynexus' ),
                'default'     => __( 'M-Pesa (PayNexus)', 'paynexus' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'paynexus' ),
                'type'        => 'textarea',
                'description' => __( 'Description shown at checkout.', 'paynexus' ),
                'default'     => __( 'Pay securely with M-Pesa. You will receive a payment prompt on your phone.', 'paynexus' ),
                'desc_tip'    => true,
            ),
            'instructions' => array(
                'title'       => __( 'Thank You Page Instructions', 'paynexus' ),
                'type'        => 'textarea',
                'description' => __( 'Displayed on the thank-you page after checkout.', 'paynexus' ),
                'default'     => __( 'An M-Pesa payment prompt has been sent to your phone. Enter your PIN to complete the payment.', 'paynexus' ),
                'desc_tip'    => true,
            ),
            'payment_account_id' => array(
                'title'       => __( 'Payment Account ID', 'paynexus' ),
                'type'        => 'text',
                'description' => __( 'Optional. Leave blank to auto-resolve from your PayNexus account.', 'paynexus' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Render the phone field at checkout.
     */
    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }
        ?>
        <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="wc-payment-form">
            <p class="form-row form-row-wide">
                <label for="paynexus_phone"><?php esc_html_e( 'M-Pesa Phone Number', 'paynexus' ); ?> <span class="required">*</span></label>
                <input type="tel" class="input-text" id="paynexus_phone" name="paynexus_phone"
                       placeholder="<?php esc_attr_e( '254712345678', 'paynexus' ); ?>"
                       pattern="^(?:\+?254|0)\d{9}$" required />
                <small><?php esc_html_e( 'Format: 254712345678', 'paynexus' ); ?></small>
            </p>
        </fieldset>
        <?php
    }

    /**
     * Validate checkout fields.
     */
    public function validate_fields() {
        $phone = sanitize_text_field( $_POST['paynexus_phone'] ?? '' );

        if ( empty( $phone ) ) {
            wc_add_notice( __( 'Please enter your M-Pesa phone number.', 'paynexus' ), 'error' );
            return false;
        }

        if ( ! preg_match( '/^(?:\+?254|0)\d{9}$/', $phone ) ) {
            wc_add_notice( __( 'Please enter a valid Kenyan phone number (e.g., 254712345678).', 'paynexus' ), 'error' );
            return false;
        }

        return true;
    }

    /**
     * Process the payment.
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $phone = sanitize_text_field( $_POST['paynexus_phone'] ?? '' );

        // Normalise phone.
        $phone = preg_replace( '/^\+/', '', $phone );
        if ( strpos( $phone, '0' ) === 0 ) {
            $phone = '254' . substr( $phone, 1 );
        }

        $data = array(
            'amount'            => floatval( $order->get_total() ),
            'phone'             => $phone,
            'account_reference' => substr( $order->get_order_number(), 0, 12 ),
            'description'       => sprintf(
                /* translators: %s: order number */
                __( 'Order %s', 'paynexus' ),
                $order->get_order_number()
            ),
        );

        $payment_account_id = $this->get_option( 'payment_account_id' );
        if ( ! empty( $payment_account_id ) ) {
            $data['payment_account_id'] = intval( $payment_account_id );
        }

        $result = paynexus()->client->initiate_payment( $data );

        if ( ! empty( $result['success'] ) ) {
            $payment_data = $result['data'] ?? array();

            $order->update_meta_data( '_paynexus_checkout_request_id', $payment_data['checkout_request_id'] ?? '' );
            $order->update_meta_data( '_paynexus_reference', $payment_data['reference'] ?? '' );
            $order->update_meta_data( '_paynexus_phone', $phone );
            $order->save();

            // Link the local payment to this order.
            $local = PayNexus_Payment::find_by( 'checkout_request_id', $payment_data['checkout_request_id'] ?? '' );
            if ( $local ) {
                PayNexus_Payment::update_payment( $local->id, array( 'order_id' => $order_id ) );
            }

            $order->update_status( 'pending', __( 'Awaiting M-Pesa payment confirmation.', 'paynexus' ) );

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }

        $message = $result['message'] ?? __( 'Payment initiation failed. Please try again.', 'paynexus' );
        wc_add_notice( $message, 'error' );

        return array(
            'result'   => 'failure',
            'messages' => $message,
        );
    }

    /**
     * Thank-you page content with status polling.
     */
    public function thankyou_page( $order_id ) {
        $order         = wc_get_order( $order_id );
        $instructions  = $this->get_option( 'instructions', '' );
        $checkout_id   = $order->get_meta( '_paynexus_checkout_request_id' );
        $reference     = $order->get_meta( '_paynexus_reference' );

        if ( $instructions ) {
            echo wpautop( wptexturize( $instructions ) );
        }

        if ( $order->is_paid() ) {
            return;
        }

        wp_enqueue_script( 'paynexus-payment' );
        wp_enqueue_style( 'paynexus-payment' );
        ?>
        <div class="paynexus-order-status" id="paynexus-order-status"
             data-checkout-id="<?php echo esc_attr( $checkout_id ); ?>"
             data-reference="<?php echo esc_attr( $reference ); ?>"
             data-order-url="<?php echo esc_url( $order->get_view_order_url() ); ?>">
            <div class="paynexus-spinner"></div>
            <p class="paynexus-status-message"><?php esc_html_e( 'Waiting for M-Pesa confirmation...', 'paynexus' ); ?></p>
        </div>
        <?php
    }

    /**
     * Enqueue scripts on checkout and thank-you pages.
     */
    public function enqueue_scripts() {
        if ( is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
            wp_enqueue_style( 'paynexus-payment' );
            wp_enqueue_script( 'paynexus-payment' );
        }
    }
}
