/**
 * ------------------------------------------------------------------------
 * Asset Terms - Formulários e assinatura em canvas
 *
 * - Aba do computador e Ativos > Termos (#form-termo-responsabilidade): assinatura na
 *   tela, envio por e-mail, link de assinatura, PDF para o papel e "somente ciclo de vida".
 * - Página do link enviado por e-mail (#form-termo-assinatura): o colaborador assina.
 *
 * A aba é carregada por AJAX depois da página, então os formulários são
 * iniciados quando aparecem no DOM (MutationObserver).
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

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    /** Lê a resposta JSON mesmo quando o servidor responde com erro */
    function readJson(response) {
        return response.json().catch(function () {
            return { success: false, message: 'Erro ' + response.status + ' ao falar com o servidor.' };
        });
    }

    /** Copia texto; em HTTP sem TLS o navegador bloqueia a API de área de transferência */
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        return ok ? Promise.resolve() : Promise.reject(new Error('cópia bloqueada'));
    }

    function flashCopied(btn) {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="ti ti-check"></i> Copiado';
        setTimeout(function () { btn.innerHTML = original; }, 1800);
    }

    function reloadSoon() {
        setTimeout(function () { window.location.reload(); }, 1800);
    }

    /** Alerta e indicador de carregamento de um formulário */
    function feedback(form) {
        const alertBox = form.querySelector('#termo-alert-box');
        const spinner = form.querySelector('#termo-loading-spinner');
        return {
            show: function (kind, html) {
                alertBox.className = 'alert alert-' + kind + ' mt-3';
                alertBox.innerHTML = html;
            },
            hide: function () {
                alertBox.className = 'alert d-none mt-3';
            },
            busy: function (on) {
                form.querySelectorAll('.termo-actions-bar button').forEach(function (b) { b.disabled = on; });
                if (spinner) spinner.classList.toggle('d-none', !on);
            },
        };
    }

    // ------------------------------------------------------- Quadro de assinatura
    function signaturePad(form) {
        const canvas = form.querySelector('#signature-canvas');
        const wrapper = form.querySelector('.canvas-wrapper');
        const ctx = canvas.getContext('2d');
        let drawing = false;
        let hasDrawn = false;
        let sized = false;

        function clear() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasDrawn = false;
            wrapper.classList.remove('has-signature');
        }

        function setup() {
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
            clear();
        }

        function pos(e) {
            const rect = canvas.getBoundingClientRect();
            const p = e.touches ? e.touches[0] : e;
            return { x: p.clientX - rect.left, y: p.clientY - rect.top };
        }

        function start(e) {
            if (!sized) setup();
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

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);
        canvas.addEventListener('touchcancel', end);
        form.querySelector('#btn-clear-signature').addEventListener('click', clear);

        // Redimensionar apaga o desenho; só refaz se ainda não houver assinatura
        window.addEventListener('resize', function () {
            if (!hasDrawn) setup();
        });
        setup();

        return {
            hasDrawn: function () { return hasDrawn; },
            clear: clear,
            // Exporta com fundo branco: o PDF não depende de transparência
            png: function () {
                const out = document.createElement('canvas');
                out.width = canvas.width;
                out.height = canvas.height;
                const o = out.getContext('2d');
                o.fillStyle = '#ffffff';
                o.fillRect(0, 0, out.width, out.height);
                o.drawImage(canvas, 0, 0);
                return out.toDataURL('image/png');
            },
        };
    }

    // ------------------------------------------------------ Aba do computador
    function initTab(form) {
        if (form.dataset.termoReady) return;
        form.dataset.termoReady = '1';

        const pad = signaturePad(form);
        const ui = feedback(form);
        const stateSelect = form.querySelector('#target_state_id');

        function applyTipo() {
            const checked = form.querySelector('input[name="tipo_termo"]:checked');
            const tipo = checked.value;
            form.querySelectorAll('.radio-card').forEach(function (card) {
                card.classList.toggle('selected', card.contains(checked));
            });
            form.querySelectorAll('[data-show]').forEach(function (el) {
                el.hidden = el.dataset.show !== tipo;
            });
            // "Somente ciclo de vida" esconde tudo o que é do termo
            form.querySelectorAll('[data-mode]').forEach(function (el) {
                el.hidden = (el.dataset.mode === 'status') !== (tipo === 'status');
            });
            const state = checked.dataset.state;
            if (stateSelect && state && state !== '0') {
                stateSelect.value = state;
            }
        }
        form.querySelectorAll('input[name="tipo_termo"]').forEach(function (r) { r.addEventListener('change', applyTipo); });

        // Somente ciclo de vida: "Mover para outra pessoa" mostra o usuário do GLPI e o nome de preferência
        const acaoSelect = form.querySelector('#usuario_acao');
        function applyAcao() {
            form.querySelectorAll('[data-acao]').forEach(function (el) {
                el.hidden = !acaoSelect || el.dataset.acao !== acaoSelect.value;
            });
        }
        if (acaoSelect) acaoSelect.addEventListener('change', applyAcao);

        function collect(modo) {
            const fd = new FormData(form);
            fd.set('modo', modo);
            fd.delete('signature_image');
            if (modo === 'arquivar' && pad.hasDrawn()) {
                fd.set('signature_image', pad.png());
            }
            return fd;
        }

        function send(modo) {
            ui.busy(true);
            ui.hide();
            return post(form.action, collect(modo))
                .then(readJson)
                .then(function (res) {
                    ui.busy(false);
                    if (!res.success) {
                        ui.show('danger', escapeHtml(res.message || 'Erro ao gerar o termo.'));
                        return null;
                    }
                    return res;
                })
                .catch(function (err) {
                    ui.busy(false);
                    ui.show('danger', 'Falha na comunicação com o servidor: ' + escapeHtml(err));
                    return null;
                });
        }

        // Assinatura na tela
        form.querySelector('#btn-save-termo').addEventListener('click', function () {
            if (!pad.hasDrawn() && !confirm('O colaborador não assinou na tela. Arquivar o termo sem assinatura, para assinatura manual?')) {
                return;
            }
            send('arquivar').then(function (res) {
                if (!res) return;
                ui.show('success',
                    '<strong><i class="ti ti-check"></i> ' + escapeHtml(res.message) + '</strong><br>' +
                    '<a href="' + escapeHtml(res.view_url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-success mt-2">' +
                    '<i class="ti ti-eye"></i> Abrir o PDF</a>');
                const statusBadge = document.querySelector('.badge-status strong');
                if (statusBadge && res.status_name) statusBadge.textContent = res.status_name;
                const userBadge = document.querySelector('.badge-user strong');
                if (userBadge && res.user_name) userBadge.textContent = res.user_name;
                pad.clear();
            });
        });

        // Envio por e-mail: o colaborador assina pelo link (só aparece com o e-mail do GLPI configurado)
        const emailBtn = form.querySelector('#btn-send-email');
        if (emailBtn) {
            emailBtn.addEventListener('click', function () {
                send('email').then(function (res) {
                    if (!res) return;
                    ui.show('success', '<strong><i class="ti ti-mail-check"></i> ' + escapeHtml(res.message) + '</strong>');
                    reloadSoon();
                });
            });
        }

        // Link de assinatura para o técnico mandar pelo Teams, WhatsApp, chat...
        form.querySelector('#btn-send-link').addEventListener('click', function () {
            send('link').then(function (res) {
                if (!res) return;
                ui.show('success',
                    '<strong><i class="ti ti-link"></i> ' + escapeHtml(res.message) + '</strong>' +
                    '<div class="input-group mt-2"><input type="text" class="form-control termo-link-field" readonly value="' + escapeHtml(res.link) + '">' +
                    '<button type="button" class="btn btn-success termo-copy-link" data-link="' + escapeHtml(res.link) + '"><i class="ti ti-copy"></i> Copiar link</button></div>');
            });
        });

        // Somente ciclo de vida: muda o status sem gerar termo
        form.querySelector('#btn-status').addEventListener('click', function () {
            send('status').then(function (res) {
                if (!res) return;
                ui.show('success', '<strong><i class="ti ti-check"></i> ' + escapeHtml(res.message) + '</strong>');
                const statusBadge = document.querySelector('.badge-status strong');
                if (statusBadge && res.status_name) statusBadge.textContent = res.status_name;
                const userBadge = document.querySelector('.badge-user strong');
                if (userBadge && res.user_name) userBadge.textContent = res.user_name;
            });
        });

        // PDF sem assinatura, para imprimir e assinar no papel (nada é gravado)
        form.querySelector('#btn-print-blank').addEventListener('click', function () {
            const win = window.open('', '_blank');
            ui.busy(true);
            post(form.action, collect('papel'))
                .then(function (r) {
                    if (!r.ok) return readJson(r).then(function (j) { throw new Error(j.message); });
                    return r.blob();
                })
                .then(function (blob) {
                    ui.busy(false);
                    const url = URL.createObjectURL(blob);
                    if (win) {
                        win.location.href = url;
                    } else {
                        window.location.href = url;
                    }
                })
                .catch(function (err) {
                    ui.busy(false);
                    if (win) win.close();
                    ui.show('danger', escapeHtml(err.message || err));
                });
        });
    }

    // Copiar link de assinatura (resultado do "Gerar link" e lista de pendentes)
    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('.termo-copy-link');
        if (!btn) return;
        copyText(btn.dataset.link).then(function () { flashCopied(btn); }).catch(function () {
            window.prompt('Copie o link:', btn.dataset.link);
        });
    });

    // Reenviar / cancelar termos pendentes (delegação: a aba é recriada ao recarregar)
    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('.termo-request-action');
        if (!btn) return;
        const acao = btn.dataset.action;
        if (acao === 'cancelar' && !confirm('Cancelar este termo? O link enviado por e-mail deixa de funcionar.')) {
            return;
        }
        const fd = new FormData();
        fd.set('id', btn.closest('tr').dataset.request);
        fd.set('acao', acao);
        btn.disabled = true;
        post(btn.dataset.url, fd)
            .then(readJson)
            .then(function (res) {
                btn.disabled = false;
                const box = document.querySelector('#form-termo-responsabilidade #termo-alert-box');
                if (box) {
                    box.className = 'alert alert-' + (res.success ? 'success' : 'danger') + ' mt-3';
                    box.textContent = res.message || '';
                } else {
                    alert(res.message || '');
                }
                if (res.reload) reloadSoon();
            })
            .catch(function (err) {
                btn.disabled = false;
                alert('Falha na comunicação com o servidor: ' + err);
            });
    });

    // ------------------------------------------ Página de assinatura pelo link
    function initSign(form) {
        if (form.dataset.termoReady) return;
        form.dataset.termoReady = '1';

        const pad = signaturePad(form);
        const ui = feedback(form);

        form.querySelector('#btn-sign-termo').addEventListener('click', function () {
            if (!form.querySelector('#termo-aceite').checked) {
                ui.show('warning', 'Marque que leu e concorda com o termo.');
                return;
            }
            if (!pad.hasDrawn()) {
                ui.show('warning', 'Assine no quadro antes de enviar.');
                return;
            }
            const fd = new FormData(form);
            fd.set('signature_image', pad.png());
            ui.busy(true);
            ui.hide();
            post(form.action, fd)
                .then(readJson)
                .then(function (res) {
                    ui.busy(false);
                    if (!res.success) {
                        ui.show('danger', escapeHtml(res.message || 'Erro ao assinar o termo.'));
                        return;
                    }
                    form.querySelectorAll('.termo-clausula-box, .termo-aceite, .termo-signature-area, .termo-actions-bar').forEach(function (el) {
                        el.hidden = true;
                    });
                    ui.show('success', '<strong><i class="ti ti-circle-check"></i> ' + escapeHtml(res.message) + '</strong>');
                })
                .catch(function (err) {
                    ui.busy(false);
                    ui.show('danger', 'Falha na comunicação com o servidor: ' + escapeHtml(err));
                });
        });
    }

    // --------------------------------------- Configuração (empresa e texto)
    function initConfig(form) {
        if (form.dataset.termoReady) return;
        form.dataset.termoReady = '1';

        // PDF de exemplo com o texto que está no formulário (antes de salvar)
        form.querySelectorAll('.termo-config-preview').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const fd = new FormData(form);
                fd.delete('_glpi_csrf_token');
                fd.set('preview_tipo', btn.dataset.tipo);
                const win = window.open('', '_blank');
                btn.disabled = true;
                post(form.dataset.preview, fd)
                    .then(function (r) {
                        if (!r.ok) return readJson(r).then(function (j) { throw new Error(j.message); });
                        return r.blob();
                    })
                    .then(function (blob) {
                        btn.disabled = false;
                        const url = URL.createObjectURL(blob);
                        if (win) { win.location.href = url; } else { window.location.href = url; }
                    })
                    .catch(function (err) {
                        btn.disabled = false;
                        if (win) win.close();
                        alert(err.message || err);
                    });
            });
        });

        // Campos do cabeçalho de controle de documentos só aparecem no modelo "controle"
        const layout = form.querySelector('#dc_layout');
        if (layout) {
            layout.addEventListener('change', function () {
                form.querySelectorAll('[data-dc]').forEach(function (el) {
                    el.hidden = el.dataset.dc !== layout.value;
                });
            });
        }

        // Volta os campos de texto para o padrão do plugin (só no formulário; salva quando clicar em Salvar)
        const defaults = JSON.parse(document.getElementById('assetterms-default-texts').textContent);
        form.querySelector('.termo-config-default').addEventListener('click', function () {
            if (!confirm('Trocar o texto dos dois termos pelo texto padrão do plugin? A mudança só vale depois de clicar em Salvar.')) return;
            Object.keys(defaults).forEach(function (tipo) {
                form.querySelector('[name="' + tipo + '_titulo"]').value = defaults[tipo].titulo;
                form.querySelector('[name="' + tipo + '_declaracao"]').value = defaults[tipo].declaracao;
                form.querySelector('[name="' + tipo + '_compromissos"]').value = defaults[tipo].compromissos.join('\n');
                form.querySelector('[name="' + tipo + '_ciencia"]').value = defaults[tipo].ciencia;
            });
        });
    }

    function scan() {
        const config = document.getElementById('form-assetterms-config');
        if (config) initConfig(config);
        const tab = document.getElementById('form-termo-responsabilidade');
        if (tab) initTab(tab);
        const sign = document.getElementById('form-termo-assinatura');
        if (sign) initSign(sign);
    }

    new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
})();
