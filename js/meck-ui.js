/**
 * Meck UI — normaliza botões e adiciona ícones padrão em todo o sistema
 */
(function () {
    document.documentElement.classList.add('meck-theme');
    if (document.body) document.body.classList.add('meck-app');
    else document.addEventListener('DOMContentLoaded', () => document.body.classList.add('meck-app'));

    const ICON_MAP = [
        { match: /^(novo|nova|adicionar|criar)/i, icon: 'fa-plus' },
        { match: /^(guardar|salvar|gravar|atualizar|atualizar)/i, icon: 'fa-floppy-disk' },
        { match: /^(editar|alterar)/i, icon: 'fa-pen-to-square' },
        { match: /^(eliminar|apagar|remover|excluir)/i, icon: 'fa-trash' },
        { match: /^(cancelar|fechar)/i, icon: 'fa-xmark' },
        { match: /^(pesquisar|procurar|filtrar)/i, icon: 'fa-magnifying-glass' },
        { match: /^(exportar|download|descarregar)/i, icon: 'fa-download' },
        { match: /^(importar|upload|carregar)/i, icon: 'fa-upload' },
        { match: /^(entrar|login|aceder)/i, icon: 'fa-right-to-bracket' },
        { match: /^(sair|logout)/i, icon: 'fa-right-from-bracket' },
        { match: /^(enviar|submit)/i, icon: 'fa-paper-plane' },
        { match: /^(confirmar|aprovar|sim)/i, icon: 'fa-check' },
        { match: /^(rejeitar|recusar|não|nao)/i, icon: 'fa-ban' },
        { match: /^(ver|visualizar|detalhe|detalhes)/i, icon: 'fa-eye' },
        { match: /^(imprimir)/i, icon: 'fa-print' },
        { match: /^(relatório|relatorio)/i, icon: 'fa-chart-pie' },
        { match: /^(chamada|ligar)/i, icon: 'fa-phone' },
        { match: /^(vídeo|video)/i, icon: 'fa-video' },
        { match: /^(anexar|ficheiro|arquivo)/i, icon: 'fa-paperclip' },
    ];

    function textOf(el) {
        return (el.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function hasIcon(el) {
        return !!el.querySelector('i, svg, img');
    }

    function ensureFlex(el) {
        const style = window.getComputedStyle(el);
        if (style.display === 'inline') el.style.display = 'inline-flex';
        else if (style.display === 'block' && !el.classList.contains('w-full')) {
            /* keep block for full-width CTAs */
        }
        if (!el.className.includes('items-center') && !el.style.alignItems) {
            el.style.alignItems = 'center';
            el.style.justifyContent = el.classList.contains('w-full') ? 'center' : (style.justifyContent || 'center');
            el.style.gap = el.style.gap || '0.5rem';
        }
    }

    function addIcon(el, iconClass) {
        if (hasIcon(el)) return;
        const icon = document.createElement('i');
        icon.className = 'fa-solid ' + iconClass;
        icon.setAttribute('aria-hidden', 'true');
        el.prepend(icon);
        ensureFlex(el);
    }

    function normalizeButton(el) {
        if (el.dataset.meckUi === '1') return;
        if (el.closest('.fa, .swal2-container, [x-cloak]')) return;

        const cls = el.className || '';
        const isPrimary =
            /\bbg-blue-600\b|\bbg-blue-500\b|\bbg-indigo-600\b|\bbtn-primary\b|\blogin-btn\b|\bbg-teams-600\b/.test(cls);
        const isDanger = /\bbg-red-600\b|\bbg-red-500\b|\bbtn-danger\b/.test(cls);
        const isSuccess = /\bbg-emerald-600\b|\bbg-green-600\b|\bbtn-success\b/.test(cls);

        if (isPrimary || isDanger || isSuccess || el.classList.contains('btn')) {
            ensureFlex(el);
            if (!/\brounded-/.test(cls) && !el.classList.contains('btn-icon')) {
                el.style.borderRadius = el.style.borderRadius || '0.9rem';
            }
        }

        const label = textOf(el);
        if (label && !hasIcon(el) && label.length < 48) {
            for (const rule of ICON_MAP) {
                if (rule.match.test(label)) {
                    addIcon(el, rule.icon);
                    break;
                }
            }
        }

        el.dataset.meckUi = '1';
    }

    function scan(root) {
        const scope = root || document;
        scope.querySelectorAll('button, a.button, a.btn, input[type="submit"], input[type="button"]').forEach(normalizeButton);
        scope.querySelectorAll('a.bg-blue-600, a.bg-indigo-600, a.bg-blue-500').forEach(normalizeButton);
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) return meta.getAttribute('content');
        // fallback cookie double-submit
        const m = document.cookie.match(/(?:^|;\s*)meck_csrf=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : '';
    }

    function installCsrf() {
        if (window.__meckCsrfInstalled) return;
        window.__meckCsrfInstalled = true;

        const originalFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            init = init || {};
            const method = String((init.method || (typeof input === 'object' && input.method) || 'GET')).toUpperCase();
            if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
                const headers = new Headers(init.headers || {});
                const token = csrfToken();
                if (token) {
                    if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
                    if (!headers.has('X-Requested-With')) headers.set('X-Requested-With', 'XMLHttpRequest');
                }
                init.headers = headers;

                // Se body é FormData e não tem csrf_token, acrescenta
                if (token && init.body instanceof FormData && !init.body.has('csrf_token')) {
                    init.body.append('csrf_token', token);
                }
            }
            return originalFetch(input, init).then(async (res) => {
                if (res.status === 419) {
                    try {
                        const clone = res.clone();
                        const data = await clone.json();
                        if (data && data.csrf_refresh) {
                            let meta = document.querySelector('meta[name="csrf-token"]');
                            if (!meta) {
                                meta = document.createElement('meta');
                                meta.setAttribute('name', 'csrf-token');
                                document.head.appendChild(meta);
                            }
                            meta.setAttribute('content', data.csrf_refresh);
                            document.cookie = 'meck_csrf=' + encodeURIComponent(data.csrf_refresh) + '; path=/; SameSite=Lax';
                        }
                    } catch (_) {}
                }
                return res;
            });
        };

        // XMLHttpRequest
        const open = XMLHttpRequest.prototype.open;
        const send = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method, url) {
            this.__meckMethod = String(method || 'GET').toUpperCase();
            return open.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function (body) {
            if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(this.__meckMethod || '')) {
                const token = csrfToken();
                if (token) {
                    try { this.setRequestHeader('X-CSRF-Token', token); } catch (_) {}
                }
            }
            return send.apply(this, arguments);
        };

        document.addEventListener('submit', (ev) => {
            const form = ev.target;
            if (!(form instanceof HTMLFormElement)) return;
            const method = (form.getAttribute('method') || form.method || 'get').toLowerCase();
            if (method === 'get') return;
            let input = form.querySelector('input[name="csrf_token"]');
            const token = csrfToken();
            if (!token) return;
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                form.appendChild(input);
            }
            input.value = token;
        }, true);

        // Injeta em forms já existentes
        document.querySelectorAll('form').forEach((form) => {
            const method = (form.getAttribute('method') || form.method || 'get').toLowerCase();
            if (method === 'get') return;
            if (form.querySelector('input[name="csrf_token"]')) return;
            const token = csrfToken();
            if (!token) return;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = token;
            form.appendChild(input);
        });
    }

    function boot() {
        installCsrf();
        scan(document);
        const obs = new MutationObserver((mutations) => {
            for (const m of mutations) {
                m.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;
                    if (node.matches && (node.matches('button, a.bg-blue-600, a.btn, form') || node.querySelector)) {
                        scan(node);
                        if (node.matches && node.matches('form')) {
                            const method = (node.getAttribute('method') || 'post').toLowerCase();
                            if (method !== 'get' && !node.querySelector('input[name="csrf_token"]')) {
                                const token = csrfToken();
                                if (token) {
                                    const input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = 'csrf_token';
                                    input.value = token;
                                    node.appendChild(input);
                                }
                            }
                        }
                    }
                });
            }
        });
        if (document.body) obs.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
