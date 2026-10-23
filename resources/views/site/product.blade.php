@extends('layouts.site')
@section('title', 'Product — Caldren AI Energy Forecasting & Optimization')
@section('content')
<div class="caldren-product">
    <section class="story-product-hero" aria-labelledby="product-title">
        <img src="{{ asset('images/energy.webp') }}" alt="Energy operations and forecasting" fetchpriority="high" width="2702" height="1120">
        <div class="container product-hero-copy">
            <span class="obs-kicker">AI ENERGY FORECASTING &amp; OPTIMIZATION</span>
            <h1 id="product-title">A clear path from energy history to the next decision.</h1>
            <p>Caldren brings your usage, demand outlook, and tariff context into one review flow for energy and operations teams.</p>
            <div class="flex flex-wrap gap-4"><a class="button" href="{{ route('register') }}">Start a 30-day Site trial</a><a class="obs-outline-button" href="{{ route('login') }}">Sign in</a></div>
            <small>Estimates support human review. No card required for the Site trial.</small>
        </div>
    </section>

    <section class="product-identity-band" aria-labelledby="pulse-title">
        <div class="container product-identity-inner">
            <div class="product-identity-mark" aria-hidden="true"><span>CALDREN</span><strong>CP<span>/01</span></strong><i></i></div>
            <div class="product-identity-copy">
                <div class="product-identity-meta"><span class="product-beta-label">BETA</span><span>AI ENERGY INTELLIGENCE</span></div>
                <h2 id="pulse-title">Meet <span>Caldren Pulse.</span></h2>
                <p>An AI energy forecasting and optimization workspace for teams that need to understand demand, anticipate peaks, and review practical next steps.</p>
                <div class="product-identity-capabilities"><span>READ <b>usage history</b></span><span>FORECAST <b>seven-day demand</b></span><span>REVIEW <b>tariff-aware opportunities</b></span></div>
            </div>
            <p class="product-identity-footnote">BETA PRODUCT · Estimates are decision support for human review. Forecasting runs through Groq today; the GPU pipeline below is a future direction.</p>
        </div>
    </section>

    <nav class="product-tech-nav" aria-label="Product sections"><div class="container"><a href="#today">Today</a><a href="#pipeline">Forecast pipeline</a><a href="#deployment">Deployment</a><a href="#training">Model training</a><a href="#roadmap">Roadmap</a></div></nav>

    <section id="today" class="product-tech-section container" aria-labelledby="today-title">
        <div class="product-tech-intro"><span class="eyebrow">01 / CURRENT SYSTEM</span><h2 id="today-title">The current forecast starts with a bounded energy summary.</h2><p>Caldren’s Laravel application runs on the web host. It checks interval coverage, calculates daily history and a baseline, then sends a compact summary to the configured Groq Chat Completions model.</p></div>
        <div class="product-tech-columns">
            <article><span class="tech-index">A — INPUT</span><h3>Fourteen complete days</h3><p>Hourly or sub-hourly readings are grouped into daily kWh and peak estimates. The request includes the building time zone and configured tariff details needed for recommendations.</p><code>daily kWh · daily peak · tariff context</code></article>
            <article><span class="tech-index">B — RESPONSE</span><h3>Estimate, validate, store</h3><p>The current Groq flow returns a seven-day numeric outlook and review opportunities. Laravel checks dates, ranges, and historical plausibility before saving it with the model label.</p><code>Groq Chat Completions · JSON mode</code></article>
            <article><span class="tech-index">C — REVIEW</span><h3>Keep people in control</h3><p>Teams compare estimates with operating conditions and actual usage. Suggestions support decisions; Caldren does not automate building equipment or promise savings.</p><code>history remains available · retry on failure</code></article>
        </div>
    </section>

    <section id="pipeline" class="product-tech-dark" aria-labelledby="pipeline-title"><div class="container product-tech-layout">
        <div><span class="eyebrow">02 / PLANNED NUMERIC PIPELINE</span><h2 id="pipeline-title">Separate numeric forecasting from recommendation writing.</h2><p>The next architecture introduces a separately hosted, authenticated GPU service. Laravel will send the bounded daily series over HTTPS. Triton may serve an evaluated numeric model in a later phase; Groq remains the planned provider for grounded recommendations.</p><p class="tech-roadmap-label"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Planned integration — not active until a GPU host and an evaluated model are configured.</p></div>
        <ol class="product-pipeline-list">
            <li><span>01</span><div><strong>Laravel on cPanel</strong><p>Validates the workspace request, sends the 14-day daily summary, checks returned dates and ranges, and stores the result source and version.</p></div></li>
            <li><span>02</span><div><strong>CUDA + RAPIDS / cuML</strong><p>CUDA provides GPU computation. RAPIDS and cuML are intended for feature processing and training numeric demand models.</p></div></li>
            <li><span>03</span><div><strong>Triton inference behind the gateway</strong><p>Triton will return seven daily kWh and peak estimates. It stays on the private service network and is not exposed directly to the public internet.</p></div></li>
            <li><span>04</span><div><strong>Groq recommendations</strong><p>In a future GPU pipeline, validated estimates and bounded tariff context would continue to Groq for review opportunities. Today, Groq handles both forecasts and recommendations.</p></div></li>
        </ol>
    </div></section>

    <section id="deployment" class="product-tech-section container" aria-labelledby="deployment-title"><div class="product-tech-split"><div><span class="eyebrow">03 / SERVICE BOUNDARY</span><h2 id="deployment-title">Keep the app and GPU service deployable on their own.</h2><p>The Laravel application remains suited to the existing cPanel deployment. GPU inference runs in a separate container on a CUDA-capable host, with a bearer-authenticated HTTPS API between the two services.</p><p>Triton is private behind the gateway. Endpoint credentials and the active model version are supplied through deployment configuration. If Groq cannot produce a valid result, the forecast run records a failure while readings and previous forecasts remain available.</p></div><div class="tech-spec-panel"><div><span>APP</span><strong>Laravel · cPanel · MySQL</strong></div><div><span>GPU API</span><strong>HTTPS · authenticated · containerized</strong></div><div><span>INFERENCE</span><strong>CUDA · cuML model · Triton</strong></div><div><span>FAILOVER</span><strong>Groq estimate path · retry available</strong></div><small>Service boundary and model-serving design; host provisioning is not included.</small></div></div></section>

    <section id="training" class="product-tech-consent" aria-labelledby="training-title"><div class="container product-tech-split"><div><span class="eyebrow">04 / CUSTOMER DATA CONTROL</span><h2 id="training-title">Training contribution is optional and off by default.</h2><p>Only an organization owner can opt in. The training export uses daily energy values from opted-in workspaces and omits account, organization, building, and user identifiers. Inference uses the individual workspace’s own summary regardless of training consent.</p><p>An owner can withdraw consent at any time. Caldren then stops including that workspace in new exports, retires an active model whose recorded training cohort includes it, and continues using Groq forecasts while a replacement model trained without that workspace is evaluated.</p></div><aside><span class="consent-status"><i class="fa-solid fa-lock" aria-hidden="true"></i> DEFAULT: OFF</span><h3>Activation gate</h3><ul><li>Enough opted-in readings for training</li><li>Walk-forward evaluation against a seasonal baseline</li><li>Candidate beats the baseline on daily kWh and peak error</li><li>Manual release activation after checks pass</li></ul><p>No benchmark or accuracy claim is made before evaluation.</p></aside></div></section>

    <section id="roadmap" class="product-tech-section container" aria-labelledby="roadmap-title"><div class="product-tech-split product-roadmap"><div><span class="eyebrow">05 / FUTURE EXPLORATION</span><h2 id="roadmap-title">Omniverse is a possible digital-twin direction.</h2><p>NVIDIA Omniverse and OpenUSD may inform a later exploration of connected 3D building context and simulation. They are not part of the current product or the planned numeric forecast service.</p></div><aside class="roadmap-note"><strong>Roadmap only</strong><p>No 3D or BIM import, building simulation, or interactive digital twin is included in this release.</p></aside></div></section>

    <section class="container product-tech-close"><span class="eyebrow">ENERGY / UTILITIES</span><h2>Start with the data you already have.</h2><p>Explore the Site trial, then bring your portfolio questions into focus with your team.</p><a class="button" href="{{ route('register') }}">Create a workspace</a></section>
</div>
@endsection
