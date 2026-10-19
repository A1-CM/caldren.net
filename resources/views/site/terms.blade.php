@extends('layouts.site')
@section('title', 'Terms — Caldren')
@section('content')
<section class="policy-hero" aria-labelledby="terms-title">
    <div class="policy-hero-grid" aria-hidden="true"></div>
    <div class="policy-hero-glow" aria-hidden="true"></div>
    <div class="container policy-hero-layout">
        <div class="policy-hero-copy">
            <span class="obs-kicker"><span class="obs-live-dot" aria-hidden="true"></span> USING CALDREN</span>
            <h1 id="terms-title">Clear expectations for every decision.</h1>
            <p>Caldren brings building energy history and cautious estimates into one workspace. These terms explain how the current service, trial, and demo checkout work.</p>
            <span class="policy-updated">Last updated October 16, 2026</span>
        </div>
        <div class="policy-hero-panel observatory-glass">
            <span class="policy-panel-label">AT A GLANCE</span>
            <h2>How the service works today</h2>
            <div class="policy-panel-item"><i class="fa-solid fa-user-group" aria-hidden="true"></i><span>Your team manages its workspace, buildings, and invitations.</span></div>
            <div class="policy-panel-item"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><span>Forecasts and opportunities are estimates for human review.</span></div>
            <div class="policy-panel-item"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><span>The 30-day trial needs no card; checkout is a demo simulation.</span></div>
        </div>
    </div>
</section>

<section class="container policy-main" aria-label="Terms details">
    <nav class="policy-index" aria-label="On this page">
        <span class="policy-index-label">ON THIS PAGE</span>
        <a href="#terms-service">The service</a>
        <a href="#terms-accounts">Accounts and teams</a>
        <a href="#terms-data">Data you provide</a>
        <a href="#terms-estimates">Estimates and decisions</a>
        <a href="#terms-trial">Trial and access</a>
        <a href="#terms-demo">Demo checkout</a>
        <a href="#terms-availability">Availability</a>
    </nav>
    <div class="policy-content">
        <div class="policy-intro"><span class="eyebrow">THE DETAILS</span><h2>Use the workspace with context.</h2><p>These terms describe the current Caldren experience for commercial building teams.</p></div>
        <article class="policy-card">
            <section id="terms-service" class="policy-section">
                <span class="policy-section-number">01 / SERVICE</span>
                <h2>The service</h2>
                <p>Caldren helps teams organize building energy readings and bills, review historical trends, and request AI-assisted seven-day energy and peak estimates when sufficient interval data is available. It highlights possible peak and tariff opportunities for your team to assess.</p>
            </section>
            <section id="terms-accounts" class="policy-section">
                <span class="policy-section-number">02 / WORKSPACES</span>
                <h2>Accounts and teams</h2>
                <p>You are responsible for keeping your account credentials secure and for the people you invite to your organization. Team members with access to a workspace can see that organization's building information. Workspace owners manage invitations and can use demo checkout.</p>
            </section>
            <section id="terms-data" class="policy-section">
                <span class="policy-section-number">03 / INPUTS</span>
                <h2>Data you provide</h2>
                <p>Upload or enter only information you are authorized to use. Check the accuracy of readings, bills, building details, and tariff settings before relying on an analysis. Keep personal and sensitive information out of building names, CSV files, and contact messages.</p>
            </section>
            <section id="terms-estimates" class="policy-section">
                <span class="policy-section-number">04 / ANALYSIS</span>
                <h2>Estimates and decisions</h2>
                <p>Forecasts, peak alerts, and opportunities are estimates based on the information available to Caldren. They do not guarantee accuracy, savings, or a particular operating result. Review them against actual conditions and use your own judgment before making building decisions. Caldren does not control equipment.</p>
            </section>
            <section id="terms-trial" class="policy-section">
                <span class="policy-section-number">05 / ACCESS</span>
                <h2>Trial and access</h2>
                <p>New organizations receive a 30-day trial without providing a card. After access expires, historical workspace data remains visible, while adding buildings or bills, uploading readings, and generating forecasts are gated until access is active again.</p>
            </section>
            <section id="terms-demo" class="policy-section">
                <span class="policy-section-number">06 / DEMO BILLING</span>
                <h2>Demo checkout</h2>
                <p>Caldren displays one USD 99/month plan, but the current checkout is a clearly labelled simulation available to signed-in workspace owners. It accepts only the displayed 4242 demo number. A successful simulation records a demo payment and adds one month of access; no money is charged, no card details are stored, and access does not renew automatically.</p>
            </section>
            <section id="terms-availability" class="policy-section">
                <span class="policy-section-number">07 / AVAILABILITY</span>
                <h2>Availability and retries</h2>
                <p>Forecast generation depends on an external API and can fail or be unavailable. If it fails, historical data remains available in the workspace and you can try again later. For questions about the service, contact <a href="mailto:{{ config('mail.contact_to') }}">{{ config('mail.contact_to') }}</a>.</p>
            </section>
        </article>
        <div class="policy-next obs-light-glass"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><div><strong>Questions about your data?</strong><p>See what Caldren stores and sends when you request a forecast.</p></div><a href="{{ route('privacy') }}">Read Privacy</a></div>
    </div>
</section>
@endsection
