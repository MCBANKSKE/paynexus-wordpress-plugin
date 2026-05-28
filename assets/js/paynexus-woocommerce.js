/**
 * PayNexus WooCommerce Thank-You Page Polling
 *
 * Polls the PayNexus server database for payment status
 * and updates the WooCommerce order automatically.
 */
(function() {
    var params = window.paynexus_woo_params || {};

    var checkoutId = params.checkout_id || '';
    var reference  = params.reference || '';
    var ajaxUrl    = params.ajax_url || '';
    var nonce      = params.nonce || '';
    var interval   = params.poll_interval || 3000;
    var timeout    = params.poll_timeout || 120000;
    var start      = Date.now();
    var spinner    = document.getElementById('pnx-spinner');
    var msg        = document.getElementById('pnx-status-msg');
    var result     = document.getElementById('pnx-result');
    var phoneAnim  = document.getElementById('pnx-phone-anim');
    var steps      = [
        document.getElementById('pnx-step-1'),
        document.getElementById('pnx-step-2'),
        document.getElementById('pnx-step-3'),
        document.getElementById('pnx-step-4')
    ];
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
