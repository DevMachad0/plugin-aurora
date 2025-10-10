<div class="aurora-bubble" data-aurora-role="bubble" aria-live="polite">
    <button class="aurora-bubble__launcher" type="button" aria-controls="aurora-bubble-panel" aria-expanded="false">
        <span class="aurora-bubble__launcher-icon" aria-hidden="true">💬</span>
        <span class="aurora-bubble__launcher-label">Fale com a Aurora</span>
    </button>

    <div class="aurora-bubble__overlay" data-aurora-role="overlay" hidden></div>

    <div class="aurora-bubble__panel" id="aurora-bubble-panel" hidden aria-modal="true" role="dialog" aria-label="Chat Aurora">
        <header class="aurora-bubble__header">
            <div class="aurora-bubble__brand">
                <span class="aurora-bubble__brand-icon" aria-hidden="true">A◦</span>
                <div class="aurora-bubble__brand-copy">
                    <span class="aurora-bubble__brand-name">Aurora Chat</span>
                    <span class="aurora-bubble__brand-status" data-aurora-role="status">Online</span>
                </div>
            </div>
            <button class="aurora-bubble__close" type="button" aria-label="Fechar chat">×</button>
        </header>

        <section class="aurora-bubble__welcome" data-aurora-role="welcome">
            <h2 class="aurora-bubble__welcome-title">Bem-vindo</h2>
            <p class="aurora-bubble__welcome-subtitle">Estamos aqui para ajudar!</p>
            <button class="aurora-bubble__start" type="button" data-aurora-role="start">Envie-nos uma mensagem</button>
        </section>

        <div class="aurora-bubble__thread" data-aurora-role="messages" tabindex="0">
            <article class="aurora-bubble__message is-bot">
                <div class="aurora-bubble__bubble">
                    <p>Olá! Pronto para começar o atendimento? É só me enviar uma mensagem.</p>
                </div>
            </article>
        </div>

    <form class="aurora-bubble__composer" data-aurora-role="composer" hidden>
            <label class="screen-reader-text" for="aurora-bubble-input">Mensagem</label>
            <input id="aurora-bubble-input" class="aurora-bubble__input" type="text" autocomplete="off" placeholder="Digite sua mensagem" required />
            <button type="submit" class="aurora-bubble__send" data-aurora-role="send" aria-label="Enviar mensagem">
                <span aria-hidden="true">➤</span>
            </button>
        </form>

        <div class="aurora-bubble__footer" data-aurora-role="footer"></div>
    </div>
</div>
