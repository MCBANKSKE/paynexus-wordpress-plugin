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
        $this->method_title       = __( 'PayNexus (M-Pesa)', 'paynexus-payment-gateway' );
        $this->method_description = __( 'Accept M-Pesa payments via PayNexus. Customers receive an STK Push on their phone to complete payment.', 'paynexus-payment-gateway' );
        $this->supports           = array( 'products', 'refunds' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title', __( 'M-Pesa (PayNexus)', 'paynexus-payment-gateway' ) );
        $this->description = $this->get_option( 'description', __( 'Pay securely with M-Pesa. You will receive a payment prompt on your phone.', 'paynexus-payment-gateway' ) );
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
                'title'   => __( 'Enable/Disable', 'paynexus-payment-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable PayNexus M-Pesa payments', 'paynexus-payment-gateway' ),
                'default' => 'no',
            ),
            'title'       => array(
                'title'       => __( 'Title', 'paynexus-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Title shown at checkout.', 'paynexus-payment-gateway' ),
                'default'     => __( 'M-Pesa (PayNexus)', 'paynexus-payment-gateway' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'paynexus-payment-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Description shown at checkout.', 'paynexus-payment-gateway' ),
                'default'     => __( 'Pay securely with M-Pesa. You will receive a payment prompt on your phone.', 'paynexus-payment-gateway' ),
                'desc_tip'    => true,
            ),
            'instructions' => array(
                'title'       => __( 'Thank You Page Instructions', 'paynexus-payment-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Displayed on the thank-you page after checkout.', 'paynexus-payment-gateway' ),
                'default'     => __( 'An M-Pesa payment prompt has been sent to your phone. Enter your PIN to complete the payment.', 'paynexus-payment-gateway' ),
                'desc_tip'    => true,
            ),

        );
    }

    /**
     * Render the phone field at checkout.
     */
    public function payment_fields() {
        if ( $this->description ) {
            echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
        }
        ?>
        <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="wc-payment-form pnx-checkout-fields">
            <div class="pnx-phone-field">
                <label for="paynexus_phone" class="pnx-phone-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00A650" stroke-width="2" stroke-linecap="round" style="vertical-align:middle;margin-right:4px;"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    <?php esc_html_e( 'M-Pesa Phone Number', 'paynexus-payment-gateway' ); ?> <span class="required">*</span>
                </label>
                <div class="pnx-phone-input-wrap">
                    <span class="pnx-phone-prefix">+254</span>
                    <input type="tel" class="input-text pnx-phone-input" id="paynexus_phone" name="paynexus_phone"
                           placeholder="<?php esc_attr_e( '0746990866', 'paynexus-payment-gateway' ); ?>"
                           pattern="^(?:\+?254|0)\d{9}$" required
                           oninput="(function(el){var v=el.value.replace(/\D/g,'');var ok=/^(?:254\d{9}|0\d{9}|\d{9})$/.test(v);var fb=el.parentNode.nextElementSibling;if(fb){fb.className='pnx-phone-feedback'+(v.length>0?(ok?' pnx-phone-valid':' pnx-phone-invalid'):'')}})(this)" />
                </div>
                <span class="pnx-phone-feedback" id="pnx-phone-feedback"></span>
                <small class="pnx-phone-hint"><?php esc_html_e( 'Format: 254746990866 or 0746990866', 'paynexus-payment-gateway' ); ?></small>
            </div>
            <div class="pnx-secure-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#00A650" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span><?php esc_html_e( 'Secured by PayNexus', 'paynexus-payment-gateway' ); ?></span>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Validate checkout fields.
     */
    public function validate_fields() {
        // WooCommerce handles nonce verification before calling this method.
        $phone = sanitize_text_field( wp_unslash( $_POST['paynexus_phone'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( empty( $phone ) ) {
            wc_add_notice( __( 'Please enter your M-Pesa phone number.', 'paynexus-payment-gateway' ), 'error' );
            return false;
        }

        if ( ! preg_match( '/^(?:\+?254|0)\d{9}$/', $phone ) ) {
            wc_add_notice( __( 'Please enter a valid Kenyan phone number (e.g., 254746990866).', 'paynexus-payment-gateway' ), 'error' );
            return false;
        }

        return true;
    }

    /**
     * Process the payment.
     */
    public function process_payment( $order_id ) {
        // WooCommerce handles nonce verification (wp_verify_nonce) in the checkout process
        // before calling this method. The nonce is checked in WC_Checkout::process_checkout()
        // which calls this method only after successful verification.
        $order = wc_get_order( $order_id );
        $phone = sanitize_text_field( wp_unslash( $_POST['paynexus_phone'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        // Normalise phone.
        $phone = preg_replace( '/^\+/', '', $phone );
        if ( strpos( $phone, '0' ) === 0 ) {
            $phone = '254' . substr( $phone, 1 );
        }

        $data = array(
            'amount'      => floatval( $order->get_total() ),
            'phone'       => $phone,
            'description' => sprintf(
                /* translators: %s: order number */
                __( 'Order %s', 'paynexus-payment-gateway' ),
                $order->get_order_number()
            ),
        );

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

            $order->update_status( 'pending', __( 'Awaiting M-Pesa payment confirmation.', 'paynexus-payment-gateway' ) );

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }

        $message = $result['message'] ?? __( 'Payment initiation failed. Please try again.', 'paynexus-payment-gateway' );
        wc_add_notice( $message, 'error' );

        return array(
            'result'   => 'failure',
            'messages' => $message,
        );
    }

    /**
     * Thank-you page content with status polling.
     *
     * Outputs a self-contained inline script (vanilla JS, no jQuery)
     * that polls the PayNexus server database for payment status
     * and updates the WooCommerce order automatically.
     */
    public function thankyou_page( $order_id ) {
        $order         = wc_get_order( $order_id );
        $checkout_id   = $order->get_meta( '_paynexus_checkout_request_id' );
        $reference     = $order->get_meta( '_paynexus_reference' );
        $phone         = $order->get_meta( '_paynexus_phone' );
        $mpesa_logo    = PAYNEXUS_PLUGIN_URL . 'assets/images/mpesa-logo.png';

        if ( $order->is_paid() ) {
            return;
        }

        $poll_interval = absint( paynexus()->get_option( 'poll_interval', 3 ) ) * 1000;
        $poll_timeout  = absint( paynexus()->get_option( 'poll_timeout', 120 ) ) * 1000;
        $ajax_url      = admin_url( 'admin-ajax.php' );
        $nonce         = wp_create_nonce( 'paynexus_payment' );

        // Localize script with PHP variables
        wp_localize_script( 'paynexus-woocommerce', 'paynexus_woo_params', array(
            'checkout_id'   => $checkout_id,
            'reference'     => $reference,
            'ajax_url'      => $ajax_url,
            'nonce'         => $nonce,
            'poll_interval' => $poll_interval,
            'poll_timeout'  => $poll_timeout,
        ) );
        ?>
        <div class="pnx-thankyou" id="paynexus-order-status">
            <div class="pnx-thankyou__card" id="pnx-card">
                <div class="pnx-thankyou__header">
                    <img src="<?php echo esc_url( $mpesa_logo ); ?>" alt="M-Pesa" class="pnx-thankyou__logo" />
                </div>

                <div class="pnx-thankyou__phone-anim" id="pnx-phone-anim">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00A650" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                    <p class="pnx-thankyou__prompt"><?php esc_html_e( 'Check your phone and enter your M-Pesa PIN', 'paynexus-payment-gateway' ); ?></p>
                    <?php if ( $phone ) : ?>
                        <span class="pnx-thankyou__phone-num"><?php echo esc_html( substr( $phone, 0, 6 ) . '***' . substr( $phone, -2 ) ); ?></span>
                    <?php endif; ?>
                </div>

                <div class="pnx-thankyou__stepper" id="pnx-stepper">
                    <div class="pnx-step pnx-step--active" id="pnx-step-1">
                        <div class="pnx-step__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg>
                        </div>
                        <span class="pnx-step__label"><?php esc_html_e( 'STK Sent', 'paynexus-payment-gateway' ); ?></span>
                    </div>
                    <div class="pnx-step__line"></div>
                    <div class="pnx-step" id="pnx-step-2">
                        <div class="pnx-step__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        </div>
                        <span class="pnx-step__label"><?php esc_html_e( 'Enter PIN', 'paynexus-payment-gateway' ); ?></span>
                    </div>
                    <div class="pnx-step__line"></div>
                    <div class="pnx-step" id="pnx-step-3">
                        <div class="pnx-step__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
                        </div>
                        <span class="pnx-step__label"><?php esc_html_e( 'Confirming', 'paynexus-payment-gateway' ); ?></span>
                    </div>
                    <div class="pnx-step__line"></div>
                    <div class="pnx-step" id="pnx-step-4">
                        <div class="pnx-step__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
                        </div>
                        <span class="pnx-step__label"><?php esc_html_e( 'Complete', 'paynexus-payment-gateway' ); ?></span>
                    </div>
                </div>

                <div class="pnx-thankyou__status" id="pnx-status">
                    <div class="pnx-thankyou__spinner" id="pnx-spinner"></div>
                    <p class="pnx-thankyou__msg" id="pnx-status-msg"><?php esc_html_e( 'Waiting for M-Pesa confirmation...', 'paynexus-payment-gateway' ); ?></p>
                </div>

                <div class="pnx-thankyou__result" id="pnx-result" style="display:none;"></div>
            </div>
            <p class="pnx-thankyou__footer"><?php esc_html_e( 'Secured by PayNexus', 'paynexus-payment-gateway' ); ?></p>
        </div>
        <?php
    }

    /**
     * Enqueue scripts on checkout and thank-you pages.
     */
    public function enqueue_scripts() {
        if ( is_checkout() ) {
            wp_enqueue_style(
                'paynexus-woocommerce',
                PAYNEXUS_PLUGIN_URL . 'assets/css/paynexus-woocommerce.css',
                array(),
                PAYNEXUS_VERSION
            );
        }

        if ( is_wc_endpoint_url( 'order-received' ) ) {
            wp_enqueue_style(
                'paynexus-woocommerce',
                PAYNEXUS_PLUGIN_URL . 'assets/css/paynexus-woocommerce.css',
                array(),
                PAYNEXUS_VERSION
            );
            wp_enqueue_script(
                'paynexus-woocommerce',
                PAYNEXUS_PLUGIN_URL . 'assets/js/paynexus-woocommerce.js',
                array(),
                PAYNEXUS_VERSION,
                true
            );
        }
    }
}
