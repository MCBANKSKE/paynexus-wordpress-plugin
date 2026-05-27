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
                           placeholder="<?php esc_attr_e( '712345678', 'paynexus-payment-gateway' ); ?>"
                           pattern="^(?:\+?254|0)\d{9}$" required
                           oninput="(function(el){var v=el.value.replace(/\D/g,'');var ok=/^(?:254\d{9}|0\d{9}|\d{9})$/.test(v);var fb=el.parentNode.nextElementSibling;if(fb){fb.className='pnx-phone-feedback'+(v.length>0?(ok?' pnx-phone-valid':' pnx-phone-invalid'):'')}})(this)" />
                </div>
                <span class="pnx-phone-feedback" id="pnx-phone-feedback"></span>
                <small class="pnx-phone-hint"><?php esc_html_e( 'Format: 254712345678 or 0712345678', 'paynexus-payment-gateway' ); ?></small>
            </div>
            <div class="pnx-secure-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#00A650" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span><?php esc_html_e( 'Secured by PayNexus', 'paynexus-payment-gateway' ); ?></span>
            </div>
        </fieldset>
        <style>
        .pnx-checkout-fields{border:1px solid #e0e0e0!important;border-radius:8px;padding:16px!important;background:#fafffe}
        .pnx-phone-label{display:flex!important;align-items:center;font-weight:600;font-size:14px;color:#333;margin-bottom:8px}
        .pnx-phone-input-wrap{display:flex;align-items:center;border:1px solid #ccc;border-radius:6px;overflow:hidden;transition:border-color 0.2s}
        .pnx-phone-input-wrap:focus-within{border-color:#00A650;box-shadow:0 0 0 2px rgba(0,166,80,0.12)}
        .pnx-phone-prefix{padding:10px 12px;background:#f5f5f5;color:#333;font-weight:600;font-size:14px;border-right:1px solid #e0e0e0;white-space:nowrap}
        .pnx-phone-input{border:none!important;box-shadow:none!important;padding:10px 12px!important;font-size:15px;flex:1;outline:none}
        .pnx-phone-input:focus{box-shadow:none!important}
        .pnx-phone-feedback{display:block;height:4px;border-radius:2px;margin-top:4px;transition:all 0.3s}
        .pnx-phone-valid{background:#00A650}
        .pnx-phone-invalid{background:#e53935}
        .pnx-phone-hint{display:block;margin-top:4px;font-size:12px;color:#888}
        .pnx-secure-badge{display:flex;align-items:center;gap:4px;margin-top:12px;font-size:11px;color:#00A650}
        </style>
        <?php
    }

    /**
     * Validate checkout fields.
     */
    public function validate_fields() {
        $phone = sanitize_text_field( wp_unslash( $_POST['paynexus_phone'] ?? '' ) );

        if ( empty( $phone ) ) {
            wc_add_notice( __( 'Please enter your M-Pesa phone number.', 'paynexus-payment-gateway' ), 'error' );
            return false;
        }

        if ( ! preg_match( '/^(?:\+?254|0)\d{9}$/', $phone ) ) {
            wc_add_notice( __( 'Please enter a valid Kenyan phone number (e.g., 254712345678).', 'paynexus-payment-gateway' ), 'error' );
            return false;
        }

        return true;
    }

    /**
     * Process the payment.
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $phone = sanitize_text_field( wp_unslash( $_POST['paynexus_phone'] ?? '' ) );

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

        <style>
        .pnx-thankyou{max-width:480px;margin:24px auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
        .pnx-thankyou__card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:28px 24px;box-shadow:0 4px 16px rgba(0,0,0,0.06);text-align:center}
        .pnx-thankyou__header{margin-bottom:20px}
        .pnx-thankyou__logo{max-height:40px;width:auto}
        .pnx-thankyou__phone-anim{margin:16px 0 24px;animation:pnx-pulse 2s ease-in-out infinite}
        .pnx-thankyou__phone-anim svg{margin:0 auto;display:block}
        .pnx-thankyou__prompt{font-size:15px;color:#333;font-weight:600;margin:10px 0 4px}
        .pnx-thankyou__phone-num{font-size:13px;color:#666;background:#f5f5f5;padding:2px 10px;border-radius:12px;display:inline-block}
        .pnx-thankyou__stepper{display:flex;align-items:center;justify-content:center;gap:0;margin:20px 0;padding:0 8px}
        .pnx-step{display:flex;flex-direction:column;align-items:center;gap:6px;flex:0 0 auto}
        .pnx-step__icon{width:36px;height:36px;border-radius:50%;background:#f0f0f0;color:#999;display:flex;align-items:center;justify-content:center;transition:all 0.4s ease}
        .pnx-step--active .pnx-step__icon{background:#e8f5e9;color:#00A650}
        .pnx-step--done .pnx-step__icon{background:#00A650;color:#fff}
        .pnx-step__label{font-size:11px;color:#999;font-weight:500;white-space:nowrap;transition:color 0.3s}
        .pnx-step--active .pnx-step__label{color:#00A650;font-weight:600}
        .pnx-step--done .pnx-step__label{color:#00A650}
        .pnx-step__line{flex:1;height:2px;background:#e0e0e0;min-width:20px;margin:0 4px;transition:background 0.4s;align-self:center;margin-bottom:18px}
        .pnx-step__line.pnx-step__line--done{background:#00A650}
        .pnx-thankyou__status{padding:12px 0}
        .pnx-thankyou__spinner{width:32px;height:32px;border:3px solid #e0e0e0;border-top-color:#00A650;border-radius:50%;animation:pnx-spin 0.8s linear infinite;margin:0 auto 10px}
        .pnx-thankyou__msg{font-size:14px;color:#555;margin:0}
        .pnx-thankyou__result{margin-top:16px;padding:16px;border-radius:8px;text-align:left}
        .pnx-thankyou__result--success{background:#e8f5e9;border:1px solid #a5d6a7}
        .pnx-thankyou__result--failed{background:#fce8e6;border:1px solid #f5c6cb}
        .pnx-thankyou__result--timeout{background:#fff8e1;border:1px solid #ffe082}
        .pnx-thankyou__result h4{margin:0 0 8px;font-size:16px}
        .pnx-thankyou__result p{margin:4px 0;font-size:13px;color:#555}
        .pnx-thankyou__result .pnx-txn-id{font-family:monospace;background:#f5f5f5;padding:2px 8px;border-radius:4px;font-size:13px}
        .pnx-thankyou__footer{text-align:center;font-size:11px;color:#aaa;margin-top:12px}
        @keyframes pnx-spin{to{transform:rotate(360deg)}}
        @keyframes pnx-pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}
        </style>

        <script>
        (function(){
            var checkoutId = <?php echo wp_json_encode( $checkout_id ); ?>;
            var reference  = <?php echo wp_json_encode( $reference ); ?>;
            var ajaxUrl    = <?php echo wp_json_encode( $ajax_url ); ?>;
            var nonce      = <?php echo wp_json_encode( $nonce ); ?>;
            var interval   = <?php echo (int) $poll_interval; ?> || 3000;
            var timeout    = <?php echo (int) $poll_timeout; ?> || 120000;
            var start      = Date.now();
            var spinner    = document.getElementById('pnx-spinner');
            var msg        = document.getElementById('pnx-status-msg');
            var result     = document.getElementById('pnx-result');
            var phoneAnim  = document.getElementById('pnx-phone-anim');
            var steps      = [document.getElementById('pnx-step-1'),document.getElementById('pnx-step-2'),document.getElementById('pnx-step-3'),document.getElementById('pnx-step-4')];
            var lines      = document.querySelectorAll('.pnx-step__line');
            var pollCount  = 0;

            function setStep(n) {
                for (var i = 0; i < steps.length; i++) {
                    steps[i].className = 'pnx-step' + (i < n ? ' pnx-step--done' : (i === n ? ' pnx-step--active' : ''));
                }
                for (var j = 0; j < lines.length; j++) {
                    lines[j].className = 'pnx-step__line' + (j < n ? ' pnx-step__line--done' : '');
                }
            }

            if (!checkoutId && !reference) return;

            setTimeout(function(){ setStep(1); }, 5000);
            setTimeout(function(){ setStep(2); }, 15000);

            var timer = setInterval(function(){
                pollCount++;
                if (Date.now() - start > timeout) {
                    clearInterval(timer);
                    if (spinner) spinner.style.display = 'none';
                    if (phoneAnim) phoneAnim.style.display = 'none';
                    if (msg) msg.style.display = 'none';
                    if (result) {
                        result.style.display = 'block';
                        result.className = 'pnx-thankyou__result pnx-thankyou__result--timeout';
                        result.innerHTML = '<h4 style="color:#f57f17;">&#9888; Confirmation Timed Out</h4>'
                            + '<p>Your payment may still be processing. Please check your order status or contact support if the amount was deducted.</p>';
                    }
                    return;
                }

                var body = new FormData();
                body.append('action', 'paynexus_check_status');
                body.append('nonce', nonce);
                if (checkoutId) body.append('checkout_request_id', checkoutId);
                if (reference) body.append('reference', reference);

                fetch(ajaxUrl, {method:'POST', body:body, credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if (!res || !res.success) return;
                        var d = res.data || {};
                        var status = d.status || '';

                        if (status === 'completed') {
                            clearInterval(timer);
                            setStep(4);
                            if (spinner) spinner.style.display = 'none';
                            if (phoneAnim) phoneAnim.style.display = 'none';
                            if (msg) msg.style.display = 'none';
                            var txnId = d.provider_transaction_id || d.transaction_id || '';
                            if (result) {
                                result.style.display = 'block';
                                result.className = 'pnx-thankyou__result pnx-thankyou__result--success';
                                result.innerHTML = '<h4 style="color:#1b5e20;">&#10003; Payment Successful!</h4>'
                                    + (txnId ? '<p><strong>Transaction ID:</strong> <span class="pnx-txn-id">' + txnId + '</span></p>' : '')
                                    + '<p style="color:#888;font-size:12px;margin-top:8px;">Redirecting...</p>';
                            }
                            setTimeout(function(){ window.location.reload(); }, 2500);
                        } else if (status === 'failed') {
                            clearInterval(timer);
                            if (spinner) spinner.style.display = 'none';
                            if (phoneAnim) phoneAnim.style.display = 'none';
                            if (msg) msg.style.display = 'none';
                            if (result) {
                                result.style.display = 'block';
                                result.className = 'pnx-thankyou__result pnx-thankyou__result--failed';
                                result.innerHTML = '<h4 style="color:#c62828;">&#10007; Payment Failed</h4>'
                                    + '<p>' + (d.failure_reason || d.result_description || 'The payment was not completed.') + '</p>'
                                    + '<p style="margin-top:8px;"><a href="javascript:window.location.reload()" style="color:#1976d2;text-decoration:underline;">Try again</a></p>';
                            }
                        }
                    })
                    .catch(function(){});
            }, interval);
        })();
        </script>
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
