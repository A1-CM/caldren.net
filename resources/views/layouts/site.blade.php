<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#102A33"><link rel="icon" type="image/webp" href="{{ asset('images/favicon.webp') }}"><meta name="description" content="AI-assisted energy forecasting and optimization insights for energy, facilities, and operations teams managing commercial sites."><title>@yield('title', 'Caldren — AI Energy Forecasting &amp; Optimization')</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="site-body"><a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header console-header">
    <div class="console-header-inner">
        <a class="brand" href="{{ route('home') }}" aria-label="Caldren home" @if(request()->routeIs('home')) aria-current="page" @endif>
            <img class="brand-logo" src="{{ asset('images/logo.webp') }}" alt="" width="2731" height="1535">
        </a>
        <button class="console-menu-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="main-navigation" data-menu-toggle>
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <nav id="main-navigation" aria-label="Main navigation">
            <a href="{{ route('home') }}#about" data-section-link="about">About</a>
            <a href="{{ route('home') }}#how-it-works" data-section-link="how-it-works">How it works</a>
            <a href="{{ route('home') }}#pricing" data-section-link="pricing">Pricing</a>
            <a href="{{ route('home') }}#contact" data-section-link="contact">Contact</a>
            <div class="console-mobile-actions">
                @auth
                    <a href="{{ route('dashboard') }}"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i> Dashboard</a>
                @endauth
                <a class="console-mobile-cta" href="{{ route('product') }}" @if(request()->routeIs('product')) aria-current="page" @endif><i class="fa-solid fa-wave-square" aria-hidden="true"></i> Explore Caldren Pulse</a>
            </div>
        </nav>
        <div class="console-header-actions">
            @auth
                <a class="console-dashboard-link" href="{{ route('dashboard') }}"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i> Dashboard</a>
            @endauth
            <a class="console-trial-link" href="{{ route('product') }}" @if(request()->routeIs('product')) aria-current="page" @endif>Explore Caldren Pulse <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </div>
    <svg class="console-waveform" viewBox="0 0 1440 20" preserveAspectRatio="none" aria-hidden="true"><path d="M0 12h245l12-5 10 10 14-13 12 8 10-4h160l10-3 12 7 12-10 13 9 12-4h260l12-6 13 12 12-15 12 10 13-4h280l12-5 12 10 14-12 12 7 12-4h240" /></svg>
</header>
<main id="main-content" tabindex="-1">@if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
@yield('content')</main>
<footer class="site-footer">
    <section class="footer-contact-band" aria-labelledby="footer-contact-title" data-reveal>
        <div class="footer-contact-photo" aria-hidden="true"><img src="{{ asset('images/energy.webp') }}" alt="" loading="lazy" width="2702" height="1120"></div>
        <div class="footer-contact-content">
            <span class="footer-kicker">ENERGY / UTILITIES</span>
            <h2 id="footer-contact-title">Have an energy question? Let’s bring it into focus.</h2>
            <p>Tell us about your portfolio, available energy data, and goals.</p>
            <a class="footer-contact-cta" href="{{ route('home') }}#contact">Talk with the Caldren team</a>
        </div>
    </section>
    <div class="footer-base" data-reveal>
        <div class="footer-base-inner">
            <div class="footer-brand-block">
                <a class="brand" href="{{ route('home') }}" aria-label="Caldren home"><img class="brand-logo" src="{{ asset('images/logo.webp') }}" alt="" width="2731" height="1535"></a>
                <p>AI energy forecasting and optimization insights for energy teams.</p>
            </div>
            <nav class="footer-link-group" aria-label="Explore Caldren">
                <h3>Explore</h3>
                <a href="{{ route('product') }}">Product</a>
                <a href="{{ route('home') }}#how-it-works">How it works</a>
                <a href="{{ route('home') }}#pricing">Pricing</a>
            </nav>
            <nav class="footer-link-group" aria-label="Company information">
                <h3>Company</h3>
                <a href="{{ route('home') }}#contact">Contact</a>
                <a href="{{ route('home') }}#privacy">Privacy</a>
                <a href="{{ route('home') }}#terms">Terms</a>
            </nav>
            <nav class="footer-link-group footer-social-group" aria-label="Caldren social media">
                <h3>Follow</h3>
                <div class="footer-social-links">
                    <a class="footer-social-link" href="https://medium.com/@caldren.net" target="_blank" rel="noopener noreferrer"><img src="https://www.google.com/s2/favicons?domain=medium.com&sz=64" alt="" width="18" height="18" loading="lazy" referrerpolicy="no-referrer"><span>Medium</span></a>
                    <a class="footer-social-link" href="https://www.facebook.com/CaldrenSolutions/" target="_blank" rel="noopener noreferrer"><img src="https://www.google.com/s2/favicons?domain=facebook.com&sz=64" alt="" width="18" height="18" loading="lazy" referrerpolicy="no-referrer"><span>Facebook</span></a>
                    <a class="footer-social-link" href="https://www.youtube.com/@CaldrenSolutions" target="_blank" rel="noopener noreferrer"><img src="https://www.google.com/s2/favicons?domain=youtube.com&sz=64" alt="" width="18" height="18" loading="lazy" referrerpolicy="no-referrer"><span>YouTube</span></a>
                    <a class="footer-social-link" href="https://www.linkedin.com/company/caldren-solutions/" target="_blank" rel="noopener noreferrer"><img src="https://www.google.com/s2/favicons?domain=linkedin.com&sz=64" alt="" width="18" height="18" loading="lazy" referrerpolicy="no-referrer"><span>LinkedIn</span></a>
                </div>
            </nav>
            <div class="footer-bottom"><span>© {{ date('Y') }} Caldren</span><span>Built to support clearer energy decisions.</span></div>
        </div>
    </div>
</footer>
<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/6ad5af224bf5ff34c946e7a0/1k59b88is';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->
</body></html>
