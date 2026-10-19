@extends('layouts.site')
@section('title', 'Privacy — Caldren')
@section('content')
<section class="policy-hero" aria-labelledby="privacy-title">
    <div class="policy-hero-grid" aria-hidden="true"></div>
    <div class="policy-hero-glow" aria-hidden="true"></div>
    <div class="container policy-hero-layout">
        <div class="policy-hero-copy">
            <span class="obs-kicker"><span class="obs-live-dot" aria-hidden="true"></span> PRIVACY AT CALDREN</span>
            <h1 id="privacy-title">Know what happens to your data.</h1>
            <p>Building energy decisions need context. Here is what Caldren stores, what is shared for a forecast, and how to reach us about your information.</p>
            <span class="policy-updated">Last updated October 16, 2026</span>
        </div>
        <div class="policy-hero-panel observatory-glass">
            <span class="policy-panel-label">AT A GLANCE</span>
            <h2>Three useful things to know</h2>
            <div class="policy-panel-item"><i class="fa-solid fa-building-shield" aria-hidden="true"></i><span>Workspace data is associated with your organization.</span></div>
            <div class="policy-panel-item"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Forecasts send summarized usage and tariff settings to the OpenAI API.</span></div>
            <div class="policy-panel-item"><i class="fa-solid fa-credit-card" aria-hidden="true"></i><span>Demo checkout does not store card details or charge money.</span></div>
        </div>
    </div>
</section>

<section class="container policy-main" aria-label="Privacy details">
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
        <div class="policy-intro"><span class="eyebrow">THE DETAILS</span><h2>A clear view of your information.</h2><p>This page describes how the current Caldren service uses information you provide.</p></div>
        <article class="policy-card">
            <section id="privacy-collect" class="policy-section">
                <span class="policy-section-number">01 / INFORMATION</span>
                <h2>Information we store</h2>
                <p>To operate your workspace, Caldren stores account names and email addresses, organization membership, building details, interval energy readings you upload, monthly bills you enter, and generated forecasts and recommendations. Building details can include location, time zone, and tariff settings.</p>
                <p>Please do not include personal or sensitive information in building names, CSV uploads, or messages to us. A short description of your energy question is enough for a first contact.</p>
            </section>
            <section id="privacy-access" class="policy-section">
                <span class="policy-section-number">02 / WORKSPACES</span>
                <h2>Workspace access</h2>
                <p>People invited to an organization can view its workspace information. Building and forecast views are scoped to the selected organization. Workspace owners can invite team members, so review invitations before sharing access.</p>
            </section>
            <section id="privacy-forecast" class="policy-section">
                <span class="policy-section-number">03 / FORECASTS</span>
                <h2>Forecast processing</h2>
                <p>When an authorized team member requests a forecast, Caldren sends the OpenAI Responses API daily summaries from recent interval readings, a historical baseline, the building time zone, and tariff settings such as rates and peak hours. The request does not need raw CSV rows to generate the seven-day estimate.</p>
                <p>Caldren checks returned values against the input history before displaying them. Forecasts and opportunities are estimates and should be reviewed alongside actual conditions.</p>
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
        <div class="policy-next obs-light-glass"><i class="fa-solid fa-file-contract" aria-hidden="true"></i><div><strong>Using Caldren?</strong><p>Read the service terms for trial, forecast, and demo checkout details.</p></div><a href="{{ route('terms') }}">Read Terms</a></div>
    </div>
</section>
@endsection
