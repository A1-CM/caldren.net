<div>
    <!-- Knowing is not enough; we must apply. Being willing is not enough; we must do. - Leonardo da Vinci -->
</div>
<div class="site-assistant" data-site-assistant data-endpoint="{{ route('assistant.chat') }}">
    <section class="site-assistant-panel" id="site-assistant-panel" aria-label="Caldren AI assistant" hidden>
        <header class="site-assistant-header">
            <div><span class="site-assistant-kicker"><i class="fa-solid fa-wave-square" aria-hidden="true"></i> CALDREN PULSE</span><h2>Ask Caldren</h2><p>AI assistant · Product and plan questions</p></div>
            <button class="site-assistant-close" type="button" aria-label="Close chat" data-assistant-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <div class="site-assistant-messages" data-assistant-messages aria-live="polite" aria-relevant="additions text">
            <p class="site-assistant-message assistant-message">Hi, I’m Caldren’s AI assistant. Ask me about Pulse, forecasts, data requirements, or plans.</p>
        </div>
        <p class="site-assistant-note">Answers use public Caldren product information. Messages are sent to Groq to generate replies. Please don’t share private energy or account data.</p>
        <form class="site-assistant-form" data-assistant-form>
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <label class="visually-hidden" for="site-assistant-input">Your question</label>
            <textarea id="site-assistant-input" name="message" rows="2" maxlength="1000" placeholder="Ask about Caldren Pulse…" required data-assistant-input></textarea>
            <button class="site-assistant-send" type="submit" aria-label="Send question" data-assistant-send><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
        </form>
        <p class="site-assistant-status" role="status" data-assistant-status></p>
    </section>
    <button class="site-assistant-toggle" type="button" aria-expanded="false" aria-controls="site-assistant-panel" data-assistant-toggle>
        <i class="fa-regular fa-message" aria-hidden="true"></i><span>Ask Caldren</span>
    </button>
</div>
