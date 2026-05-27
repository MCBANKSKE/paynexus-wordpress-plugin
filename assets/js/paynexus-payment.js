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

    // Show payment summary before submit.
    $(document).on('input change', '#paynexus-amount, #paynexus-phone', function () {
        var $form    = $(this).closest('[data-paynexus-form]');
        var amount   = $form.find('[name="amount"]').val();
        var phone    = $form.find('[name="phone"]').val();
        var $summary = $form.find('#pnx-payment-summary');

        if (amount && parseFloat(amount) > 0 && phone && phone.length >= 9) {
            var currency = params.currency || 'KES';
            $form.find('#pnx-summary-amount').text(currency + ' ' + parseFloat(amount).toLocaleString());
            $form.find('#pnx-summary-phone').text(phone);
            $summary.slideDown(200);
        } else {
            $summary.slideUp(200);
        }
    });

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
                action:      'paynexus_initiate_payment',
                nonce:       params.nonce,
                phone:       phone,
                amount:      amount,
                description: $form.find('[name="description"]').val() || 'Payment via PayNexus'
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
                    var statusClass = d.status === 'completed' ? 'pnx-receipt--success' : (d.status === 'failed' ? 'pnx-receipt--failed' : 'pnx-receipt--pending');
                    var statusIcon = d.status === 'completed' ? '&#10003;' : (d.status === 'failed' ? '&#10007;' : '&#9679;');
                    var html = '<div class="pnx-receipt__header ' + statusClass + '">'
                             + '<span class="pnx-receipt__icon">' + statusIcon + '</span>'
                             + '<span class="pnx-receipt__status">' + escHtml((d.status || 'unknown').toUpperCase()) + '</span>'
                             + '</div>'
                             + '<div class="pnx-receipt__body">'
                             + '<div class="pnx-receipt__row">'
                             + '<span class="pnx-receipt__label">Reference</span>'
                             + '<span class="pnx-receipt__value"><code>' + escHtml(d.reference || ref) + '</code>'
                             + '<button type="button" class="pnx-copy-btn" data-copy="' + escHtml(d.reference || ref) + '" title="Copy">&#128203;</button></span>'
                             + '</div>'
                             + '<div class="pnx-receipt__row">'
                             + '<span class="pnx-receipt__label">Amount</span>'
                             + '<span class="pnx-receipt__value">' + escHtml((d.currency || 'KES') + ' ' + parseFloat(d.amount || 0).toLocaleString()) + '</span>'
                             + '</div>';
                    if (d.transaction_id || d.provider_transaction_id) {
                        var txnId = d.provider_transaction_id || d.transaction_id;
                        html += '<div class="pnx-receipt__row">'
                              + '<span class="pnx-receipt__label">Transaction ID</span>'
                              + '<span class="pnx-receipt__value"><code>' + escHtml(txnId) + '</code>'
                              + '<button type="button" class="pnx-copy-btn" data-copy="' + escHtml(txnId) + '" title="Copy">&#128203;</button></span>'
                              + '</div>';
                    }
                    if (d.phone) {
                        html += '<div class="pnx-receipt__row">'
                              + '<span class="pnx-receipt__label">Phone</span>'
                              + '<span class="pnx-receipt__value">' + escHtml(d.phone) + '</span>'
                              + '</div>';
                    }
                    html += '</div>';
                    $result.removeClass('success error').addClass(statusClass).html(html).show();
                } else {
                    $result.removeClass('success').addClass('error').html('<p style="padding:16px;text-align:center;">' + escHtml((res.data && res.data.message) || 'Not found.') + '</p>').show();
                }
            },
            error: function () {
                $result.removeClass('success').addClass('error').html('<p style="padding:16px;text-align:center;">An error occurred.</p>').show();
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

    // Copy-to-clipboard handler for receipt copy buttons.
    $(document).on('click', '.pnx-copy-btn', function () {
        var text = $(this).data('copy');
        var $btn = $(this);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                $btn.html('&#10003;').addClass('pnx-copy-btn--done');
                setTimeout(function () { $btn.html('&#128203;').removeClass('pnx-copy-btn--done'); }, 1500);
            });
        }
    });

})(jQuery);
