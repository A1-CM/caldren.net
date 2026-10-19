<details id="privacy" class="story-policy" data-policy>
<summary>Privacy <span>Read the full details</span></summary>
<section class="policy-main" aria-label="Privacy details">
    <nav class="policy-index" aria-label="On this page">
        <span class="policy-index-label">ON THIS PAGE</span>
        <a href="#privacy-collect">Information we store</a>
        <a href="#privacy-access">Workspace access</a>
        <a href="#privacy-forecast">Forecast processing</a>
        <a href="#privacy-contact">Contact inquiries</a>
        <a href="#privacy-payments">Demo payments</a>
        <a href="#privacy-requests">Questions and requests</a>
    </nav>
    <div class="policy-content">
        <div class="policy-intro"><span class="eyebrow">THE DETAILS</span><h2>How energy information is used.</h2><p>This notice describes how the current Caldren energy forecasting and optimization service uses information you provide.</p></div>
        <article class="policy-card">
            <section id="privacy-collect" class="policy-section">
                <span class="policy-section-number">01 / INFORMATION</span>
                <h2>Information we store</h2>
                <p>To provide the service, Caldren stores account names and email addresses, organization membership, site details, interval energy readings you upload, monthly utility bills you enter, and generated forecasts and recommendations. Site details can include location, time zone, and tariff settings.</p>
                <p>Please do not include personal or sensitive information in building names, CSV uploads, or messages to us. A short description of your energy question is enough for a first contact.</p>
            </section>
            <section id="privacy-access" class="policy-section">
                <span class="policy-section-number">02 / WORKSPACES</span>
                <h2>Workspace access</h2>
                <p>People invited to an organization can view its energy workspace information. Site and forecast views are scoped to the selected organization. Workspace owners can invite team members, so review invitations before sharing access.</p>
            </section>
            <section id="privacy-forecast" class="policy-section">
                <span class="policy-section-number">03 / FORECASTS</span>
                <h2>Forecast processing</h2>
                <p>When an authorized team member requests a forecast, Caldren sends the OpenAI Responses API daily summaries from recent interval readings, a historical baseline, the site time zone, and tariff settings such as rates and peak hours. The request does not need raw CSV rows to generate the seven-day estimate.</p>
                <p>Caldren checks returned values against the input history before displaying them. Forecasts and optimization opportunities are estimates for your team to review alongside actual conditions.</p>
            </section>
            <section id="privacy-contact" class="policy-section">
                <span class="policy-section-number">04 / INQUIRIES</span>
                <h2>Contact inquiries</h2>
                <p>If you use the contact form, Caldren saves your name, email address, optional company name, and message. It also attempts to email the inquiry to <a href="mailto:{{ config('mail.contact_to') }}">{{ config('mail.contact_to') }}</a> so the team can respond. If email delivery fails, the inquiry remains saved in Caldren’s database.</p>
            </section>
            <section id="privacy-payments" class="policy-section">
                <span class="policy-section-number">05 / DEMO BILLING</span>
                <h2>Demo payments</h2>
                <p>The current checkout is a local simulation. Caldren records the simulated payment and access period, but it does not take a real payment or store payment card details.</p>
            </section>
            <section id="privacy-requests" class="policy-section">
                <span class="policy-section-number">06 / GET IN TOUCH</span>
                <h2>Questions and requests</h2>
                <p>Email <a href="mailto:{{ config('mail.contact_to') }}">{{ config('mail.contact_to') }}</a> with questions about your information or to request deletion of your account and associated workspace data. Include enough information for us to identify the workspace; please do not email private meter data as part of the request.</p>
            </section>
        </article>
        <div class="policy-next obs-light-glass"><i class="fa-solid fa-file-contract" aria-hidden="true"></i><div><strong>Using Caldren?</strong><p>Read the service terms for trial, forecast, and demo checkout details.</p></div><a href="{{ route('home') }}#terms">Read Terms</a></div>
    </div>
</section>
</details>
