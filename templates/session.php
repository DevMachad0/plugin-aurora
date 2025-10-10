<section class="aurora-session" aria-label="Assistente virtual Aurora">
    <div class="aurora-session__surface">
        <header class="aurora-session__header">
            <div class="aurora-session__agent">
                <span class="aurora-session__avatar" aria-hidden="true">🌌</span>
                <div class="aurora-session__meta">
                    <h2 class="aurora-session__title">Assistente Aurora</h2>
                    <p class="aurora-session__subtitle">Sempre por aqui para acelerar suas respostas.</p>
                </div>
            </div>
            <div class="aurora-session__status" data-aurora-role="status">Online</div>
        </header>

        <div class="aurora-session__messages" data-aurora-role="messages" tabindex="0">
            <article class="aurora-session__message is-bot">
                <div class="aurora-session__bubble">
                    <p>Olá! Sou o Aurora, seu copiloto digital. Como posso te ajudar hoje?</p>
                </div>
                <span class="aurora-session__timestamp" data-aurora-role="timestamp"></span>
            </article>
        </div>

        <form class="aurora-session__composer" data-aurora-role="composer">
            <label class="screen-reader-text" for="aurora-session-input">Mensagem</label>
            <textarea id="aurora-session-input" class="aurora-session__input" rows="3" placeholder="Escreva uma mensagem... (Enter = nova linha, Ctrl/Cmd+Enter = enviar)" required></textarea>
            <div class="aurora-session__toolbar">
                <span class="aurora-session__hint">Enter = enviar · Ctrl/Cmd+Enter = nova linha</span>
                <button type="submit" class="aurora-session__send" data-aurora-role="send">
                    <span class="aurora-session__send-icon" aria-hidden="true">➤</span>
                    <span class="aurora-session__send-text">Enviar</span>
                </button>
            </div>
        </form>

        <footer class="aurora-session__footer" data-aurora-role="footer">
            <small>Powered by Aurora Chat</small>
        </footer>
    </div>
</section>
