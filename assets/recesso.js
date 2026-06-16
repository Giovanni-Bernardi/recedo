/* Recedo — recesso.js */
/* global jQuery, wcr54 */
(function ($) {
    'use strict';

    var modal       = null;
    var currentOrderId   = 0;
    var currentOrderKey  = '';
    var currentScadenza  = '';
    var userEmail        = '';

    $(document).ready(function () {
        modal = $('#wcr54-modal');
        if (!modal.length) return;

        // Recupera email utente dal DOM (WC la stampa in varie posizioni)
        userEmail = $('address.woocommerce-customer-details--email').text().trim()
                 || $('p.order-info .email').text().trim()
                 || '';

        // ── Apri modale ──────────────────────────────────────────────────────
        $(document).on('click', '.wcr54-btn-recesso', function () {
            currentOrderId  = $(this).data('order-id');
            currentOrderKey = $(this).data('order-key') || '';
            currentScadenza = $(this).data('scadenza') || '';

            resetModal();
            populateStep1();
            openModal();
        });

        // ── Chiudi modale ─────────────────────────────────────────────────────
        $(document).on('click', '.wcr54-modal-close, #wcr54-overlay', function () {
            closeModal();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        // ── Step 1 → Step 2 ───────────────────────────────────────────────────
        modal.on('click', '.wcr54-btn-step2', function () {
            showStep(2);
        });

        // ── Step 2 → Step 1 (Indietro) ────────────────────────────────────────
        modal.on('click', '.wcr54-btn-back', function () {
            showStep(1);
        });

        // ── Conferma definitiva ────────────────────────────────────────────────
        modal.on('click', '#wcr54-btn-confirm-final', function () {
            submitRecesso($(this));
        });
    });

    // ── Funzioni ──────────────────────────────────────────────────────────────

    function openModal() {
        modal.removeAttr('hidden');
        modal.find('.wcr54-modal-close').trigger('focus');
        $('body').css('overflow', 'hidden');
        bindFocusTrap();
    }

    function closeModal() {
        modal.attr('hidden', '');
        $('body').css('overflow', '');
        $(document).off('keydown.wcr54trap');
    }

    // Focus trap (WCAG 2.4.3): mantiene il focus dentro la modale con Tab/Shift+Tab
    function bindFocusTrap() {
        $(document).off('keydown.wcr54trap').on('keydown.wcr54trap', function (e) {
            if (e.key !== 'Tab') return;
            var focusable = modal.find(
                'button:visible, [href]:visible, input:visible, textarea:visible, [tabindex]:not([tabindex="-1"]):visible'
            ).filter(':not([disabled])');
            if (!focusable.length) return;
            var first = focusable.first()[0];
            var last  = focusable.last()[0];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    }

    function resetModal() {
        showStep(1);
        $('#wcr54-reason').val('');
        $('#wcr54-error-msg').attr('hidden', '').text('');
    }

    function showStep(n) {
        $('#wcr54-step-1, #wcr54-step-2, #wcr54-step-3').attr('hidden', '');
        $('#wcr54-step-' + n).removeAttr('hidden');
    }

    function populateStep1() {
        $('#wcr54-order-label').text('#' + currentOrderId);
        $('#wcr54-order-id-display').text(currentOrderId);
        $('#wcr54-scadenza').text(currentScadenza);
        $('#wcr54-user-email').text(userEmail || '—');
    }

    function submitRecesso($btn) {
        var reason = $('#wcr54-reason').val();

        $btn.prop('disabled', true).text(wcr54.i18n.sending);
        $('#wcr54-error-msg').attr('hidden', '').text('');

        var data = {
            action    : 'wcr54_submit_recesso',
            nonce     : wcr54.nonce,
            order_id  : currentOrderId,
            reason    : reason,
        };

        if (currentOrderKey) {
            data.order_key = currentOrderKey;
        }

        $.post(wcr54.ajax_url, data)
            .done(function (res) {
                if (res.success) {
                    showStep(3);
                    $('#wcr54-success-msg').text(res.data.message);
                    // Disabilita il pulsante nell'elenco ordini per questo ordine
                    $('[data-order-id="' + currentOrderId + '"]').prop('disabled', true).text('Recesso inviato');
                } else {
                    showError(res.data.message || wcr54.i18n.error_generic);
                    $btn.prop('disabled', false).text(wcr54.i18n.confirm_label);
                }
            })
            .fail(function () {
                showError(wcr54.i18n.error_generic);
                $btn.prop('disabled', false).text(wcr54.i18n.confirm_label);
            });
    }

    function showError(msg) {
        $('#wcr54-error-msg').removeAttr('hidden').text(msg);
    }

}(jQuery));
