/**
 * PayNexus Payment — frontend JavaScript.
 *
 * Handles payment form submission, STK Push initiation, and status polling.
 */
(function ($) {
    'use strict';

    var params = window.paynexus_params || {};

    // -----------------------------------------------------------------
    //  Payment Form (shortcode)
    // -----------------------------------------------------------------

    $(document).on('submit', '[data-paynexus-form]', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $wrap    = $form.closest('.paynexus-payment-form-wrap');
        var $btn     = $form.find('[data-paynexus-submit]');
        var $status  = $wrap.find('[data-paynexus-status]');
        var $message = $wrap.find('[data-paynexus-message]');
        var $result  = $wrap.find('[data-paynexus-result]');

        var phone  = $form.find('[name="phone"]').val();
        var amount = $form.find('[name="amount"]').val();

        if (!phone || !amount || parseFloat(amount) <= 0) {
            return;
        }

        $btn.prop('disabled', true);
        $form.hide();
        $status.show();
        $result.hide().removeClass('success error').empty();
        $message.text(params.i18n ? params.i18n.processing : 'Processing payment...');

        $.ajax({
            url: params.ajax_url,
            method: 'POST',
            data: {
                action:            'paynexus_initiate_payment',
                nonce:             params.nonce,
                phone:             phone,
                amount:            amount,
                account_reference: $form.find('[name="account_reference"]').val() || 'PAYNEXUS',
                description:       $form.find('[name="description"]').val() || 'Payment via PayNexus'
            },
            success: function (res) {
                if (res.success) {
                    var data = res.data || {};
                    $message.text(params.i18n ? params.i18n.waiting : 'Waiting for M-Pesa confirmation...');
                    pollPaymentStatus(data.checkout_request_id || '', data.reference || '', $status, $message, $result, $form, $btn);
                } else {
                    showError($status, $result, $form, $btn, (res.data && res.data.message) || 'Payment initiation failed.');
                }
            },
            error: function () {
                showError($status, $result, $form, $btn, params.i18n ? params.i18n.error : 'An error occurred.');
            }
        });
    });

    // -----------------------------------------------------------------
    //  Payment Status Checker (shortcode)
    // -----------------------------------------------------------------

    $(document).on('submit', '[data-paynexus-status-form]', function (e) {
        e.preventDefault();

        var $form   = $(this);
        var $result = $form.closest('.paynexus-status-checker').find('[data-paynexus-status-result]');
        var ref     = $form.find('[name="reference"]').val();

        if (!ref) return;

        $result.show().removeClass('success error').html('<div class="paynexus-spinner"></div>');

        $.ajax({
            url: params.ajax_url,
            method: 'POST',
            data: {
                action:    'paynexus_check_status',
                nonce:     params.nonce,
                reference: ref
            },
            success: function (res) {
                if (res.success) {
                    var d = res.data || {};
                    var html = '<p><strong>Reference:</strong> ' + escHtml(d.reference || ref) + '</p>'
                             + '<p><strong>Status:</strong> ' + escHtml(d.status || 'unknown') + '</p>'
                             + '<p><strong>Amount:</strong> ' + escHtml((d.currency || 'KES') + ' ' + (d.amount || '0')) + '</p>';
                    if (d.transaction_id) {
                        html += '<p><strong>Transaction ID:</strong> ' + escHtml(d.transaction_id) + '</p>';
                    }
                    $result.addClass('success').html(html);
                } else {
                    $result.addClass('error').html('<p>' + escHtml((res.data && res.data.message) || 'Not found.') + '</p>');
                }
            },
            error: function () {
                $result.addClass('error').html('<p>An error occurred.</p>');
            }
        });
    });

    // -----------------------------------------------------------------
    //  WooCommerce thank-you page polling
    // -----------------------------------------------------------------

    $(function () {
        var $orderStatus = $('#paynexus-order-status');
        if ($orderStatus.length) {
            var checkoutId = $orderStatus.data('checkout-id');
            var reference  = $orderStatus.data('reference');
            var orderUrl   = $orderStatus.data('order-url');

            if (checkoutId || reference) {
                pollWooStatus(checkoutId, reference, $orderStatus, orderUrl);
            }
        }
    });

    // -----------------------------------------------------------------
    //  Polling helpers
    // -----------------------------------------------------------------

    function pollPaymentStatus(checkoutId, reference, $status, $message, $result, $form, $btn) {
        var interval = params.poll_interval || 3000;
        var timeout  = params.poll_timeout  || 120000;
        var start    = Date.now();

        var timer = setInterval(function () {
            if (Date.now() - start > timeout) {
                clearInterval(timer);
                showError($status, $result, $form, $btn, params.i18n ? params.i18n.timeout : 'Payment timed out.');
                return;
            }

            $.ajax({
                url: params.ajax_url,
                method: 'POST',
                data: {
                    action:              'paynexus_check_status',
                    nonce:               params.nonce,
                    checkout_request_id: checkoutId,
                    reference:           reference
                },
                success: function (res) {
                    if (!res.success) return;

                    var d      = res.data || {};
                    var status = d.status || '';

                    if (status === 'completed') {
                        clearInterval(timer);
                        $status.hide();
                        $result.addClass('success').html(
                            '<p><strong>' + (params.i18n ? params.i18n.completed : 'Payment completed!') + '</strong></p>' +
                            '<p>Reference: ' + escHtml(d.reference || reference) + '</p>' +
                            (d.transaction_id ? '<p>Transaction: ' + escHtml(d.transaction_id) + '</p>' : '')
                        ).show();
                    } else if (status === 'failed') {
                        clearInterval(timer);
                        showError($status, $result, $form, $btn, d.failure_reason || (params.i18n ? params.i18n.failed : 'Payment failed.'));
                    }
                }
            });
        }, interval);
    }

    function pollWooStatus(checkoutId, reference, $container, orderUrl) {
        var interval = params.poll_interval || 3000;
        var timeout  = params.poll_timeout  || 120000;
        var start    = Date.now();
        var $msg     = $container.find('.paynexus-status-message');

        var timer = setInterval(function () {
            if (Date.now() - start > timeout) {
                clearInterval(timer);
                $container.find('.paynexus-spinner').hide();
                $msg.text(params.i18n ? params.i18n.timeout : 'Payment confirmation timed out. Your payment may still be processing.');
                return;
            }

            $.ajax({
                url: params.ajax_url,
                method: 'POST',
                data: {
                    action:              'paynexus_check_status',
                    nonce:               params.nonce,
                    checkout_request_id: checkoutId,
                    reference:           reference
                },
                success: function (res) {
                    if (!res.success) return;

                    var d      = res.data || {};
                    var status = d.status || '';

                    if (status === 'completed') {
                        clearInterval(timer);
                        $container.find('.paynexus-spinner').hide();
                        $msg.html('<strong style="color:#1e7e34;">' + (params.i18n ? params.i18n.completed : 'Payment completed!') + '</strong>');
                        if (orderUrl) {
                            setTimeout(function () { window.location.reload(); }, 2000);
                        }
                    } else if (status === 'failed') {
                        clearInterval(timer);
                        $container.find('.paynexus-spinner').hide();
                        $msg.html('<strong style="color:#c5221f;">' + escHtml(d.failure_reason || (params.i18n ? params.i18n.failed : 'Payment failed.')) + '</strong>');
                    }
                }
            });
        }, interval);
    }

    function showError($status, $result, $form, $btn, message) {
        $status.hide();
        $result.addClass('error').html('<p>' + escHtml(message) + '</p>').show();
        $form.show();
        $btn.prop('disabled', false);
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})(jQuery);
