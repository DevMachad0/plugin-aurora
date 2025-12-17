<?php
// Template de Sessão (Aurora)
// Observação: Mantemos as mesmas classes e data-attributes para compatibilidade com o JS.
// Todo o CSS específico da sessão foi movido para este arquivo para facilitar customizações por template.
?>

<section class="aurora-session" aria-label="Assistente virtual Aurora">
    <style>
        /* ====================== Session Template - Stylescope ====================== */
        /* O template assume que as CSS vars (ex.: --aurora-color-*) já existem no tema global */

        .aurora-session {
            display: grid;
            gap: var(--aurora-space-4, 16px);
            background: var(--aurora-color-bg, #fff);
            border: 1px solid var(--aurora-color-border, #e5e7eb);
            border-radius: var(--aurora-radius-xl, 16px);
            box-shadow: var(--aurora-shadow-md, 0 4px 16px rgba(0, 0, 0, .06));
            overflow: hidden;
        }

        .aurora-session__surface {
            display: grid;
            grid-template-rows: auto 1fr auto auto;
            min-height: min(72vh, 820px);
        }

        /* Header sticky com presença */
        .aurora-session__header {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--aurora-space-3, 12px);
            padding: clamp(14px, 2.5vw, 18px) clamp(14px, 3vw, 20px);
            background:
                linear-gradient(180deg, var(--aurora-color-soft-bg, #f8fafc), transparent 85%),
                var(--aurora-color-bg, #fff);
            border-bottom: 1px solid var(--aurora-color-border, #e5e7eb);
            backdrop-filter: saturate(1.1) blur(2px);
        }

        .aurora-session__right {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .aurora-theme-toggle-btn {
            appearance: none;
            border: 1px solid var(--aurora-color-border, #e5e7eb);
            background: #fff;
            color: inherit;
            border-radius: 8px;
            width: 28px;
            height: 28px;
            display: inline-grid;
            place-items: center;
            font-size: 14px;
            cursor: pointer;
        }

        .aurora-theme-toggle-btn:hover {
            filter: brightness(0.98);
        }

        .aurora-session__agent {
            display: flex;
            align-items: center;
            gap: var(--aurora-space-3, 12px);
            min-width: 0;
        }

        .aurora-session__avatar {
            width: 44px;
            height: 44px;
            border-radius: var(--aurora-radius-md, 12px);
            background: var(--aurora-color-primary-gradient, linear-gradient(135deg, #6d28d9, #2563eb));
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: .95rem;
            box-shadow: var(--aurora-shadow-sm, 0 2px 8px rgba(0, 0, 0, .08));
        }

        .aurora-session__meta {
            display: grid;
            gap: 2px;
            min-width: 0;
            justify-items: start;
            justify-items: start;
        }

        .aurora-session__title {
            margin: 0;
            font-size: clamp(1rem, 1.2vw, 1.1rem);
            font-weight: 700;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .aurora-session__subtitle {
            margin: 0;
            font-size: .82rem;
            color: var(--aurora-color-muted, #6b7280);
        }

        .aurora-session__status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            font-size: .72rem;
            letter-spacing: .4px;
            font-weight: 700;
            text-transform: uppercase;
            background: var(--aurora-color-soft-alt, #f3f4f6);
            border: 1px solid var(--aurora-color-border, #e5e7eb);
            border-radius: 999px;
            color: var(--aurora-color-text-soft, #6b7280);
        }

        .aurora-session__status::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, .15);
        }

        .aurora-chat-is-loading .aurora-session__status {
            background: var(--aurora-color-primary, #2563eb);
            color: #fff;
            border-color: var(--aurora-color-primary, #2563eb);
        }

        .aurora-chat-is-loading .aurora-session__status::before {
            background: #fff;
            box-shadow: none;
        }

        /* Thread de mensagens */
        .aurora-session__messages {
            display: flex;
            flex-direction: column;
            gap: clamp(10px, 1.4vw, 14px);
            padding: clamp(14px, 2.6vw, 22px) clamp(14px, 3vw, 24px) clamp(6px, 1.6vw, 12px);
            background: var(--aurora-color-soft-bg, #f8fafc);
            border-bottom: 1px solid var(--aurora-color-border, #e5e7eb);
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .aurora-session__message {
            display: grid;
            gap: 6px;
            max-width: min(78%, 580px);
        }

        .aurora-session__message.is-user {
            margin-left: auto;
            text-align: right;
        }

        .aurora-session__bubble {
            padding: 10px 12px;
            border-radius: 14px;
            background: var(--aurora-chat-bot-bg, #fff);
            color: var(--aurora-chat-bot-text, #0f172a);
            font-size: .95rem;
            line-height: 1.55;
            box-shadow: 0 1px 0 rgba(0, 0, 0, .04);
        }

        .aurora-session__message.is-user .aurora-session__bubble {
            background: var(--aurora-chat-user-bg, linear-gradient(135deg, #2563eb, #7c3aed));
            color: var(--aurora-chat-user-text, #fff);
            box-shadow: 0 2px 12px rgba(124, 58, 237, .25);
        }

        .aurora-session__timestamp {
            font-size: .68rem;
            letter-spacing: .3px;
            color: var(--aurora-color-muted, #6b7280);
        }

        /* Composer ancorado com pílula */
        .aurora-session__composer {
            position: sticky;
            bottom: 0;
            z-index: 2;
            background: var(--aurora-color-bg, #fff);
            padding: clamp(8px, 2vw, 12px) clamp(12px, 3vw, 20px);
            display: grid;
            gap: 8px;
        }

        .aurora-input-group {
            display: flex;
            align-items: center;
            width: 100%;
            border: 2px solid var(--aurora-color-border-strong, #e5e7eb);
            background: #fff;
            border-radius: 16px;
            padding: 8px 8px 8px 12px;
        }

        .aurora-session__input {
            width: 100%;
            border: 0;
            background: transparent;
            resize: vertical;
            min-height: 46px;
            max-height: 200px;
            padding: 6px 8px;
            line-height: 1.5;
            font-size: .98rem;
        }

        .aurora-session__input:focus {
            outline: none;
            box-shadow: none;
        }

        .aurora-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding-left: 4px;
        }

        .aurora-session__mic,
        .aurora-session__send,
        .aurora-chat-container [data-aurora-role="mic"] {
            appearance: none;
            border: 0;
            width: 40px;
            height: 40px;
            border-radius: 999px;
            display: inline-grid;
            place-items: center;
            cursor: pointer;
            background: #3a3c40;
            color: #fff;
            font-size: 18px;
            line-height: 1;
            transition: background .18s ease, transform .12s ease;
        }

        .aurora-session__mic:hover,
        .aurora-session__send:hover {
            background: linear-gradient(90deg, var(--accent, #4f46e5), var(--accent-2, #06b6d4));
            transform: translateZ(0) scale(1.02);
        }

        .aurora-session__mic:focus-visible,
        .aurora-session__send:focus-visible {
            outline: 2px solid rgba(99, 102, 241, .5);
            outline-offset: 2px;
        }

        .aurora-session__send-icon {
            display: inline-block;
            line-height: 1;
        }

        .aurora-chat-is-loading .aurora-session__send {
            opacity: .55;
            pointer-events: none;
        }

        .aurora-session__toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 0 4px;
        }

        .aurora-session__hint {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--aurora-color-muted, #6b7280);
        }

        .aurora-session__limit {
            font-size: .75rem;
            color: var(--aurora-color-text-soft, #6b7280);
        }

        /* Footer */
        .aurora-session__footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 16px 14px;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--aurora-color-muted, #6b7280);
        }

        /* Toast container (hook do JS) */
        .aurora-toast-container {
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 9999;
            display: grid;
            gap: 8px;
        }

        /* Gravação de áudio (somente sessão) */
        .aurora-session__recording {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: var(--aurora-color-soft-bg, #f8fafc);
            border: 1px solid var(--aurora-color-border, #e5e7eb);
            border-radius: var(--aurora-radius-lg, 12px);
            padding: 10px 12px;
            margin: 8px 12px;
        }

        .aurora-voice-wave {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            flex: 1;
        }

        .aurora-voice-wave span {
            display: block;
            width: 4px;
            height: 14px;
            background: var(--aurora-color-primary, #2563eb);
            border-radius: 2px;
            animation: aurora-wave 1s ease-in-out infinite;
            opacity: .85;
        }

        .aurora-voice-wave span:nth-child(2) {
            animation-delay: .1s
        }

        .aurora-voice-wave span:nth-child(3) {
            animation-delay: .2s
        }

        .aurora-voice-wave span:nth-child(4) {
            animation-delay: .3s
        }

        .aurora-voice-wave span:nth-child(5) {
            animation-delay: .4s
        }

        .aurora-voice-wave span:nth-child(6) {
            animation-delay: .5s
        }

        .aurora-voice-wave span:nth-child(7) {
            animation-delay: .6s
        }

        .aurora-voice-wave span:nth-child(8) {
            animation-delay: .7s
        }

        @keyframes aurora-wave {

            0%,
            100% {
                transform: scaleY(.4)
            }

            50% {
                transform: scaleY(1.2)
            }
        }

        .aurora-rec-timer {
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            color: var(--aurora-color-text-soft, #6b7280);
        }

        .aurora-rec-actions {
            display: inline-flex;
            gap: 8px;
        }

        .aurora-rec-btn {
            appearance: none;
            border: 1px solid var(--aurora-color-border, #e5e7eb);
            background: #fff;
            color: var(--aurora-color-text, #111827);
            border-radius: 999px;
            padding: 6px 10px;
            font-size: .8rem;
            box-shadow: var(--aurora-shadow-sm, 0 2px 8px rgba(0, 0, 0, .08));
        }

        .aurora-rec-cancel {
            color: var(--aurora-color-danger, #dc2626);
            border-color: rgba(220, 38, 38, .35);
        }

        /* Player de áudio de resposta */
        .aurora-session__bubble audio.aurora-audio-player {
            width: 100%;
            max-width: 520px;
            display: block;
            margin-top: .25rem;
            background: var(--aurora-color-soft-alt, #f1f5f9);
            border-radius: var(--aurora-radius-sm, 4px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, .08);
            accent-color: var(--aurora-color-primary, #2563eb);
        }

        @media (max-width: 600px) {
            .aurora-session__bubble audio.aurora-audio-player {
                max-width: 100%;
            }
        }

        /* Responsividade */
        @media (min-width: 768px) {
            .aurora-session {
                gap: var(--aurora-space-5, 20px);
            }

            .aurora-session__messages {
                min-height: 360px;
                max-height: 560px;
                border-radius: var(--aurora-radius-lg, 12px);
                margin: 8px 12px;
            }

            .aurora-session__message {
                max-width: min(70%, 680px);
            }
        }

        @media (max-width: 480px) {
            .aurora-session__avatar {
                width: 38px;
                height: 38px;
            }

            .aurora-session__status {
                display: none;
            }

            .aurora-session__composer {
                padding-left: 10px;
                padding-right: 10px;
            }
        }
    </style>

    <div class="aurora-session__surface">
        <header class="aurora-session__header">
            <div class="aurora-session__agent">
                <span class="aurora-session__avatar" aria-hidden="true">A</span>
                <div class="aurora-session__meta">
                    <h2 class="aurora-session__title">Assistente Aurora</h2>
                    <p class="aurora-session__subtitle">Sempre por aqui para acelerar suas respostas.</p>
                </div>
            </div>
            <div class="aurora-session__right">
                <div class="aurora-session__status" data-aurora-role="status" aria-live="polite">Online</div>
                <button type="button" class="aurora-theme-toggle-btn" data-aurora-role="theme-toggle"
                    aria-label="Alternar tema" title="Alternar tema">🌓</button>
            </div>
        </header>

        <div class="aurora-session__messages" data-aurora-role="messages" tabindex="0" role="log" aria-live="polite"
            aria-relevant="additions">
            <article class="aurora-session__message is-bot">
                <div class="aurora-session__bubble">
                    <p>Olá! Sou o Aurora, seu copiloto digital. Como posso te ajudar hoje?</p>
                </div>
                <span class="aurora-session__timestamp" data-aurora-role="timestamp"></span>
            </article>
        </div>

        <form class="aurora-session__composer" data-aurora-role="composer">
            <label class="screen-reader-text" for="aurora-session-input">Mensagem</label>
            <div class="aurora-input-group">
                <input id="aurora-session-input" class="aurora-session__input" rows="3"
                    placeholder="Escreva uma mensagem... (Enter = nova linha, Ctrl/Cmd+Enter = enviar)"
                    required></input>
                <div class="aurora-actions">
                    <button type="button" class="aurora-session__mic" data-aurora-role="mic"
                        aria-label="Gravar mensagem de voz" title="Gravar mensagem de voz">
                        <span aria-hidden="true">🎤</span>
                    </button>
                    <button type="submit" class="aurora-session__send" data-aurora-role="send"
                        aria-label="Enviar mensagem">
                        <span class="aurora-session__send-icon" aria-hidden="true">➤</span>
                    </button>
                </div>
            </div>
            <div class="aurora-session__toolbar">
                <span class="aurora-session__limit" data-aurora-role="char-limit" aria-live="polite" hidden></span>
            </div>
        </form>

        <footer class="aurora-session__footer" data-aurora-role="footer">
            <small>Feito por Aurora Tecnologia e Inovação</small>
            <div></div>
        </footer>
        <div class="aurora-toast-container" data-aurora-role="toast-container"></div>
    </div>
</section>