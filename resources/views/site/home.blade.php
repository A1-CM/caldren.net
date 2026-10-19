@extends('layouts.site')
@section('content')
<div class="scroll-story" data-story-page>
<section class="observatory-hero observatory-hero-parallax" aria-labelledby="home-title" data-scroll-parallax>
    <div class="hero-photo-layer" data-parallax-speed="0.16" style="background-image:url('{{ asset('images/caldren-building-dusk.webp') }}')" aria-hidden="true"></div>
    <div class="hero-photo-shade" aria-hidden="true"></div>
    <div class="hero-copy-centered">
        <span class="obs-kicker"><span class="obs-live-dot" aria-hidden="true"></span> AI ENERGY INTELLIGENCE FOR BUILDINGS</span>
        <h1 id="home-title">See tomorrow’s energy demand. <span class="obs-glow-text">Make better decisions today.</span></h1>
        <p>Give your team a clear view of building usage, likely demand peaks, and opportunities worth reviewing. Start with the data you already have.</p>
        <div class="hero-centered-actions"><a class="button" href="{{ route('product') }}">Explore Caldren Pulse</a><a class="obs-outline-button" href="{{ route('register') }}">Start Site trial</a></div>
        <div class="hero-data-notes"><span><i class="fa-solid fa-credit-card" aria-hidden="true"></i> No card required</span><span><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Bills or interval readings</span><span><i class="fa-solid fa-user-check" aria-hidden="true"></i> Your team reviews estimates</span></div>
    </div>
    <a class="hero-scroll-cue" href="#story"><span>SCROLL TO SEE HOW IT CONNECTS</span><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></a>
</section>
<section id="story" tabindex="-1" class="container story-section" aria-labelledby="questions-title">
<div id="about" class="story-heading" data-reveal><span class="eyebrow">01 / ENERGY SIGNALS TO DECISIONS</span><h2 id="questions-title">See the pattern.<br>Anticipate demand. Find where to look.</h2><p>Bring energy history and operating context together to support the next decision.</p></div>
<div class="story-scene" data-scroll-scene><figure class="story-stage"><img src="{{ asset('images/caldren-building-dusk.webp') }}" alt="Commercial office building illuminated at dusk" loading="lazy" width="1536" height="1024" data-scene-image><figcaption><span>ENERGY OPERATIONS</span><strong data-scene-caption>01 / Understand</strong></figcaption></figure><div class="story-chapters">
<article data-story-step data-caption="01 / Understand"><span class="eyebrow">01 / UNDERSTAND</span><h3>What does demand look like?</h3><p>Bring interval readings and utility bills into one view. Review consumption and spending history across the commercial sites your team manages.</p><span class="story-footnote">Start with the data already available to your energy team.</span></article>
<article data-story-step data-caption="02 / Forecast"><span class="eyebrow">02 / FORECAST</span><h3>What might demand do next?</h3><p>When interval history qualifies, review seven days of daily energy and peak estimates alongside their source period and historical baseline.</p><span class="story-footnote">Forecasts are estimates for your team to assess against actual conditions.</span></article>
<article data-story-step data-caption="03 / Optimize"><span class="eyebrow">03 / OPTIMIZE</span><h3>Where is there an opportunity to review?</h3><p>Use possible peak alerts and tariff-aware insights to identify schedules, flexible loads, or operating patterns worth investigating with your energy team.</p><span class="story-footnote">Caldren supports optimization decisions; your team remains in control.</span></article>
</div></div>
</section>
@include('site.how')
<section id="workspace" tabindex="-1" class="story-photo-band" data-scroll-scene aria-labelledby="workspace-title"><img src="{{ asset('images/caldren-building-dusk.webp') }}" alt="" loading="lazy" width="1536" height="1024" data-scene-image><div class="container story-band-copy" data-reveal><span class="obs-kicker">03 / ONE ENERGY VIEW</span><h2 id="workspace-title">Connect the signals.<br>Keep the context close.</h2><p>Bring readings, utility bills, tariffs, and forecasts together across your commercial sites. Give operations and energy teams a shared view of demand and opportunities to review.</p><a class="button button-light" href="{{ route('product') }}">Explore forecasting and optimization insights</a></div></section>
@include('site.pricing')
<div data-reveal>@include('site.testimonials')</div>
<section id="faq" tabindex="-1" data-reveal class="obs-faq-section" aria-labelledby="faq-title"><div class="container obs-faq-layout"><div><span class="eyebrow">FREQUENTLY ASKED QUESTIONS</span><h2 id="faq-title">Clear answers for energy teams.</h2><p>Learn how forecasts and optimization insights fit your existing energy data and review process.</p><a class="text-cta" href="{{ route('home') }}#contact">Ask us something else</a></div><div class="obs-faq-list">
<details><summary>What data do I need for an energy forecast?</summary><p>Enter monthly utility bills to review spending trends, or upload a CSV with <code>timestamp,kwh</code> interval readings. Seven-day forecasts require at least 14 complete days of recent hourly or sub-hourly readings with sufficient coverage.</p></details>
<details><summary>Can I use Caldren with only utility bills?</summary><p>Yes. Add at least two bills to compare usage and spending over time. Interval readings are required for daily energy and peak forecasts.</p></details>
<details><summary>What do the forecasts and optimization insights show?</summary><p>Forecasts estimate daily energy use and peak demand for the next seven days when data qualifies. Possible peak alerts and tariff-aware opportunities can help your team decide what to investigate. Review all estimates against actual conditions; Caldren does not control equipment.</p></details>
<details><summary>Can my energy and operations teams share a workspace?</summary><p>Yes. A workspace owner can invite teammates to the organization so they can review the same site data, forecasts, and opportunities.</p></details>
<details><summary>What happens after the 30-day trial?</summary><p>Historical data stays visible. Adding sites or bills, uploads, and new forecasts are gated until demo access is activated. Choose Site or Portfolio after the trial; the Site trial supports up to 3 buildings.</p></details>
<details><summary>Does demo checkout charge a real card?</summary><p>No. A signed-in workspace owner selects Site ($49/month, up to 3 buildings) or Portfolio ($99/month, up to 10). Campus portfolios can contact sales. Self-serve demo checkout uses the 4242 number. Checkout records a simulated payment, adds one month of access, stores no card details, and does not renew automatically.</p></details>
</div></div></section>
@include('site.contact')
<section class="container story-close" data-reveal><span class="eyebrow">YOUR NEXT ENERGY DECISION</span><h2>Bring your energy data into focus.</h2><p>Explore forecasts and optimization insights for 30 days. No card required.</p><a class="button" href="{{ route('register') }}">Start your Site trial</a></section>
<section class="container story-legal" aria-label="Privacy and terms">@include('site.privacy') @include('site.terms')</section>
</div>
@endsection
