<?php
/**
 * PayNexus API Client.
 *
 * Handles all communication with the PayNexus platform API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayNexus_Client {

    /** @var string */
    private $secret_key;

    /** @var string */
    private $public_key;

    /** @var string */
    private $base_url;

    /** @var int */
    private $timeout;

    /** @var int */
    private $retries;

    public function __construct( $secret_key, $base_url, $public_key = '' ) {
        $this->secret_key = $secret_key;
        $this->public_key = $public_key;
        $this->base_url   = rtrim( $base_url, '/' );
        $this->timeout    = absint( paynexus()->get_option( 'http_timeout', 30 ) );
        $this->retries    = absint( paynexus()->get_option( 'http_retries', 2 ) );
    }

    // -----------------------------------------------------------------
    //  Merchant
    // -----------------------------------------------------------------

    /**
     * Get authenticated merchant information.
     */
    public function get_merchant() {
        return $this->get( '/api/merchant' );
    }

    /**
     * Get merchant businesses.
     */
    public function get_businesses() {
        return $this->get( '/api/merchant/businesses' );
    }

    /**
     * Get merchant payment accounts.
     */
    public function get_payment_accounts() {
        return $this->get( '/api/merchant/payment-accounts' );
    }

    // -----------------------------------------------------------------
    //  Payments — initiate
    // -----------------------------------------------------------------

    /**
     * Initiate an M-Pesa STK Push payment.
     *
     * @param array $data {
     *     @type int    $payment_account_id Optional. Auto-resolved if omitted.
     *     @type float  $amount             Required.
     *     @type string $phone              Required.
     *     @type string $description        Optional.
     *     @type array  $metadata           Optional.
     * }
     * @return array
     */
    public function initiate_payment( $data ) {
        $payment_account_id = isset( $data['payment_account_id'] )
            ? intval( $data['payment_account_id'] )
            : $this->resolve_payment_account_id();

        if ( ! $payment_account_id ) {
            return array(
                'success' => false,
                'message' => 'No payment account found. Pass payment_account_id or ensure your merchant has at least one payment account configured in PayNexus.',
            );
        }

        $payload = array(
            'payment_account_id' => $payment_account_id,
            'amount'             => floatval( $data['amount'] ),
            'phone'              => sanitize_text_field( $data['phone'] ),
            'description'        => sanitize_text_field( $data['description'] ?? 'Payment via PayNexus' ),
        );

        $result = $this->post( '/api/payments/initiate', $payload );

        if ( ! empty( $result['success'] ) ) {
            $payment_data = $result['data'] ?? array();

            PayNexus_Payment::insert_payment( array(
                'paynexus_payment_id' => $payment_data['payment_id'] ?? null,
                'reference'           => $payment_data['reference'] ?? null,
                'checkout_request_id' => $payment_data['checkout_request_id'] ?? null,
                'merchant_request_id' => $payment_data['merchant_request_id'] ?? null,
                'amount'              => floatval( $data['amount'] ),
                'currency'            => $payment_data['currency'] ?? paynexus()->get_option( 'currency', 'KES' ),
                'phone'               => $data['phone'],
                'description'         => $payload['description'],
                'status'              => 'pending',
                'metadata'            => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
            ) );

            do_action( 'paynexus_payment_initiated', $payment_data, $data );
        }

        return $result;
    }

    /**
     * Initiate payment via the M-Pesa specific endpoint.
     */
    public function initiate_mpesa_payment( $data ) {
        $payment_account_id = isset( $data['payment_account_id'] )
            ? intval( $data['payment_account_id'] )
            : $this->resolve_payment_account_id();

        if ( ! $payment_account_id ) {
            return array(
                'success' => false,
                'message' => 'No payment account found.',
            );
        }

        $payload = array(
            'payment_account_id' => $payment_account_id,
            'amount'             => floatval( $data['amount'] ),
            'phone'              => sanitize_text_field( $data['phone'] ),
            'description'        => sanitize_text_field( $data['description'] ?? 'Payment via PayNexus' ),
            'remark'             => sanitize_text_field( $data['remark'] ?? 'Website Payment' ),
        );

        $result = $this->post( '/api/mpesa/payment/initiate', $payload );

        if ( ! empty( $result['success'] ) ) {
            $payment_data = $result['data'] ?? $result;

            PayNexus_Payment::insert_payment( array(
                'paynexus_payment_id' => $payment_data['payment_id'] ?? null,
                'reference'           => $payment_data['reference'] ?? null,
                'checkout_request_id' => $payment_data['checkout_request_id'] ?? null,
                'merchant_request_id' => $payment_data['merchant_request_id'] ?? null,
                'amount'              => floatval( $data['amount'] ),
                'currency'            => paynexus()->get_option( 'currency', 'KES' ),
                'phone'               => $data['phone'],
                'description'         => $payload['description'],
                'status'              => 'pending',
                'metadata'            => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
            ) );

            do_action( 'paynexus_payment_initiated', $payment_data, $data );
        }

        return $result;
    }

    // -----------------------------------------------------------------
    //  Payments — status
    // -----------------------------------------------------------------

    /**
     * Get payment status by PayNexus reference.
     */
    public function get_payment_by_reference( $reference ) {
        $result = $this->request( 'GET', '/api/payments/' . urlencode( $reference ) );
        $this->sync_local_record( $result );
        return $result;
    }

    /**
     * Get payment status by PayNexus payment ID.
     */
    public function get_payment_by_id( $id ) {
        return $this->get( '/api/payments/' . intval( $id ) . '/status-by-id' );
    }

    /**
     * Get payment status by checkout request ID.
     */
    public function get_payment_by_checkout_id( $checkout_request_id ) {
        $result = $this->post( '/api/payments/status-by-checkout-id', array(
            'checkout_request_id' => $checkout_request_id,
        ) );
        $this->sync_local_record( $result );
        return $result;
    }

    /**
     * Query real-time M-Pesa transaction status via PayNexus.
     */
    public function check_mpesa_status( $checkout_request_id ) {
        $result = $this->post( '/api/mpesa/payment/status', array(
            'checkout_request_id' => $checkout_request_id,
        ) );
        $this->sync_local_record( $result );
        return $result;
    }

    /**
     * Poll for payment completion.
     */
    public function poll_status( $checkout_request_id, $interval = null, $timeout = null ) {
        $interval = $interval ?: absint( paynexus()->get_option( 'poll_interval', 3 ) );
        $timeout  = $timeout  ?: absint( paynexus()->get_option( 'poll_timeout', 120 ) );
        $start    = time();

        while ( true ) {
            $result = $this->get_payment_by_checkout_id( $checkout_request_id );
            $status = $result['data']['status'] ?? $result['status'] ?? null;

            if ( in_array( $status, array( 'completed', 'failed', 'timeout' ), true ) ) {
                return $result;
            }

            if ( ( time() - $start ) >= $timeout ) {
                return array(
                    'success'     => false,
                    'message'     => 'Polling timed out after ' . $timeout . ' seconds',
                    'last_status' => $status,
                    'data'        => $result['data'] ?? array(),
                );
            }

            sleep( $interval );
        }
    }

    // -----------------------------------------------------------------
    //  Payments — list
    // -----------------------------------------------------------------

    /**
     * List payments with optional filters.
     */
    public function list_payments( $filters = array() ) {
        return $this->get( '/api/payments', $filters );
    }

    // -----------------------------------------------------------------
    //  Webhooks
    // -----------------------------------------------------------------

    /**
     * Register a webhook endpoint with PayNexus.
     */
    public function register_webhook( $name, $url, $events = array() ) {
        return $this->post( '/api/webhooks/register', array(
            'name'   => $name,
            'url'    => $url,
            'events' => $events,
        ) );
    }

    /**
     * List registered webhooks.
     */
    public function list_webhooks() {
        return $this->get( '/api/webhooks' );
    }

    /**
     * Update a webhook.
     */
    public function update_webhook( $id, $data ) {
        return $this->put( '/api/webhooks/' . intval( $id ), $data );
    }

    /**
     * Delete a webhook.
     */
    public function delete_webhook( $id ) {
        return $this->api_delete( '/api/webhooks/' . intval( $id ) );
    }

    /**
     * Verify a PayNexus webhook signature.
     */
    public function verify_webhook_signature( $payload, $signature, $secret = null ) {
        $secret = $secret ?: paynexus()->get_option( 'webhook_secret', '' );
        if ( empty( $secret ) ) {
            return false;
        }
        $expected = hash_hmac( 'sha256', $payload, $secret );
        return hash_equals( $expected, $signature );
    }

    // -----------------------------------------------------------------
    //  Phone validation
    // -----------------------------------------------------------------

    /**
     * Validate and normalise a phone number via PayNexus.
     */
    public function validate_phone( $phone ) {
        return $this->post_read( '/api/mpesa/validate-phone', array(
            'phone' => $phone,
        ) );
    }

    // -----------------------------------------------------------------
    //  API Keys
    // -----------------------------------------------------------------

    /**
     * List API keys for the authenticated merchant.
     */
    public function list_api_keys() {
        return $this->get( '/api/api-keys' );
    }

    // -----------------------------------------------------------------
    //  Internal helpers
    // -----------------------------------------------------------------

    /**
     * Resolve the first available payment account ID.
     */
    private function resolve_payment_account_id() {
        $result = $this->get_payment_accounts();

        if ( empty( $result['success'] ) ) {
            return null;
        }

        $accounts = $result['data'] ?? array();
        if ( empty( $accounts ) ) {
            return null;
        }

        return intval( $accounts[0]['id'] );
    }

    /**
     * Sync a local payment record with the API response.
     */
    public function sync_local_record( $result ) {
        if ( empty( $result['success'] ) ) {
            return;
        }

        $data        = $result['data'] ?? $result;
        $checkout_id = $data['provider_request_id'] ?? $data['checkout_request_id'] ?? null;
        $reference   = $data['reference'] ?? null;

        if ( ! $checkout_id && ! $reference ) {
            return;
        }

        $local = null;
        if ( $checkout_id ) {
            $local = PayNexus_Payment::find_by( 'checkout_request_id', $checkout_id );
        }
        if ( ! $local && $reference ) {
            $local = PayNexus_Payment::find_by( 'reference', $reference );
        }
        if ( ! $local ) {
            return;
        }

        $updates = array();
        $new_status = $data['status'] ?? null;
        if ( $new_status && $new_status !== $local->status ) {
            $updates['status'] = $new_status;
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

        $pnx_id = $data['id'] ?? $data['payment_id'] ?? null;
        if ( $pnx_id && empty( $local->paynexus_payment_id ) ) {
            $updates['paynexus_payment_id'] = intval( $pnx_id );
        }

        if ( ! empty( $updates ) ) {
            PayNexus_Payment::update_payment( $local->id, $updates );
        }
    }

    // -----------------------------------------------------------------
    //  HTTP transport
    // -----------------------------------------------------------------

    private function get( $endpoint, $query = array() ) {
        return $this->request( 'GET', $endpoint, array(), $query, true );
    }

    private function post( $endpoint, $data = array() ) {
        return $this->request( 'POST', $endpoint, $data );
    }

    private function post_read( $endpoint, $data = array() ) {
        return $this->request( 'POST', $endpoint, $data, array(), true );
    }

    private function put( $endpoint, $data = array() ) {
        return $this->request( 'PUT', $endpoint, $data );
    }

    private function api_delete( $endpoint ) {
        return $this->request( 'DELETE', $endpoint );
    }

    /**
     * Execute an HTTP request against the PayNexus API.
     */
    private function request( $method, $endpoint, $data = array(), $query = array(), $use_public_key = false ) {
        $url = $this->base_url . $endpoint;

        if ( ! empty( $query ) ) {
            $url = add_query_arg( $query, $url );
        }

        $api_key = ( $use_public_key && $this->public_key ) ? $this->public_key : $this->secret_key;

        $args = array(
            'method'  => strtoupper( $method ),
            'timeout' => $this->timeout,
            'headers' => array(
                'X-API-Key'    => $api_key,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ),
        );

        if ( in_array( $method, array( 'POST', 'PUT' ), true ) && ! empty( $data ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        $attempts = 0;
        $max      = max( 1, $this->retries );
        $response = null;

        while ( $attempts < $max ) {
            $response = wp_remote_request( $url, $args );
            $attempts++;

            if ( ! is_wp_error( $response ) ) {
                break;
            }

            if ( $attempts < $max ) {
                usleep( 500000 ); // 500 ms
            }
        }

        if ( is_wp_error( $response ) ) {
            $this->log( 'error', "Connection error on {$method} {$endpoint}: " . $response->get_error_message() );
            return array(
                'success' => false,
                'message' => 'Unable to reach PayNexus API. Check your network and base URL configuration.',
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) ) {
            $body = array();
        }

        if ( 401 === $code ) {
            $this->log( 'error', "Authentication failed on {$method} {$endpoint}" );
            return array(
                'success' => false,
                'message' => $body['message'] ?? 'Authentication failed — check your API key.',
                'code'    => 401,
            );
        }

        if ( $code < 200 || $code >= 300 ) {
            $this->log( 'error', "API error ({$code}) on {$method} {$endpoint}" );
            return array(
                'success' => false,
                'message' => $body['message'] ?? $body['error'] ?? "API error ({$code})",
                'code'    => $code,
            );
        }

        return $body;
    }

    /**
     * Log a message to the PayNexus log file.
     */
    private function log( $level, $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[PayNexus] [{$level}] {$message}" );
        }
    }
}
