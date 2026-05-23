// data-confirm-action è il testo del bottone di conferma
// invece data-confirm-cancel è il testo dell'annulla

(function () {
    'use strict';

    var overlay = null;
    var modal = null;
    var lastFocused = null;

    function createOverlay() {
        overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.setAttribute('aria-hidden', 'true');

        modal = document.createElement('div');
        modal.className = 'modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('tabindex', '-1');

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) close();
        });
    }

    function close() {
        if (!overlay) return;
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        if (lastFocused) { try { lastFocused.focus(); } catch (e) {} }
        document.removeEventListener('keydown', onKey);
    }

    function focusables() {
        return modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    }

    function onKey(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
            return;
        }
        if (e.key === 'Tab') {
            var f = focusables();
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    }

    function open(opts, onConfirm) {
        if (!overlay) createOverlay();
        lastFocused = document.activeElement;

        var titleId  = 'modal-title-' + Date.now();
        modal.setAttribute('aria-labelledby', titleId);

        modal.innerHTML = ''
            + '<h3 id="' + titleId + '" class="modal-title">' + escapeHtml(opts.title) + '</h3>'
            + '<p class="modal-text">' + escapeHtml(opts.text) + '</p>'
            + '<div class="modal-actions">'
            +   '<button type="button" class="btn btn-outline" data-modal-cancel>' + escapeHtml(opts.cancel) + '</button>'
            +   '<button type="button" class="btn" data-modal-ok>' + escapeHtml(opts.action) + '</button>'
            + '</div>';

        modal.querySelector('[data-modal-cancel]').addEventListener('click', close);
        modal.querySelector('[data-modal-ok]').addEventListener('click', function () {
            close();
            onConfirm();
        });

        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        document.addEventListener('keydown', onKey);

        setTimeout(function () { modal.querySelector('[data-modal-cancel]').focus(); }, 10);
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function init() {
        document.querySelectorAll('form[data-confirm-modal]').forEach(function (form) {
            if (form.hasAttribute('onsubmit')) form.removeAttribute('onsubmit');

            form.addEventListener('submit', function (e) {
                if (form.dataset._confirmed === '1') return; // già confermato
                e.preventDefault();
                open({
                    title:  form.dataset.confirmTitle  || 'Conferma azione',
                    text:   form.dataset.confirmText   || 'Vuoi continuare?',
                    action: form.dataset.confirmAction || 'Conferma',
                    cancel: form.dataset.confirmCancel || 'Annulla',
                }, function () {
                    form.dataset._confirmed = '1';
                    form.submit();
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
