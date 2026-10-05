/**
 * ------------------------------------------------------------------------
 * Asset Terms - Formulário e assinatura em canvas
 *
 * A aba do computador é carregada por AJAX depois da página, então o
 * formulário é iniciado quando aparece no DOM (MutationObserver).
 * ------------------------------------------------------------------------
 */

(function () {
    'use strict';

    function csrfToken() {
        const meta = document.querySelector('meta[property="glpi:csrf_token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function post(url, formData) {
        return fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Glpi-Csrf-Token': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json, application/pdf',
            },
        });
    }

    function initTermo(form) {
        if (form.dataset.termoReady) return;
        form.dataset.termoReady = '1';

        const canvas = form.querySelector('#signature-canvas');
        const wrapper = form.querySelector('.canvas-wrapper');
        const saveBtn = form.querySelector('#btn-save-termo');
        const paperBtn = form.querySelector('#btn-print-blank');
        const stateSelect = form.querySelector('#target_state_id');
        const alertBox = form.querySelector('#termo-alert-box');
        const spinner = form.querySelector('#termo-loading-spinner');
        const radios = form.querySelectorAll('input[name="tipo_termo"]');
        const ctx = canvas.getContext('2d');
        let drawing = false;
        let hasDrawn = false;
        let sized = false;

        // ---------------------------------------------------- Assinatura
        function setupCanvas() {
            const rect = canvas.getBoundingClientRect();
            if (!rect.width) return;
            sized = true;
            const dpr = window.devicePixelRatio || 1;
            canvas.width = Math.round(rect.width * dpr);
            canvas.height = Math.round(rect.height * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#1e293b';
            clearSignature();
        }

        function clearSignature() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasDrawn = false;
            wrapper.classList.remove('has-signature');
        }

        function pos(e) {
            const rect = canvas.getBoundingClientRect();
            const p = e.touches ? e.touches[0] : e;
            return { x: p.clientX - rect.left, y: p.clientY - rect.top };
        }

        function start(e) {
            if (!sized) setupCanvas();
            drawing = true;
            hasDrawn = true;
            wrapper.classList.add('has-signature');
            const p = pos(e);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            ctx.lineTo(p.x + 0.1, p.y + 0.1);
            ctx.stroke();
            if (e.cancelable) e.preventDefault();
        }

        function move(e) {
            if (!drawing) return;
            const p = pos(e);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            if (e.cancelable) e.preventDefault();
        }

        function end() {
            drawing = false;
        }

        // Exporta com fundo branco: o PDF não depende de transparência
        function signaturePng() {
            const out = document.createElement('canvas');
            out.width = canvas.width;
            out.height = canvas.height;
            const o = out.getContext('2d');
            o.fillStyle = '#ffffff';
            o.fillRect(0, 0, out.width, out.height);
            o.drawImage(canvas, 0, 0);
            return out.toDataURL('image/png');
        }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);
        canvas.addEventListener('touchcancel', end);
        form.querySelector('#btn-clear-signature').addEventListener('click', clearSignature);

        // Redimensionar apaga o desenho; só refaz se ainda não houver assinatura
        window.addEventListener('resize', function () {
            if (!hasDrawn) setupCanvas();
        });
        setupCanvas();

        // ------------------------------------------- Entrega x devolução
        function applyTipo() {
            const checked = form.querySelector('input[name="tipo_termo"]:checked');
            const tipo = checked.value;
            form.querySelectorAll('.radio-card').forEach(function (card) {
                card.classList.toggle('selected', card.contains(checked));
            });
            form.querySelectorAll('[data-show]').forEach(function (el) {
                el.hidden = el.dataset.show !== tipo;
            });
            const state = checked.dataset.state;
            if (stateSelect && state && state !== '0') {
                stateSelect.value = state;
            }
        }
        radios.forEach(function (r) { r.addEventListener('change', applyTipo); });

        // ------------------------------------------------------ Envio
        function showAlert(kind, html) {
            alertBox.className = 'alert alert-' + kind + ' mt-3';
            alertBox.innerHTML = html;
        }

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function busy(on) {
            saveBtn.disabled = on;
            paperBtn.disabled = on;
            spinner.classList.toggle('d-none', !on);
        }

        function collect(modo) {
            const fd = new FormData(form);
            fd.set('modo', modo);
            fd.delete('signature_image');
            if (modo === 'arquivar' && hasDrawn) {
                fd.set('signature_image', signaturePng());
            }
            return fd;
        }

        function errorFrom(response) {
            return response.json()
                .then(function (j) { return j.message || ('Erro ' + response.status); })
                .catch(function () { return 'Erro ' + response.status + ' ao falar com o servidor.'; });
        }

        saveBtn.addEventListener('click', function () {
            if (!hasDrawn && !confirm('O colaborador não assinou na tela. Arquivar o termo sem assinatura, para assinatura manual?')) {
                return;
            }
            busy(true);
            alertBox.className = 'alert d-none mt-3';
            post(form.action, collect('arquivar'))
                .then(function (r) {
                    return r.ok ? r.json() : errorFrom(r).then(function (m) { return { success: false, message: m }; });
                })
                .then(function (res) {
                    busy(false);
                    if (!res.success) {
                        showAlert('danger', escapeHtml(res.message || 'Erro ao gerar o termo.'));
                        return;
                    }
                    showAlert('success',
                        '<strong><i class="ti ti-check"></i> ' + escapeHtml(res.message) + '</strong><br>' +
                        '<a href="' + escapeHtml(res.view_url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-success mt-2">' +
                        '<i class="ti ti-eye"></i> Abrir o PDF</a>');
                    const statusBadge = document.querySelector('.badge-status strong');
                    if (statusBadge && res.status_name) statusBadge.textContent = res.status_name;
                    const userBadge = document.querySelector('.badge-user strong');
                    if (userBadge && res.user_name) userBadge.textContent = res.user_name;
                    clearSignature();
                })
                .catch(function (err) {
                    busy(false);
                    showAlert('danger', 'Falha na comunicação com o servidor: ' + escapeHtml(err));
                });
        });

        // PDF sem assinatura, para imprimir e assinar no papel (nada é gravado)
        paperBtn.addEventListener('click', function () {
            const win = window.open('', '_blank');
            busy(true);
            post(form.action, collect('papel'))
                .then(function (r) {
                    if (!r.ok) return errorFrom(r).then(function (m) { throw new Error(m); });
                    return r.blob();
                })
                .then(function (blob) {
                    busy(false);
                    const url = URL.createObjectURL(blob);
                    if (win) {
                        win.location.href = url;
                    } else {
                        window.location.href = url;
                    }
                })
                .catch(function (err) {
                    busy(false);
                    if (win) win.close();
                    showAlert('danger', escapeHtml(err.message || err));
                });
        });
    }

    function scan() {
        const form = document.getElementById('form-termo-responsabilidade');
        if (form) initTermo(form);
    }

    new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
})();
