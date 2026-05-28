<?php
/**
 * PayNexus shortcodes.
 *
 * [paynexus_payment_form]  — Renders a payment form with M-Pesa STK Push.
 * [paynexus_payment_status] — Renders a payment status checker.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayNexus_Shortcode {

    public static function init() {
        add_shortcode( 'paynexus_payment_form', array( __CLASS__, 'payment_form' ) );
        add_shortcode( 'paynexus_payment_status', array( __CLASS__, 'payment_status' ) );
    }

    /**
     * Render the payment form.
     *
     * Usage:
     *   [paynexus_payment_form amount="1000" description="Order payment" reference="ORD-001"]
     *   [paynexus_payment_form]  (amount entered by user)
     *
     * @param array $atts Shortcode attributes.
     */
    public static function payment_form( $atts ) {
        $atts = shortcode_atts( array(
            'amount'            => '',
            'description'       => __( 'Payment via PayNexus', 'paynexus-payment-gateway' ),
            'reference'         => 'PAYNEXUS',
            'button_text'       => __( 'Pay with M-Pesa', 'paynexus-payment-gateway' ),
            'class'             => '',
            'show_amount_field' => '',
        ), $atts, 'paynexus_payment_form' );

        $fixed_amount     = floatval( $atts['amount'] );
        $show_amount      = empty( $atts['amount'] ) || 'yes' === $atts['show_amount_field'];
        $currency         = paynexus()->get_option( 'currency', 'KES' );

        wp_enqueue_style( 'paynexus-payment' );
        wp_enqueue_script( 'paynexus-payment' );

        $mpesa_logo = PAYNEXUS_PLUGIN_URL . 'assets/images/mpesa-logo.png';

        ob_start();
        ?>
        <div class="paynexus-payment-form-wrap <?php echo esc_attr( $atts['class'] ); ?>" id="paynexus-payment-form">
            <form class="paynexus-form pnx-form-branded" data-paynexus-form>
                <div class="pnx-form-header">
                    <img src="<?php echo esc_url( $mpesa_logo ); ?>" alt="M-Pesa" class="pnx-form-logo" />
                    <span class="pnx-form-title"><?php esc_html_e( 'M-Pesa Payment', 'paynexus-payment-gateway' ); ?></span>
                </div>

                <?php if ( $show_amount ) : ?>
                    <div class="paynexus-field">
                        /* translators: %s: currency code */
                        <label for="paynexus-amount"><?php printf( esc_html__( 'Amount (%s)', 'paynexus-payment-gateway' ), esc_html( $currency ) ); ?></label>
                        <input type="number" id="paynexus-amount" name="amount"
                               min="1" step="0.01" required
                               value="<?php echo $fixed_amount > 0 ? esc_attr( $fixed_amount ) : ''; ?>"
                               placeholder="<?php esc_attr_e( 'Enter amount', 'paynexus-payment-gateway' ); ?>"
                               <?php echo $fixed_amount > 0 && 'yes' !== $atts['show_amount_field'] ? 'readonly' : ''; ?> />
                    </div>
                <?php else : ?>
                    <input type="hidden" name="amount" value="<?php echo esc_attr( $fixed_amount ); ?>" />
                <?php endif; ?>

                <div class="paynexus-field">
                    <label for="paynexus-phone"><?php esc_html_e( 'M-Pesa Phone Number', 'paynexus-payment-gateway' ); ?></label>
                    <div class="pnx-phone-wrap">
                        <span class="pnx-phone-prefix">+254</span>
                        <input type="tel" id="paynexus-phone" name="phone"
                               required pattern="^(?:\+?254|0)\d{9}$"
                               placeholder="<?php esc_attr_e( '0746990866', 'paynexus-payment-gateway' ); ?>"
                               class="pnx-phone-input" />
                    </div>
                    <small class="paynexus-hint"><?php esc_html_e( 'Enter your M-Pesa registered phone number', 'paynexus-payment-gateway' ); ?></small>
                </div>

                <input type="hidden" name="description" value="<?php echo esc_attr( $atts['description'] ); ?>" />

                <div class="pnx-summary" id="pnx-payment-summary" style="display:none;">
                    <div class="pnx-summary__row">
                        <span><?php esc_html_e( 'Amount', 'paynexus-payment-gateway' ); ?></span>
                        <strong id="pnx-summary-amount"></strong>
                    </div>
                    <div class="pnx-summary__row">
                        <span><?php esc_html_e( 'Phone', 'paynexus-payment-gateway' ); ?></span>
                        <strong id="pnx-summary-phone"></strong>
                    </div>
                    <div class="pnx-summary__row">
                        <span><?php esc_html_e( 'Description', 'paynexus-payment-gateway' ); ?></span>
                        <strong id="pnx-summary-desc"><?php echo esc_html( $atts['description'] ); ?></strong>
                    </div>
                </div>

                <button type="submit" class="paynexus-btn" data-paynexus-submit>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    <?php echo esc_html( $atts['button_text'] ); ?>
                </button>

                <div class="pnx-secured">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/></svg>
                    <?php esc_html_e( 'Secured by PayNexus', 'paynexus-payment-gateway' ); ?>
                </div>
            </form>

            <div class="paynexus-status-area" data-paynexus-status style="display:none;">
                <div class="paynexus-spinner"></div>
                <p class="paynexus-status-message" data-paynexus-message></p>
            </div>

            <div class="paynexus-result" data-paynexus-result style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a payment status checker.
     *
     * Usage:
     *   [paynexus_payment_status]
     */
    public static function payment_status( $atts ) {
        wp_enqueue_style( 'paynexus-payment' );
        wp_enqueue_script( 'paynexus-payment' );

        $mpesa_logo = PAYNEXUS_PLUGIN_URL . 'assets/images/mpesa-logo.png';

        ob_start();
        ?>
        <div class="paynexus-status-checker" id="paynexus-status-checker">
            <form class="paynexus-form pnx-form-branded" data-paynexus-status-form>
                <div class="pnx-form-header">
                    <img src="<?php echo esc_url( $mpesa_logo ); ?>" alt="M-Pesa" class="pnx-form-logo" />
                    <span class="pnx-form-title"><?php esc_html_e( 'Payment Status', 'paynexus-payment-gateway' ); ?></span>
                </div>
                <div class="paynexus-field">
                    <label for="paynexus-ref"><?php esc_html_e( 'Payment Reference', 'paynexus-payment-gateway' ); ?></label>
                    <input type="text" id="paynexus-ref" name="reference" required
                           placeholder="<?php esc_attr_e( 'PNX...', 'paynexus-payment-gateway' ); ?>" />
                </div>
                <button type="submit" class="paynexus-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <?php esc_html_e( 'Check Status', 'paynexus-payment-gateway' ); ?>
                </button>
            </form>
            <div class="pnx-receipt" data-paynexus-status-result style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
