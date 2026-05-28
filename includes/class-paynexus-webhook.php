<?php
/**
 * PayNexus Webhook handler.
 *
 * Registers a WP REST API endpoint to receive payment webhooks
 * from the PayNexus platform.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayNexus_Webhook {

    /**
     * Register REST API routes.
     */
    public static function register_routes() {
        register_rest_route( 'paynexus/v1', '/webhook', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle' ),
            'permission_callback' => array( __CLASS__, 'verify_signature' ),
        ) );
    }

    /**
     * Verify the incoming webhook signature.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public static function verify_signature( $request ) {
        $secret = paynexus()->get_option( 'webhook_secret', '' );

        // Webhook secret is required for security.
        if ( empty( $secret ) ) {
            return new WP_Error(
                'paynexus_no_secret',
                __( 'Webhook secret not configured', 'paynexus-payment-gateway' ),
                array( 'status' => 403 )
            );
        }

        $signature = $request->get_header( 'X-PayNexus-Signature' );
        if ( empty( $signature ) ) {
            return new WP_Error(
                'paynexus_missing_signature',
                __( 'Missing webhook signature.', 'paynexus-payment-gateway' ),
                array( 'status' => 403 )
            );
        }

        $body     = $request->get_body();
        $expected = hash_hmac( 'sha256', $body, $secret );

        if ( ! hash_equals( $expected, $signature ) ) {
            return new WP_Error(
                'paynexus_invalid_signature',
                __( 'Invalid webhook signature.', 'paynexus-payment-gateway' ),
                array( 'status' => 403 )
            );
        }

        // Replay protection.
        $timestamp = $request->get_header( 'X-PayNexus-Timestamp' );
        if ( $timestamp ) {
            $tolerance = 300; // 5 minutes
            if ( abs( time() - intval( $timestamp ) ) > $tolerance ) {
                return new WP_Error(
                    'paynexus_expired_webhook',
                    __( 'Webhook timestamp outside tolerance window.', 'paynexus-payment-gateway' ),
                    array( 'status' => 403 )
                );
            }
        }

        return true;
    }

    /**
     * Handle the incoming webhook payload.
     *
     * Expected payload:
     * {
     *   "event": "payment.completed",
     *   "timestamp": "...",
     *   "data": {
     *     "payment_id": 42,
     *     "reference": "PNX...",
     *     "amount": "1000.00",
     *     "currency": "KES",
     *     "phone": "254...",
     *     "status": "completed",
     *     "checkout_request_id": "ws_CO_...",
     *     "transaction_id": "SH1234ABCDE",
     *     "provider_transaction_id": "SH1234ABCDE",
     *     "provider_reference": "...",
     *     "payer_name": "JOHN DOE",
     *     ...
     *   }
     * }
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle( $request ) {
        $payload = $request->get_json_params();

        $event = $payload['event'] ?? $payload['status'] ?? 'unknown';
        $data  = $payload['data'] ?? $payload;

        $reference           = $data['reference'] ?? null;
        $checkout_request_id = $data['checkout_request_id'] ?? null;
        $payment_id          = $data['payment_id'] ?? null;

        // Try to find an existing local record.
        $local = self::find_local_payment( $reference, $checkout_request_id, $payment_id );

        if ( ! $local ) {
            // Create a record for webhooks that arrive before local record exists.
            $failure_reason = $data['failure_reason'] ?? null;
            if ( $failure_reason ) {
                $client = paynexus()->client;
                if ( $client && method_exists( $client, 'map_failure_reason' ) ) {
                    $failure_reason = $client->map_failure_reason( $failure_reason );
                }
            }

            $insert_id = PayNexus_Payment::insert_payment( array(
                'paynexus_payment_id' => $payment_id,
                'reference'           => $reference,
                'checkout_request_id' => $checkout_request_id,
                'transaction_id'      => $data['transaction_id'] ?? $data['provider_transaction_id'] ?? null,
                'amount'              => floatval( $data['amount'] ?? 0 ),
                'currency'            => $data['currency'] ?? 'KES',
                'phone'               => $data['phone'] ?? null,
                'status'              => self::resolve_status( $event ),
                'provider_reference'  => $data['provider_reference'] ?? null,
                'failure_reason'      => $failure_reason,
                'payer_name'          => $data['payer_name'] ?? null,
                'account_reference'   => $data['account_reference'] ?? null,
            ) );

            if ( $insert_id ) {
                $local = PayNexus_Payment::find_by( 'id', $insert_id );
            }
        }

        if ( ! $local ) {
            return new WP_REST_Response( array( 'received' => true ), 200 );
        }

        // Process event.
        if ( in_array( $event, array( 'payment.completed', 'completed' ), true ) ) {
            self::handle_completed( $local, $data );
        } elseif ( in_array( $event, array( 'payment.failed', 'failed' ), true ) ) {
            self::handle_failed( $local, $data );
        }

        return new WP_REST_Response( array( 'received' => true ), 200 );
    }

    // -----------------------------------------------------------------
    //  Handlers
    // -----------------------------------------------------------------

    private static function handle_completed( $payment, $data ) {
        PayNexus_Payment::update_payment( $payment->id, array(
            'status'             => 'completed',
            'transaction_id'     => $data['transaction_id'] ?? $data['provider_transaction_id'] ?? $payment->transaction_id,
            'provider_reference' => $data['provider_reference'] ?? $payment->provider_reference,
            'payer_name'         => $data['payer_name'] ?? $payment->payer_name,
        ) );

        $updated = PayNexus_Payment::find_by( 'id', $payment->id );

        /**
         * Fires when a payment is completed.
         *
         * @param object $payment Local payment record.
         * @param array  $data    Raw webhook data.
         */
        do_action( 'paynexus_payment_completed', $updated, $data );

        // WooCommerce: complete the order if linked.
        if ( ! empty( $payment->order_id ) && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $payment->order_id );
            if ( $order && ! $order->is_paid() ) {
                $txn_id = $data['transaction_id'] ?? $data['provider_transaction_id'] ?? '';
                $order->payment_complete( $txn_id );
                $order->add_order_note(
                    sprintf(
                        /* translators: 1: reference, 2: transaction ID */
                        __( 'PayNexus payment completed. Ref: %1$s | Txn: %2$s', 'paynexus-payment-gateway' ),
                        $data['reference'] ?? '',
                        $txn_id
                    )
                );
            }
        }
    }

    private static function handle_failed( $payment, $data ) {
        $reason = $data['failure_reason']
            ?? $data['reason']
            ?? $data['provider_reference']
            ?? 'Unknown';

        // Apply failure reason mapping for consistent user experience
        if ( $reason && $reason !== 'Unknown' ) {
            $client = paynexus()->client;
            if ( $client && method_exists( $client, 'map_failure_reason' ) ) {
                $reason = $client->map_failure_reason( $reason );
            }
        }

        PayNexus_Payment::update_payment( $payment->id, array(
            'status'         => 'failed',
            'failure_reason' => $reason,
        ) );

        $updated = PayNexus_Payment::find_by( 'id', $payment->id );

        /**
         * Fires when a payment fails.
         *
         * @param object $payment Local payment record.
         * @param array  $data    Raw webhook data.
         * @param string $reason  Failure reason.
         */
        do_action( 'paynexus_payment_failed', $updated, $data, $reason );

        // WooCommerce: mark order as failed if linked.
        if ( ! empty( $payment->order_id ) && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $payment->order_id );
            if ( $order && ! $order->is_paid() ) {
                $order->update_status( 'failed', sprintf(
                    /* translators: %s: failure reason */
                    __( 'PayNexus payment failed: %s', 'paynexus-payment-gateway' ),
                    $reason
                ) );
            }
        }
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    /**
     * Find a local payment by reference, checkout ID, or payment ID.
     */
    private static function find_local_payment( $reference, $checkout_request_id, $payment_id ) {
        $local = null;

        if ( $reference ) {
            $local = PayNexus_Payment::find_by( 'reference', $reference );
        }

        if ( ! $local && $checkout_request_id ) {
            $local = PayNexus_Payment::find_by( 'checkout_request_id', $checkout_request_id );
        }

        if ( ! $local && $payment_id ) {
            $local = PayNexus_Payment::find_by( 'paynexus_payment_id', $payment_id );
        }

        return $local;
    }

    /**
     * Map event names to a status string.
     */
    private static function resolve_status( $event ) {
        $map = array(
            'payment.completed' => 'completed',
            'completed'         => 'completed',
            'payment.failed'    => 'failed',
            'failed'            => 'failed',
        );

        return $map[ $event ] ?? 'pending';
    }
}
