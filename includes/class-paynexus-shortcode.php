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
            'description'       => __( 'Payment via PayNexus', 'paynexus' ),
            'reference'         => 'PAYNEXUS',
            'button_text'       => __( 'Pay with M-Pesa', 'paynexus' ),
            'class'             => '',
            'show_amount_field' => '',
        ), $atts, 'paynexus_payment_form' );

        $fixed_amount     = floatval( $atts['amount'] );
        $show_amount      = empty( $atts['amount'] ) || 'yes' === $atts['show_amount_field'];
        $currency         = paynexus()->get_option( 'currency', 'KES' );

        wp_enqueue_style( 'paynexus-payment' );
        wp_enqueue_script( 'paynexus-payment' );

        ob_start();
        ?>
        <div class="paynexus-payment-form-wrap <?php echo esc_attr( $atts['class'] ); ?>" id="paynexus-payment-form">
            <form class="paynexus-form" data-paynexus-form>
                <?php if ( $show_amount ) : ?>
                    <div class="paynexus-field">
                        <label for="paynexus-amount"><?php printf( esc_html__( 'Amount (%s)', 'paynexus' ), esc_html( $currency ) ); ?></label>
                        <input type="number" id="paynexus-amount" name="amount"
                               min="1" step="0.01" required
                               value="<?php echo $fixed_amount > 0 ? esc_attr( $fixed_amount ) : ''; ?>"
                               placeholder="<?php esc_attr_e( 'Enter amount', 'paynexus' ); ?>"
                               <?php echo $fixed_amount > 0 && 'yes' !== $atts['show_amount_field'] ? 'readonly' : ''; ?> />
                    </div>
                <?php else : ?>
                    <input type="hidden" name="amount" value="<?php echo esc_attr( $fixed_amount ); ?>" />
                <?php endif; ?>

                <div class="paynexus-field">
                    <label for="paynexus-phone"><?php esc_html_e( 'M-Pesa Phone Number', 'paynexus' ); ?></label>
                    <input type="tel" id="paynexus-phone" name="phone"
                           required pattern="^(?:\+?254|0)\d{9}$"
                           placeholder="<?php esc_attr_e( '254712345678', 'paynexus' ); ?>" />
                    <small class="paynexus-hint"><?php esc_html_e( 'Format: 254712345678', 'paynexus' ); ?></small>
                </div>

                <input type="hidden" name="account_reference" value="<?php echo esc_attr( $atts['reference'] ); ?>" />
                <input type="hidden" name="description" value="<?php echo esc_attr( $atts['description'] ); ?>" />

                <button type="submit" class="paynexus-btn" data-paynexus-submit>
                    <?php echo esc_html( $atts['button_text'] ); ?>
                </button>
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

        ob_start();
        ?>
        <div class="paynexus-status-checker" id="paynexus-status-checker">
            <form class="paynexus-form" data-paynexus-status-form>
                <div class="paynexus-field">
                    <label for="paynexus-ref"><?php esc_html_e( 'Payment Reference', 'paynexus' ); ?></label>
                    <input type="text" id="paynexus-ref" name="reference" required
                           placeholder="<?php esc_attr_e( 'PNX...', 'paynexus' ); ?>" />
                </div>
                <button type="submit" class="paynexus-btn">
                    <?php esc_html_e( 'Check Status', 'paynexus' ); ?>
                </button>
            </form>
            <div class="paynexus-result" data-paynexus-status-result style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
