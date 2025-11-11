(function () {
    'use strict';

    if (typeof AuroraChatConfig === 'undefined') {
        return;
    }

    const formatTime = () => {
        const date = new Date();
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const mdToHtml = (md) => {
        if (!md) return '';
        let s = String(md);
        // Code blocks
        s = s.replace(/```([a-z0-9_\-]+)?\n([\s\S]*?)\n```/gi, (m, lang, code) => {
            const esc = code.replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
            const cls = lang ? ` class="language-${lang}"` : '';
            return `<pre><code${cls}>${esc}</code></pre>`;
        });
        // Inline code
        s = s.replace(/`([^`]+)`/g, (m, code) => `<code>${code.replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]))}</code>`);
        // Horizontal rule: lines with ** or *** alone
        s = s.replace(/^\s*\*{2,3}\s*$/gm, '<hr/>');
        // Bold/italic
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/\*([^*]+)\*/g, '<em>$1</em>');
        // Headings
        s = s.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>').replace(/^##\s+(.+)$/gm, '<h2>$1</h2>').replace(/^#\s+(.+)$/gm, '<h1>$1</h1>');
        // Links
        s = s.replace(/\[([^\]]+)\]\((https?:[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1<\/a>');
        // Bare URLs to links
        s = s.replace(/(^|\s)(https?:\/\/[^\s<]+)(?=$|\s)/g, (m, pre, url) => `${pre}<a href="${url}" target="_blank" rel="noopener noreferrer">${url}<\/a>`);
        // Paragraphs
        s = s.split(/\n{2,}/).map(p => `<p>${p.replace(/\n/g,'<br>')}</p>`).join('');
        // Unwrap hr inside p
        s = s.replace(/<p><hr\/?><\/p>/g, '<hr/>');
        return s;
    };

    const createMessage = (layout, role, html) => {
        const article = document.createElement('article');
        const prefix = layout === 'session' ? 'aurora-session' : 'aurora-bubble';
        article.className = `${prefix}__message ${role}`;

        const bubble = document.createElement('div');
        bubble.className = `${prefix}__bubble`;
        bubble.innerHTML = html;
        article.appendChild(bubble);

        if (layout === 'session') {
            const timestamp = document.createElement('span');
            timestamp.className = 'aurora-session__timestamp';
            timestamp.textContent = formatTime();
            article.appendChild(timestamp);
        }

        return article;
    };

    const createAudioMessage = (layout, src, type) => {
        const article = document.createElement('article');
        const prefix = layout === 'session' ? 'aurora-session' : 'aurora-bubble';
        article.className = `${prefix}__message is-bot`;
        const bubble = document.createElement('div');
        bubble.className = `${prefix}__bubble`;
        const audio = document.createElement('audio');
        audio.className = 'aurora-audio-player';
        audio.controls = true;
        audio.preload = 'none';
        try {
            if (src && /^data:audio\//.test(src)) {
                audio.src = src;
            } else if (src) {
                // URL
                audio.src = src;
                audio.crossOrigin = 'anonymous';
            }
            if (type) {
                // some browsers use the type on <source>, but <audio type> is fine to hint
                audio.setAttribute('type', type);
            }
        } catch(e) { /* no-op */ }
        bubble.appendChild(audio);
        article.appendChild(bubble);
        if (layout === 'session') {
            const ts = document.createElement('span');
            ts.className = 'aurora-session__timestamp';
            ts.textContent = formatTime();
            article.appendChild(ts);
        }
        return article;
    };

    const scrollToBottom = (element) => {
        element.scrollTop = element.scrollHeight;
    };

    // Renderização simples de anexos (imagens ou links)
    const renderAttachments = (urls, messagesEl) => {
        if (!Array.isArray(urls) || !urls.length || !messagesEl) return;
        const cont = document.createElement('div');
        cont.className = 'aurora-attachments';
        urls.forEach((u) => {
            try {
                const clean = String(u);
                const ext = (clean.split('?')[0].split('#')[0].split('.').pop() || '').toLowerCase();
                const isImg = ['jpg','jpeg','png','gif','webp','avif','svg','bmp'].includes(ext);
                const a = document.createElement('a');
                a.href = clean; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.className = 'aurora-attachment-cell';
                if (isImg) {
                    const img = document.createElement('img'); img.src = clean; img.alt = 'anexo'; img.loading = 'lazy';
                    a.appendChild(img);
                } else {
                    a.textContent = clean;
                    a.classList.add('is-doc');
                }
                cont.appendChild(a);
            } catch(e) { /* no-op */ }
        });
        messagesEl.appendChild(cont);
    };

    // Pré-visualização inline dentro da mensagem do bot (dinâmico, sem depender da extensão)
    const renderInlinePreviews = (botMessageEl) => {
        if (!botMessageEl) return;
        const bubble = botMessageEl.querySelector('[class$="__bubble"]');
        if (!bubble) return;

    const links = Array.from(bubble.querySelectorAll('a[href^="http"]'));
        if (!links.length) return;
    // Remover target _blank para evitar abertura imediata em nova aba (nós tratamos o clique)
    links.forEach((a) => { try { a.removeAttribute('target'); } catch(e){} });

        const getYtId = (url) => {
            try {
                const u = new URL(url);
                if (u.hostname.includes('youtu.be')) return u.pathname.slice(1);
                if (u.hostname.includes('youtube.com')) {
                    if (u.pathname.startsWith('/shorts/')) return u.pathname.split('/')[2];
                    return u.searchParams.get('v');
                }
            } catch(e) { return null; }
            return null;
        };

        const buildImgPreview = (url, alt) => {
            const a = document.createElement('a');
            a.href = url; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.className = 'aurora-attachment-cell';
            const img = document.createElement('img'); img.src = url; img.alt = alt || 'preview'; img.loading = 'lazy';
            a.appendChild(img);
            return a;
        };

        const buildLinkCard = (url) => {
            let host = '';
            try { host = new URL(url).hostname.replace(/^www\./,''); } catch(_) { host = url; }
            const a = document.createElement('a');
            a.href = url; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.className = 'aurora-link-card';
            const fav = document.createElement('div'); fav.className = 'aurora-link-card__favicon';
            const img = document.createElement('img');
            try {
                const u = new URL(url);
                img.src = `https://www.google.com/s2/favicons?domain=${encodeURIComponent(u.hostname)}&sz=64`;
            } catch(_) { img.src = ''; }
            fav.appendChild(img);
            const hostEl = document.createElement('div'); hostEl.className = 'aurora-link-card__host'; hostEl.textContent = host;
            a.appendChild(fav); a.appendChild(hostEl);
            return a;
        };

        const buildMshotPreview = (url) => {
            const a = document.createElement('a');
            a.href = url; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.className = 'aurora-attachment-cell';
            const img = document.createElement('img');
            img.alt = 'preview'; img.loading = 'lazy';
            img.src = 'https://s.wordpress.com/mshots/v1/' + encodeURIComponent(url) + '?w=320';
            const showImage = () => {
                a.classList.remove('is-doc');
                a.innerHTML = '';
                const im = document.createElement('img');
                im.alt = 'preview'; im.loading = 'lazy'; im.src = img.src; a.appendChild(im);
            };
            const showCard = () => {
                a.replaceWith(buildLinkCard(url));
            };
            const isMobile = (typeof window !== 'undefined') && (window.matchMedia && window.matchMedia('(max-width: 600px)').matches);
            const timeoutMs = isMobile ? 8000 : 4500;
            const to = setTimeout(() => { showCard(); }, timeoutMs);
            img.onerror = () => { clearTimeout(to); showCard(); };
            img.onload = () => { clearTimeout(to); if (img.naturalWidth < 24) showCard(); else showImage(); };
            // Append a temp image to start loading; will be swapped on resolve
            a.appendChild(img);
            return a;
        };

        const isDocExt = (url) => {
            const ext = (url.split('?')[0].split('#')[0].split('.').pop() || '').toLowerCase();
            return ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar','csv'].includes(ext);
        };

        links.forEach((link) => {
            const url = link.href;
            // Substitui o link imediatamente por um skeleton para evitar flicker do texto
            const holder = document.createElement('span');
            holder.className = 'aurora-inline-thumb';
            const sk = document.createElement('span'); sk.className = 'aurora-thumb-skeleton';
            holder.appendChild(sk);
            link.replaceWith(holder);
            // 1) YouTube
            const yid = getYtId(url);
            if (yid) {
                const prev = buildImgPreview(`https://img.youtube.com/vi/${yid}/hqdefault.jpg`, 'YouTube');
                holder.innerHTML = '';
                holder.appendChild(prev);
                return;
            }
            // 2) Tenta imagem real (sem depender de extensão)
            const probe = new Image();
            probe.loading = 'eager';
            let decided = false;
            const ok = () => {
                if (decided) return; decided = true;
                const prev = buildImgPreview(url, 'imagem');
                holder.innerHTML = '';
                holder.appendChild(prev);
            };
            const fail = () => {
                if (decided) return; decided = true;
                // 3) Se parecer documento por extensão, usa célula doc; caso contrário, screenshot do site
                if (isDocExt(url)) {
                    const a = document.createElement('a');
                    a.href = url; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.className = 'aurora-attachment-cell is-doc';
                    a.textContent = url;
                    holder.innerHTML = '';
                    holder.appendChild(a);
                } else {
                    const prev = buildMshotPreview(url);
                    holder.innerHTML = '';
                    holder.appendChild(prev);
                }
            };
            // Tempo maior no mobile/3G para evitar cair em fallback prematuro
            const isMobile = (typeof window !== 'undefined') && (window.matchMedia && window.matchMedia('(max-width: 600px)').matches);
            const timeout = setTimeout(() => { fail(); }, isMobile ? 7000 : 3000);
            probe.onload = () => { clearTimeout(timeout); if (probe.naturalWidth > 0 && probe.naturalHeight > 0) ok(); else fail(); };
            probe.onerror = () => { clearTimeout(timeout); fail(); };
            try { probe.src = url; } catch(e) { clearTimeout(timeout); fail(); }
        });
    };

    // ============== Modal / Lightbox ==============
    const AuroraModal = (() => {
        let root, contentEl;
        let scale = 1, fitScale = 1, imgEl = null, tx = 0, ty = 0, labelBtn = null;

        const ensure = () => {
            if (root) return root;
            root = document.createElement('div');
            root.className = 'aurora-modal';
                        root.innerHTML = `
                            <div class="aurora-modal__dialog" role="dialog" aria-modal="true" aria-label="Visualização">
                                <div class="aurora-modal__header">
                                    <div class="aurora-modal__actions">
                                        <button type="button" class="aurora-modal__btn" data-act="open-new">Abrir nova guia</button>
                                        <button type="button" class="aurora-modal__btn aurora-modal__btn--primary" data-act="close">Fechar</button>
                                    </div>
                                </div>
                                <div class="aurora-modal__content"></div>
                            </div>`;
            document.body.appendChild(root);
            contentEl = root.querySelector('.aurora-modal__content');

            // Backdrop close
            root.addEventListener('click', (e) => {
                if (e.target === root) close();
            });
            // Buttons
            root.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-act]');
                if (!btn) return;
                const act = btn.getAttribute('data-act');
                if (act === 'close') close();
                if (act === 'open-new' && root.dataset.url) window.open(root.dataset.url, '_blank', 'noopener');
            });
            // Esc
            document.addEventListener('keydown', (e) => { if (root.classList.contains('is-open') && e.key === 'Escape') close(); });
            return root;
        };

        const applyTransform = () => {
            if (!imgEl) return;
            imgEl.style.transform = `translate(calc(-50% + ${tx}px), calc(-50% + ${ty}px)) scale(${scale})`;
        };

        const updateLabel = () => {
            if (!labelBtn) return;
            const pct = Math.round((scale / (fitScale || 1)) * 100);
            labelBtn.textContent = `${pct}%`;
        };

        const setImageZoomUI = () => {
            const wrap = document.createElement('div');
            wrap.className = 'aurora-modal__image-wrap';
            wrap.appendChild(imgEl);
            const bar = document.createElement('div');
            bar.className = 'aurora-modal__zoombar';
            const mk = (label, step) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'aurora-zoombtn';
                b.textContent = label;
                b.addEventListener('click', () => {
                    const minS = Math.max(0.25 * fitScale, 0.1);
                    scale = Math.min(6 * fitScale, Math.max(minS, scale + step));
                    tx = 0; ty = 0;
                    applyTransform();
                    updateLabel();
                });
                return b;
            };
            // Ordem: -, +, 100%
            bar.appendChild(mk('−', -0.25 * (fitScale || 1)));
            bar.appendChild(mk('+', 0.25 * (fitScale || 1)));
            labelBtn = document.createElement('button');
            labelBtn.type = 'button';
            labelBtn.className = 'aurora-zoombtn aurora-zoombtn--label';
            labelBtn.textContent = '100%';
            labelBtn.addEventListener('click', () => { scale = fitScale; tx = 0; ty = 0; applyTransform(); updateLabel(); });
            bar.appendChild(labelBtn);
            wrap.appendChild(bar);

            // Drag to pan when zoomed
            let isDown = false, startX = 0, startY = 0;
            wrap.addEventListener('mousedown', (e) => { if (scale <= 1) return; isDown = true; startX = e.clientX - tx; startY = e.clientY - ty; wrap.style.cursor = 'grabbing'; });
            window.addEventListener('mouseup', () => { isDown = false; wrap.style.cursor = ''; });
            window.addEventListener('mousemove', (e) => { if (!isDown) return; tx = e.clientX - startX; ty = e.clientY - startY; applyTransform(); });
            // Touch
            wrap.addEventListener('touchstart', (e) => { if (scale <= fitScale) return; const t = e.touches[0]; isDown = true; startX = t.clientX - tx; startY = t.clientY - ty; }, {passive:true});
            wrap.addEventListener('touchend', () => { isDown = false; }, {passive:true});
            wrap.addEventListener('touchmove', (e) => { if (!isDown) return; const t = e.touches[0]; tx = t.clientX - startX; ty = t.clientY - startY; applyTransform(); }, {passive:true});
            // Wheel zoom
            wrap.addEventListener('wheel', (e) => {
                e.preventDefault();
                const delta = Math.sign(e.deltaY) * -0.15 * (fitScale || 1); // invert to natural
                const minS = Math.max(0.25 * fitScale, 0.1);
                scale = Math.min(6 * fitScale, Math.max(minS, scale + delta));
                applyTransform();
                updateLabel();
            }, {passive:false});

            // Initialize
            updateLabel();

            return wrap;
        };

        const close = () => {
            if (!root) return;
            root.classList.remove('is-open');
            contentEl.innerHTML = '';
            document.documentElement.classList.remove('aurora-chat-lock-scroll');
        };

        const openURL = (url, title, isImage) => {
            ensure();
            root.dataset.url = url;
            contentEl.innerHTML = '';
            document.documentElement.classList.add('aurora-chat-lock-scroll');

            const openAsImage = () => {
                imgEl = new Image();
                imgEl.className = 'aurora-modal__image';
                imgEl.alt = title || '';
                imgEl.onload = () => {
                    // montar UI
                    contentEl.innerHTML = '';
                    const wrap = setImageZoomUI();
                    contentEl.appendChild(wrap);
                    // calcular escala de ajuste (fit) usando dimensões naturais e container
                    const rect = wrap.getBoundingClientRect();
                    const iw = Math.max(1, imgEl.naturalWidth || imgEl.width);
                    const ih = Math.max(1, imgEl.naturalHeight || imgEl.height);
                    fitScale = Math.min(rect.width / iw, rect.height / ih);
                    if (!isFinite(fitScale) || fitScale <= 0) fitScale = 1;
                    scale = fitScale; tx = 0; ty = 0;
                    applyTransform();
                    updateLabel();
                };
                imgEl.src = url;
                root.classList.add('is-open');
            };

            const openAsIframe = () => {
                const iframe = document.createElement('iframe');
                iframe.className = 'aurora-modal__iframe';
                iframe.referrerPolicy = 'no-referrer-when-downgrade';
                iframe.src = url;
                let loaded = false;
                iframe.addEventListener('load', () => { loaded = true; });
                contentEl.innerHTML = '';
                contentEl.appendChild(iframe);
                root.classList.add('is-open');
                setTimeout(() => {
                    if (loaded) return;
                    const tip = document.createElement('div');
                    tip.className = 'aurora-modal__fallback';
                    tip.innerHTML = `<div><p>Se o conteúdo não aparecer, ele pode bloquear a exibição embutida.</p><button type=\"button\" class=\"aurora-modal__btn\" data-act=\"open-new\">Abrir em nova guia</button></div>`;
                    contentEl.appendChild(tip);
                }, 1500);
            };

            if (isImage) { openAsImage(); return; }

            // Probe: muitos links de imagem não possuem extensão; testa como <img> antes de iframe
            let decided = false;
            const probe = new Image();
            const decideImage = () => { if (decided) return; decided = true; openAsImage(); };
            const decideIframe = () => { if (decided) return; decided = true; openAsIframe(); };
            const timer = setTimeout(decideIframe, 900); // se não carregar rápido, assume iframe
            probe.onload = () => { clearTimeout(timer); if (probe.naturalWidth > 0 && probe.naturalHeight > 0) decideImage(); else decideIframe(); };
            probe.onerror = () => { clearTimeout(timer); decideIframe(); };
            try { probe.src = url; } catch(e) { clearTimeout(timer); decideIframe(); }
        };

        return { openURL, close };
    })();

    // ============== Toast / Popup de notificação ==============
    const AuroraToast = (() => {
        let globalContainer = null, styled = false;
        const ensure = (scopedRoot) => {
            if (!styled) {
                const style = document.createElement('style');
                style.textContent = `
                .aurora-toast-container{position:fixed;right:16px;bottom:16px;z-index:100000;display:flex;flex-direction:column;gap:8px;pointer-events:none}
                .aurora-toast{background:#111827;color:#fff;border-radius:10px;padding:12px 14px;box-shadow:0 10px 25px rgba(0,0,0,.25);max-width:360px;min-width:240px;pointer-events:auto;display:grid;grid-template-columns:auto 1fr auto;align-items:start;gap:10px}
                .aurora-toast.is-error{background:#991b1b}
                .aurora-toast__icon{font-size:16px;line-height:1.1;margin-top:2px}
                .aurora-toast__body{display:block}
                .aurora-toast__title{margin:0 0 2px;font-size:13px;font-weight:700}
                .aurora-toast__text{margin:0;font-size:12px;opacity:.95}
                .aurora-toast__close{border:0;background:transparent;color:#fff;opacity:.9;cursor:pointer;font-size:16px;line-height:1;padding:2px}
                .aurora-toast__close:hover{opacity:1}
                `;
                document.head.appendChild(style);
                styled = true;
            }
            // Preferir um container fornecido dentro do escopo do chat
            if (scopedRoot) {
                let c = scopedRoot.querySelector('[data-aurora-role="toast-container"]');
                if (c) {
                    // ajustar posicionamento local (não fixed) se desejar; manter fixed por consistência
                    c.classList.add('aurora-toast-container');
                    return c;
                }
            }
            if (!globalContainer) {
                globalContainer = document.createElement('div');
                globalContainer.className = 'aurora-toast-container';
                document.body.appendChild(globalContainer);
            }
            return globalContainer;
        };
        const show = (message, type = 'info', title, scopedRoot) => {
            const root = ensure(scopedRoot);
            const el = document.createElement('div');
            el.className = 'aurora-toast' + (type === 'error' ? ' is-error' : '');
            const icon = document.createElement('div');
            icon.className = 'aurora-toast__icon';
            icon.textContent = type === 'error' ? '⚠️' : 'ℹ️';
            const body = document.createElement('div');
            body.className = 'aurora-toast__body';
            const h = document.createElement('div');
            h.className = 'aurora-toast__title';
            h.textContent = title || (type === 'error' ? 'Atenção' : 'Informação');
            const p = document.createElement('div');
            p.className = 'aurora-toast__text';
            p.textContent = String(message || '');
            body.appendChild(h); body.appendChild(p);
            const close = document.createElement('button');
            close.className = 'aurora-toast__close';
            close.setAttribute('aria-label','Fechar');
            close.textContent = '×';
            close.addEventListener('click', () => { try { el.remove(); } catch(_){} });
            el.appendChild(icon); el.appendChild(body); el.appendChild(close);
            root.appendChild(el);
            setTimeout(() => { try { el.remove(); } catch(_){} }, 4500);
            return el;
        };
        return { show };
    })();

    const isImageURL = (url) => /\.(png|jpe?g|gif|bmp|webp|svg)(\?.*)?$/i.test(url);

    const sendRequest = async (agentId, message, session, userMeta) => {
        const payload = new FormData();
        payload.append('action', 'aurora_chat_send_message');
        payload.append('nonce', AuroraChatConfig.nonce);
        payload.append('agentId', agentId);
        payload.append('message', message);
        payload.append('session', session);
        if (userMeta && typeof userMeta === 'object') {
            if (userMeta.name) payload.append('userName', userMeta.name);
            if (userMeta.email) payload.append('userEmail', userMeta.email);
            if (userMeta.contact) payload.append('userContact', userMeta.contact);
        }

        const response = await fetch(AuroraChatConfig.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        });

        if (!response.ok) {
            throw new Error('Request error');
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.data && data.data.message ? data.data.message : 'Unknown error');
        }

        return data.data;
    };

    // Envia áudio base64 para transcrição no webhook remoto via AJAX WordPress
    const sendAudio = async (agentId, audioBase64, session, userMeta) => {
        const payload = new FormData();
        payload.append('action', 'aurora_chat_send_audio');
        payload.append('nonce', AuroraChatConfig.nonce);
        payload.append('agentId', agentId);
        payload.append('audio', audioBase64);
        payload.append('session', session);
        if (userMeta && typeof userMeta === 'object') {
            if (userMeta.name) payload.append('userName', userMeta.name);
            if (userMeta.email) payload.append('userEmail', userMeta.email);
            if (userMeta.contact) payload.append('userContact', userMeta.contact);
        }

        const response = await fetch(AuroraChatConfig.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        });

        if (!response.ok) throw new Error('Request error');
        const data = await response.json();
        if (!data.success) throw new Error(data.data && data.data.message ? data.data.message : 'Unknown error');
        return data.data;
    };

    const initChat = (container) => {
    const layout = container.classList.contains('aurora-chat-layout-session') ? 'session' : 'bubble';
    const messages = container.querySelector('[data-aurora-role="messages"]');
        const composer = container.querySelector('[data-aurora-role="composer"]');
        const footer = container.querySelector('[data-aurora-role="footer"]');
        const overlay = container.querySelector('[data-aurora-role="overlay"]');
        const welcome = container.querySelector('[data-aurora-role="welcome"]');
        const startButton = container.querySelector('[data-aurora-role="start"]');
    const statusEl = container.querySelector('[data-aurora-role="status"]');
    let micBtn = container.querySelector('[data-aurora-role="mic"]');
        // Popular avatar/brand com ícone do site ou inicial e nome do agente
        try {
            const brand = (typeof AuroraChatConfig !== 'undefined' && AuroraChatConfig.brand) ? AuroraChatConfig.brand : null;
            const agentName = container.dataset.agentName || '';
            if (brand) {
                if (layout === 'session') {
                    const avatar = container.querySelector('.aurora-session__avatar');
                    if (avatar) {
                        if (brand.icon) {
                            avatar.textContent = '';
                            avatar.style.background = `center/cover no-repeat url(${brand.icon})`;
                            avatar.setAttribute('aria-hidden','true');
                        } else if (brand.initial) {
                            avatar.style.background = '';
                            avatar.textContent = brand.initial;
                        }
                    }
                    // Atualiza o título com o nome do agente (se houver)
                    if (agentName) {
                        const titleEl = container.querySelector('.aurora-session__title');
                        if (titleEl) titleEl.textContent = agentName;
                    }
                } else {
                    const brandIcon = container.querySelector('.aurora-bubble__brand-icon');
                    if (brandIcon) {
                        if (brand.icon) {
                            brandIcon.textContent = '';
                            brandIcon.style.background = `center/cover no-repeat url(${brand.icon})`;
                            brandIcon.setAttribute('aria-hidden','true');
                        } else if (brand.initial) {
                            brandIcon.style.background = '';
                            brandIcon.textContent = brand.initial;
                        }
                    }
                    // Atualiza o nome de marca com o nome do agente (se houver)
                    if (agentName) {
                        const nameEl = container.querySelector('.aurora-bubble__brand-name');
                        if (nameEl) nameEl.textContent = agentName;
                    }
                }
            }
        } catch(e) { /* no-op */ }
    const handoffButton = null; // removido

        if (!messages || !composer) {
            return;
        }

        const input = composer.querySelector('textarea, input');
        if (!input) {
            return;
        }

        // ======= Tema: alternância claro/escuro =======
        const THEME_KEY = 'aurora-theme';
        const getTheme = () => {
            try { return localStorage.getItem(THEME_KEY) || 'light'; } catch(_) { return 'light'; }
        };
        const setTheme = (theme) => {
            try { localStorage.setItem(THEME_KEY, theme); } catch(_) {}
        };
        const applyTheme = (theme) => {
            const dark = theme === 'dark';
            document.body.classList.toggle('aurora-theme-dark', dark);
            // atualiza ícone dos botões disponíveis neste container
            container.querySelectorAll('[data-aurora-role="theme-toggle"]').forEach((btn) => {
                btn.textContent = dark ? '☀' : '🌓';
                btn.setAttribute('aria-label', dark ? 'Alternar para tema claro' : 'Alternar para tema escuro');
                btn.setAttribute('title', dark ? 'Alternar para tema claro' : 'Alternar para tema escuro');
            });
        };
        // aplicar tema salvo ao iniciar
        applyTheme(getTheme());
        // listeners de clique dos botões
        container.querySelectorAll('[data-aurora-role="theme-toggle"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const next = getTheme() === 'dark' ? 'light' : 'dark';
                setTheme(next);
                applyTheme(next);
            });
        });

        const generateSession = () => {
            if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
                return crypto.randomUUID();
            }
            return `aurora-${Math.random().toString(16).slice(2)}${Date.now()}`;
        };

        const applyStatusAppearance = (state) => {
            if (!statusEl) return;
            try {
                statusEl.classList.remove('is-online','is-offline');
                const s = (state || '').toLowerCase();
                if (s === 'offline') statusEl.classList.add('is-offline');
                else statusEl.classList.add('is-online');
                // Ajusta também uma classe no container para CSS global se necessário
                container.classList.remove('aurora-status-online','aurora-status-offline');
                container.classList.add(s === 'offline' ? 'aurora-status-offline' : 'aurora-status-online');
            } catch(_){}
        };

        const setStatus = (() => {
            let timer;
            return (mode, value) => {
                if (!statusEl) {
                    return;
                }

                if (timer) {
                    clearTimeout(timer);
                    timer = null;
                }

                switch (mode) {
                    case 'responding':
                        statusEl.textContent = AuroraChatConfig.i18n.statusResponding || 'Respondendo…';
                        applyStatusAppearance('online');
                        break;
                    case 'transcribing':
                        statusEl.textContent = (AuroraChatConfig.i18n && AuroraChatConfig.i18n.statusTranscribing) || 'Transcrevendo…';
                        applyStatusAppearance('online');
                        break;
                    case 'complete':
                        statusEl.textContent = (AuroraChatConfig.i18n.statusComplete || 'Resposta em %ss').replace('%s', value || '1.0');
                        timer = setTimeout(() => setStatus('idle'), 2500);
                        applyStatusAppearance('online');
                        break;
                    case 'idle':
                    default:
                        // Se o admin marcou offline, exibimos o texto Offline; senão, Online
                        if (AuroraChatConfig && AuroraChatConfig.agentStatus === 'offline') {
                            statusEl.textContent = (AuroraChatConfig.i18n && AuroraChatConfig.i18n.statusOffline) || 'Offline';
                            applyStatusAppearance('offline');
                        } else {
                            statusEl.textContent = AuroraChatConfig.i18n.statusIdle || 'Online';
                            applyStatusAppearance('online');
                        }
                        break;
                }
            };
        })();

    setStatus('idle');

        // Garante presença do botão de microfone mesmo em templates antigos salvos no banco
        if (!micBtn && composer) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = layout === 'session' ? 'aurora-session__mic' : 'aurora-bubble__mic';
            btn.setAttribute('data-aurora-role', 'mic');
            btn.setAttribute('aria-label', 'Gravar mensagem de voz');
            btn.title = 'Gravar mensagem de voz';
            btn.innerHTML = '<span aria-hidden="true">🎤</span>';

            if (layout === 'session') {
                const toolbar = composer.querySelector('.aurora-session__toolbar') || composer;
                const sendBtn = toolbar.querySelector('[data-aurora-role="send"]');
                if (sendBtn && sendBtn.parentNode) {
                    sendBtn.parentNode.insertBefore(btn, sendBtn);
                } else {
                    toolbar.appendChild(btn);
                }
            } else {
                const sendBtn = composer.querySelector('[data-aurora-role="send"]');
                if (sendBtn && sendBtn.parentNode) {
                    sendBtn.parentNode.insertBefore(btn, sendBtn);
                } else {
                    composer.appendChild(btn);
                }
            }
            micBtn = btn;
        }

        // ======= Áudio: gravação estilo WhatsApp =======
        let mediaRecorder = null;
        let audioChunks = [];
        let isRecording = false;
        let isTranscribing = false;
        let cancelRecording = false;

        const disableComposer = (flag) => {
            try {
                input.disabled = !!flag;
                const sendBtn = container.querySelector('[data-aurora-role="send"]');
                if (sendBtn) sendBtn.disabled = !!flag;
                if (micBtn) micBtn.disabled = !!flag; // bloqueia mic tanto gravando quanto transcrevendo
            } catch(e){}
        };

        const buildRecordingUI = () => {
            const wrap = document.createElement('div');
            wrap.className = layout === 'session' ? 'aurora-session__recording' : 'aurora-bubble__recording';
            wrap.setAttribute('role','status');
            wrap.setAttribute('aria-live','polite');

            const wave = document.createElement('div');
            wave.className = 'aurora-voice-wave';
            for (let i=0;i<8;i++){ const bar=document.createElement('span'); wave.appendChild(bar); }

            const timer = document.createElement('span');
            timer.className = 'aurora-rec-timer';
            timer.textContent = '00:00';

            const actions = document.createElement('div');
            actions.className = 'aurora-rec-actions';

            const btnCancel = document.createElement('button');
            btnCancel.type = 'button'; btnCancel.className = 'aurora-rec-btn aurora-rec-cancel'; btnCancel.textContent = 'Cancelar';
            const btnSend = document.createElement('button');
            btnSend.type = 'button'; btnSend.className = 'aurora-rec-btn aurora-rec-send'; btnSend.textContent = 'Enviar';

            actions.appendChild(btnCancel); actions.appendChild(btnSend);
            wrap.appendChild(wave); wrap.appendChild(timer); wrap.appendChild(actions);

            return { wrap, timer, btnCancel, btnSend };
        };

        const Recording = { ui: null, timerId: null, startTs: 0 };

        const mountRecordingUI = () => {
            if (Recording.ui) return Recording.ui;
            const ui = buildRecordingUI();
            const composer = container.querySelector('[data-aurora-role="composer"]');
            if (composer) composer.parentNode.insertBefore(ui.wrap, composer);
            Recording.ui = ui;
            let secs = 0;
            Recording.startTs = Date.now();
            Recording.timerId = setInterval(() => {
                secs = Math.floor((Date.now()-Recording.startTs)/1000);
                const mm = String(Math.floor(secs/60)).padStart(2,'0');
                const ss = String(secs%60).padStart(2,'0');
                ui.timer.textContent = `${mm}:${ss}`;
            }, 500);
            ui.btnCancel.addEventListener('click', () => { cancelRecording = true; try { mediaRecorder && mediaRecorder.stop(); } catch(_){} });
            ui.btnSend.addEventListener('click', () => { cancelRecording = false; try { mediaRecorder && mediaRecorder.stop(); } catch(_){} });
            return ui;
        };

        const unmountRecordingUI = () => {
            if (Recording.timerId) { clearInterval(Recording.timerId); Recording.timerId = null; }
            const ui = Recording.ui; Recording.ui = null;
            if (ui && ui.wrap && ui.wrap.parentNode) ui.wrap.remove();
        };

        const readBlobAsBase64 = (blob) => new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(String(reader.result).split(',')[1] || '');
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });

    const startRecording = async () => {
            if (isRecording || isSending) return;
            ensureStarted();
            isRecording = true; cancelRecording = false; audioChunks = [];
            setStatus('responding'); // ou estado próprio visual
            disableComposer(true);
            input.value = '';
            input.setAttribute('placeholder','Gravando…');

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
            } catch (err) {
                isRecording = false; disableComposer(false); setStatus('idle');
                AuroraToast.show('Permissão de microfone negada ou não disponível.', 'error', 'Microfone', container);
                return;
            }

            const recUI = mountRecordingUI();
            if (micBtn) micBtn.setAttribute('data-aurora-recording','true');
            mediaRecorder.ondataavailable = (e) => { if (e.data && e.data.size > 0) audioChunks.push(e.data); };
            mediaRecorder.onstop = async () => {
                const blob = new Blob(audioChunks, { type: 'audio/webm' });
                // Encerrar tracks
                try { mediaRecorder.stream.getTracks().forEach(t => t.stop()); } catch(_){}
                unmountRecordingUI();
                isRecording = false;
                if (micBtn) micBtn.removeAttribute('data-aurora-recording');
                if (cancelRecording || blob.size === 0) {
                    disableComposer(false); setStatus('idle'); input.setAttribute('placeholder','Digite sua mensagem'); return;
                }
                // Transcrever
                isTranscribing = true; setStatus('transcribing');
                try {
                    const b64 = await readBlobAsBase64(blob);
                    const { transcript, session } = await sendAudio(agentId, b64, sessionId, prechatData);
                    // Não adicionar na thread; apenas preencher o input com a transcrição.
                    if (transcript) {
                        input.value = transcript;
                    }
                    setStatus('idle');
                } catch (e) {
                    console.error('[Aurora Chat][audio]', e);
                    AuroraToast.show('Falha ao transcrever áudio.', 'error', 'Transcrição', container);
                    setStatus('idle');
                } finally {
                    isTranscribing = false; disableComposer(false);
                }
            };
            mediaRecorder.start(250);
        };

        if (micBtn) {
            micBtn.addEventListener('click', () => { startRecording(); });
        }

        // Limite de caracteres por mensagem (data-max-chars)
        const maxChars = parseInt(container.dataset.maxChars || '0', 10);
        let limitEl = container.querySelector('[data-aurora-role="char-limit"]');
        // Se o elemento do contador não existir no HTML do template, criamos dinamicamente
        if (maxChars > 0 && !limitEl) {
            if (layout === 'session') {
                const toolbar = container.querySelector('.aurora-session__toolbar');
                if (toolbar) {
                    limitEl = document.createElement('span');
                    limitEl.className = 'aurora-session__limit';
                    limitEl.setAttribute('data-aurora-role','char-limit');
                    limitEl.setAttribute('aria-live','polite');
                    limitEl.hidden = true;
                    // Inserimos antes do botão enviar para ficar junto aos controles
                    const sendBtn = toolbar.querySelector('[data-aurora-role="send"]');
                    if (sendBtn && sendBtn.parentNode) {
                        toolbar.insertBefore(limitEl, sendBtn);
                    } else {
                        toolbar.appendChild(limitEl);
                    }
                }
            } else {
                // No layout bolha, preferimos o contador abaixo do input (fora do form), acima do footer
                const place = container.querySelector('[data-aurora-role="char-limit"]')
                           || container.querySelector('.aurora-bubble__footer');
                if (place) {
                    limitEl = document.createElement('div');
                    limitEl.className = 'aurora-bubble__limit';
                    limitEl.setAttribute('data-aurora-role','char-limit');
                    limitEl.setAttribute('aria-live','polite');
                    limitEl.hidden = true;
                    if (place.classList && place.classList.contains('aurora-bubble__footer')) {
                        place.parentNode.insertBefore(limitEl, place);
                    } else if (place.parentNode) {
                        place.parentNode.insertBefore(limitEl, place.nextSibling);
                    }
                }
            }
        }

        // Notificações inline (substitui alert)
        let noticeEl = container.querySelector('[data-aurora-role="notice"]');
        const ensureNotice = () => {
            if (noticeEl) return noticeEl;
            if (layout === 'session') {
                const toolbar = container.querySelector('.aurora-session__toolbar');
                if (!toolbar) return null;
                noticeEl = document.createElement('span');
                noticeEl.className = 'aurora-session__notice';
                noticeEl.setAttribute('data-aurora-role','notice');
                noticeEl.setAttribute('role','status');
                noticeEl.setAttribute('aria-live','polite');
                noticeEl.hidden = true;
                // Coloca após a hint
                const hint = toolbar.querySelector('.aurora-session__hint');
                if (hint && hint.nextSibling) toolbar.insertBefore(noticeEl, hint.nextSibling);
                else toolbar.appendChild(noticeEl);
            } else {
                const composer = container.querySelector('[data-aurora-role="composer"]');
                if (!composer) return null;
                noticeEl = document.createElement('div');
                noticeEl.className = 'aurora-bubble__notice';
                noticeEl.setAttribute('data-aurora-role','notice');
                noticeEl.setAttribute('role','status');
                noticeEl.setAttribute('aria-live','polite');
                noticeEl.hidden = true;
                // Inserir após o input
                const inp = composer.querySelector('.aurora-bubble__input');
                if (inp && inp.nextSibling) composer.insertBefore(noticeEl, inp.nextSibling);
                else composer.appendChild(noticeEl);
            }
            return noticeEl;
        };
        const showNotice = (msg, type = 'error') => {
            const n = ensureNotice(); if (!n) return;
            n.textContent = msg;
            n.hidden = false;
            n.classList.remove('is-info','is-error');
            n.classList.add(type === 'error' ? 'is-error' : 'is-info');
            // auto-hide depois de alguns segundos
            clearTimeout(showNotice._to);
            showNotice._to = setTimeout(() => { try { n.hidden = true; } catch(e){} }, 4000);
        };
        const hideNotice = () => { if (noticeEl) noticeEl.hidden = true; };

        if (maxChars > 0) {
            try { input.setAttribute('maxlength', String(maxChars)); } catch(e) {}
            const update = () => {
                const used = (input.value || '').length;
                const remaining = Math.max(0, maxChars - used);
                if (limitEl) { limitEl.textContent = `${remaining}/${maxChars}`; limitEl.hidden = false; }
                if (used <= maxChars) hideNotice();
            };
            input.addEventListener('input', update);
            update();
        } else if (limitEl) {
            limitEl.hidden = true;
        }

        // Atualizar textos de boas-vindas e placeholders a partir das i18n, se disponíveis
        try {
            const i18n = (typeof AuroraChatConfig !== 'undefined') ? AuroraChatConfig.i18n : null;
            if (i18n) {
                if (layout === 'bubble') {
                    const t = container.querySelector('.aurora-bubble__welcome-title');
                    const s = container.querySelector('.aurora-bubble__welcome-subtitle');
                    if (t && i18n.welcomeTitle) t.textContent = i18n.welcomeTitle;
                    if (s && i18n.welcomeSubtitle) s.textContent = i18n.welcomeSubtitle;
                } else {
                    const firstBot = container.querySelector('.aurora-session__message.is-bot .aurora-session__bubble p');
                    if (firstBot && i18n.welcomeBot) firstBot.textContent = i18n.welcomeBot;
                }
            }
        } catch(e) { /* no-op */ }

    let interactions = 0;
        let isSending = false;
        let sessionId = generateSession();
        const maxTurns = parseInt(container.dataset.maxTurns || '0', 10);
        const sendForm = parseInt(container.dataset.sendForm || '0', 10) === 1;
        const agentId = parseInt(container.dataset.agent || '0', 10);
    // Pré-chat form state
    let prechatCollected = false;
    let prechatData = null;

        // handoff removido

        const removeWelcome = () => {
            if (welcome && welcome.parentNode) {
                // anima remoção rápida
                welcome.style.transition = 'opacity .18s ease, transform .18s ease';
                welcome.style.opacity = '0';
                welcome.style.transform = 'translateY(-4px)';
                setTimeout(() => { if (welcome.parentNode) welcome.remove(); }, 190);
            }
        };

        const ensureStarted = () => {
            if (layout === 'bubble' && !container.classList.contains('aurora-chat-has-started')) {
                container.classList.add('aurora-chat-has-started');
                removeWelcome();
                if (composer) composer.hidden = false;
            }
        };

        if (startButton) {
            startButton.addEventListener('click', () => {
                ensureStarted();
                input.focus();
            });
        }

        // No layout sessão: Enter = enviar; Ctrl/Cmd+Enter = nova linha
        if (layout === 'session' && input.tagName === 'TEXTAREA') {
            const doSubmit = () => {
                if (isSending) return;
                if (typeof composer.requestSubmit === 'function') composer.requestSubmit();
                else composer.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            };
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    if (e.ctrlKey || e.metaKey) {
                        // permitir quebra de linha
                        return;
                    }
                    // enviar com Enter
                    e.preventDefault();
                    if (input.value.trim()) doSubmit();
                }
            });
        }

        composer.addEventListener('submit', async (event) => {
            event.preventDefault();
            const value = input.value.trim();

            if (!value || isSending || isRecording || isTranscribing) {
                return;
            }

            if (maxChars > 0 && value.length > maxChars) {
                const msg = (AuroraChatConfig.i18n && AuroraChatConfig.i18n.charsLimit)
                    ? (AuroraChatConfig.i18n.charsLimit.replace('%d', String(maxChars)))
                    : `Sua mensagem excede o limite de ${maxChars} caracteres.`;
                AuroraToast.show(msg, 'error', 'Mensagem muito longa', container);
                return;
            }

            if (maxTurns > 0 && interactions >= maxTurns) {
                alert(AuroraChatConfig.i18n.limitReached);
                return;
            }

            ensureStarted();

            interactions += 1;
            isSending = true;
            container.classList.add('aurora-chat-is-loading');
            setStatus('responding');
            // Desabilita controles somente se form não for necessário (ou após envio do form)
            const disableControls = () => {
                try {
                    input.disabled = true;
                    const sendBtn = container.querySelector('[data-aurora-role="send"]');
                    if (sendBtn) sendBtn.disabled = true;
                } catch(e) {}
            };

            const startTime = typeof performance !== 'undefined' ? performance.now() : Date.now();

            const userMessage = createMessage(layout, 'is-user', value.replace(/\n/g, '<br>'));
            messages.appendChild(userMessage);
            if (layout === 'bubble') removeWelcome();
            scrollToBottom(messages);
            input.value = '';
            input.focus();

            // Se for necessário coletar formulário pré-atendimento e ainda não foi coletado, mostrar formulário e aguardar
            if (sendForm && !prechatCollected) {
                const formWrapper = document.createElement('div');
                formWrapper.className = 'aurora-prechat-wrapper';
                formWrapper.innerHTML = `
                    <form class="aurora-prechat-form">
                        <div class="aurora-prechat-title">Antes de começarmos, preencha seus dados</div>
                        <div class="aurora-prechat-row">
                            <label>Nome</label>
                            <input type="text" name="name" placeholder="Seu nome" required />
                        </div>
                        <div class="aurora-prechat-row">
                            <label>E-mail</label>
                            <input type="email" name="email" placeholder="voce@exemplo.com" required />
                        </div>
                        <div class="aurora-prechat-row">
                            <label>Contato</label>
                            <input type="text" name="contact" placeholder="WhatsApp/Telefone" required />
                        </div>
                        <div class="aurora-prechat-actions">
                            <button type="submit" class="aurora-prechat-submit">Enviar</button>
                            <button type="button" class="aurora-prechat-cancel">Cancelar</button>
                        </div>
                    </form>
                `;
                messages.appendChild(formWrapper);
                scrollToBottom(messages);

                const formEl = formWrapper.querySelector('form');
                const cancelBtn = formWrapper.querySelector('.aurora-prechat-cancel');

                const validate = (data) => {
                    const errs = [];
                    if (!data.name || data.name.length < 2) errs.push('Informe um nome válido.');
                    if (!data.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) errs.push('Informe um e-mail válido.');
                    if (!data.contact || data.contact.length < 5) errs.push('Informe um contato válido.');
                    return errs;
                };

                const showErrors = (errs) => {
                    let box = formWrapper.querySelector('.aurora-prechat-errors');
                    if (!box) {
                        box = document.createElement('div');
                        box.className = 'aurora-prechat-errors';
                        formEl.prepend(box);
                    }
                    box.innerHTML = errs.map(e => `<div class="err">${e}</div>`).join('');
                };

                const proceedSend = async (meta) => {
                    disableControls();
                    // adiciona indicador de digitação e envia
                    const typingIndicator = createMessage(layout, 'is-bot is-typing', '<span class="aurora-chat-typing"><span></span><span></span><span></span></span>');
                    messages.appendChild(typingIndicator);
                    scrollToBottom(messages);
                    try {
                        const { reply, session, attachments, audio } = await sendRequest(agentId, value, sessionId, meta);
                        sessionId = session || sessionId;
                        typingIndicator.remove();
                        const _textReply = (typeof reply === 'string') ? reply.trim() : '';
                        if (_textReply && _textReply !== '*') {
                            const botMessage = createMessage(layout, 'is-bot', mdToHtml(_textReply));
                            messages.appendChild(botMessage);
                            renderInlinePreviews(botMessage);
                        }
                        if (attachments && attachments.length) {
                            renderAttachments(attachments, messages);
                        }
                        // Audio player (URL or base64)
                        try {
                            if (audio && typeof audio === 'object') {
                                let src = '';
                                if (audio.kind === 'url' && audio.src) {
                                    src = audio.src;
                                } else if (audio.kind === 'base64' && audio.base64) {
                                    const t = audio.type || 'audio/mpeg';
                                    src = `data:${t};base64,${audio.base64}`;
                                }
                                if (src) {
                                    const audioMsg = createAudioMessage(layout, src, audio.type || '');
                                    messages.appendChild(audioMsg);
                                }
                            }
                        } catch(e) { /* ignore */ }
                        if (typeof performance !== 'undefined') {
                            const elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                            setStatus('complete', elapsed);
                        } else {
                            setStatus('complete', '1.0');
                        }
                    } catch (error) {
                        console.error('[Aurora Chat]', error);
                        if (AuroraChatConfig.i18n.errorDefault) {
                            const fallbackMessage = createMessage(layout, 'is-bot', AuroraChatConfig.i18n.errorDefault);
                            messages.appendChild(fallbackMessage);
                        }
                        setStatus('idle');
                    } finally {
                        scrollToBottom(messages);
                        isSending = false;
                        container.classList.remove('aurora-chat-is-loading');
                        // Reabilitar controles após envio via pré-form
                        try {
                            input.disabled = false;
                            const sendBtn = container.querySelector('[data-aurora-role="send"]');
                            if (sendBtn) sendBtn.disabled = false;
                            input.focus();
                        } catch(e) {}
                    }
                };

                formEl.addEventListener('submit', async (ev) => {
                    ev.preventDefault();
                    const data = {
                        name: formEl.name.value.trim(),
                        email: formEl.email.value.trim(),
                        contact: formEl.contact.value.trim(),
                    };
                    const errs = validate(data);
                    if (errs.length) return showErrors(errs);
                    prechatCollected = true;
                    prechatData = data;
                    formWrapper.remove();
                    await proceedSend(prechatData);
                });

                cancelBtn.addEventListener('click', () => {
                    // cancelar envio; reverter estado básico
                    formWrapper.remove();
                    isSending = false;
                    interactions = Math.max(0, interactions - 1);
                    container.classList.remove('aurora-chat-is-loading');
                    setStatus('idle');
                    try {
                        input.disabled = false;
                        const sendBtn = container.querySelector('[data-aurora-role="send"]');
                        if (sendBtn) sendBtn.disabled = false;
                        input.focus();
                    } catch(e) {}
                });

                return; // não continuar o fluxo padrão até o formulário ser resolvido
            }

            disableControls();
            const typingIndicator = createMessage(layout, 'is-bot is-typing', '<span class="aurora-chat-typing"><span></span><span></span><span></span></span>');
            messages.appendChild(typingIndicator);
            scrollToBottom(messages);

            try {
                const { reply, session, attachments, audio } = await sendRequest(agentId, value, sessionId, prechatData);
                sessionId = session || sessionId;
                typingIndicator.remove();

                const _textReply = (typeof reply === 'string') ? reply.trim() : '';
                if (_textReply && _textReply !== '*') {
                    const botMessage = createMessage(layout, 'is-bot', mdToHtml(_textReply));
                    messages.appendChild(botMessage);
                    // Pré-visualizar URLs inline dentro do conteúdo do bot
                    renderInlinePreviews(botMessage);
                }
                if (attachments && attachments.length) {
                    renderAttachments(attachments, messages);
                }
                // Audio player (URL or base64)
                try {
                    if (audio && typeof audio === 'object') {
                        let src = '';
                        if (audio.kind === 'url' && audio.src) {
                            src = audio.src;
                        } else if (audio.kind === 'base64' && audio.base64) {
                            const t = audio.type || 'audio/mpeg';
                            src = `data:${t};base64,${audio.base64}`;
                        }
                        if (src) {
                            const audioMsg = createAudioMessage(layout, src, audio.type || '');
                            messages.appendChild(audioMsg);
                        }
                    }
                } catch(e) { /* ignore */ }

                // anexos (urls) opcionais
                try {
                    const last = arguments.callee.lastResponse || null; // not reliable, keep simple: we will use extra fetch return soon
                } catch(e) {}

                if (typeof performance !== 'undefined') {
                    const elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    setStatus('complete', elapsed);
                } else {
                    setStatus('complete', '1.0');
                }
            } catch (error) {
                console.error('[Aurora Chat]', error);
                typingIndicator.remove();
                if (AuroraChatConfig.i18n.errorDefault) {
                    const fallbackMessage = createMessage(layout, 'is-bot', AuroraChatConfig.i18n.errorDefault);
                    messages.appendChild(fallbackMessage);
                }
                setStatus('idle');
            } finally {
                scrollToBottom(messages);
                isSending = false;
                container.classList.remove('aurora-chat-is-loading');
                // Reabilitar controles
                try {
                    input.disabled = false;
                    const sendBtn = container.querySelector('[data-aurora-role="send"]');
                    if (sendBtn) sendBtn.disabled = false;
                    input.focus();
                } catch(e) {}
            }
        });

        if (layout === 'bubble') {
            const bubble = container.querySelector('[data-aurora-role="bubble"]');
            if (!bubble) {
                return;
            }

            // Isola o widget de bolha do DOM do construtor de páginas (ex: Elementor) para evitar overflow/clipping.
            // Move o container de bolha para o body na primeira inicialização.
            if (!bubble.__auroraMountedToBody) {
                bubble.__auroraMountedToBody = true;
                const holder = document.createElement('div');
                holder.className = 'aurora-chat-container aurora-chat-layout-bubble';
                // Preserva atributos de dados relevantes
                holder.dataset.agent = container.dataset.agent || '0';
                holder.dataset.maxTurns = container.dataset.maxTurns || '0';
                holder.dataset.sendForm = container.dataset.sendForm || '0';
                holder.dataset.maxChars = container.dataset.maxChars || '0';
                holder.dataset.agentName = container.dataset.agentName || '';
                // Move o bubble para o novo holder e monta no body
                holder.appendChild(bubble);
                document.body.appendChild(holder);
                // Reatribui ponteiros locais para o novo escopo
                container = holder;
            }

            const toggle = bubble.querySelector('.aurora-bubble__launcher');
            const panel = bubble.querySelector('.aurora-bubble__panel');
            const close = bubble.querySelector('.aurora-bubble__close');

            // Garante rodapé com "Powered by Aurora Chat"
            try {
                let footer = bubble.querySelector('.aurora-bubble__footer');
                if (!footer) {
                    footer = document.createElement('div');
                    footer.className = 'aurora-bubble__footer';
                    footer.setAttribute('data-aurora-role','footer');
                    const toastC = bubble.querySelector('[data-aurora-role="toast-container"]');
                    if (toastC && toastC.parentNode) toastC.parentNode.insertBefore(footer, toastC);
                    else bubble.appendChild(footer);
                }
                if (!footer.innerHTML || !/Powered by/i.test(footer.textContent)) {
                    footer.innerHTML = '<small>Feito por Aurora Tecnologia e Inovação</small>';
                }
            } catch(_) {}

            const openPanel = () => {
                if (!panel.hidden) return;
                panel.hidden = false;
                panel.classList.remove('is-closing');
                panel.classList.add('is-opening');
                requestAnimationFrame(() => {
                    panel.classList.add('is-visible');
                });
                if (toggle) toggle.setAttribute('aria-expanded', 'true');
                if (startButton) startButton.focus(); else input.focus();
                document.addEventListener('mousedown', outsideHandler);
                document.addEventListener('keydown', escHandler);
                if (overlay) overlay.hidden = false;
                document.documentElement.classList.add('aurora-chat-lock-scroll');
            };

            const closePanel = () => {
                if (panel.hidden) return;
                panel.classList.remove('is-opening');
                panel.classList.add('is-closing');
                panel.classList.remove('is-visible');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
                const end = () => {
                    panel.hidden = true;
                    panel.removeEventListener('animationend', end);
                };
                panel.addEventListener('animationend', end);
                document.removeEventListener('mousedown', outsideHandler);
                document.removeEventListener('keydown', escHandler);
                if (overlay) overlay.hidden = true;
                document.documentElement.classList.remove('aurora-chat-lock-scroll');
            };

            const outsideHandler = (e) => {
                if (panel.hidden) return;
                if (overlay && e.target === overlay) { closePanel(); return; }
                if (!panel.contains(e.target) && !toggle.contains(e.target)) closePanel();
            };
            const escHandler = (e) => { if (e.key === 'Escape') closePanel(); };

            if (toggle) {
                toggle.addEventListener('click', () => {
                    const expanded = toggle.getAttribute('aria-expanded') === 'true';
                    expanded ? closePanel() : openPanel();
                });
            }

            if (close) close.addEventListener('click', closePanel);
            if (overlay) overlay.addEventListener('click', closePanel);
        }

        // Abrir links dentro do histórico no popup (mensagens do bot e anexos)
        messages.addEventListener('click', (e) => {
            const a = e.target.closest('a');
            if (!a) return;
            const href = a.getAttribute('href') || '';
            if (!/^https?:\/\//i.test(href)) return; // só http(s)
            // Respeitar ctrl/cmd/shift para nova aba/janela
            if (e.ctrlKey || e.metaKey || e.shiftKey || a.getAttribute('download') !== null) return;
            // Impedir navegação padrão e garantir prioridade
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            // Redireciono direto para domínios que negam iframe (WhatsApp, YouTube, etc.)
            try {
                const u = new URL(href);
                const host = u.hostname.toLowerCase();
                const denyHosts = ['whatsapp.com','youtube.com','youtu.be','facebook.com','fb.com','instagram.com','t.me','telegram.me', 'wa.me', 'twitter.com', 'linkedin.com'];
                const mustNewTab = denyHosts.some(d => host === d || host.endsWith('.' + d));
                if (mustNewTab) {
                    window.open(href, '_blank', 'noopener');
                    return;
                }
            } catch(_) {}
            const isImg = isImageURL(href);
            AuroraModal.openURL(href, a.textContent || href, isImg);
        }, true);
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.aurora-chat-container').forEach((container) => {
            initChat(container);
        });
    });
})();
