<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#102A33"><link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}"><meta name="description" content="Caldren helps commercial building teams forecast energy demand and act on opportunities with AI energy intelligence."><title>@yield('title', 'Caldren — AI energy intelligence')</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="site-body"><a class="skip-link" href="#main-content">Skip to content</a><div class="ambient ambient-one"></div><div class="ambient ambient-two"></div>
<header class="site-header console-header">
    <div class="console-header-inner">
        <a class="brand" href="{{ route('home') }}" aria-label="Caldren home" @if(request()->routeIs('home')) aria-current="page" @endif>
            <svg class="brand-mark" viewBox="0 0 42 42" fill="none" aria-hidden="true"><rect width="42" height="42" rx="12" fill="#0B8F7A"/><path d="M7 25h7l4-10 6 17 4-11h7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Caldren</span>
        </a>
        <button class="console-menu-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="main-navigation" data-menu-toggle>
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <nav id="main-navigation" aria-label="Main navigation">
            <a href="{{ route('product') }}" @if(request()->routeIs('product')) aria-current="page" @endif>Product</a>
            <a href="{{ route('how') }}" @if(request()->routeIs('how')) aria-current="page" @endif>How it works</a>
            <a href="{{ route('pricing') }}" @if(request()->routeIs('pricing')) aria-current="page" @endif>Pricing</a>
            <a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
            <div class="console-mobile-actions">
                @auth
                    <a href="{{ route('dashboard') }}"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i> Dashboard</a>
                @else
                    <a href="{{ route('login') }}"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Sign in</a>
                    <a class="console-mobile-cta" href="{{ route('register') }}"><i class="fa-solid fa-bolt" aria-hidden="true"></i> Start free trial</a>
                @endauth
            </div>
        </nav>
        <div class="console-header-actions">
            @auth
                <a class="console-dashboard-link" href="{{ route('dashboard') }}"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i> Dashboard</a>
            @else
                <a class="console-signin-link" href="{{ route('login') }}">Sign in</a>
                <a class="console-trial-link" href="{{ route('register') }}">Start free trial <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            @endauth
        </div>
    </div>
    <svg class="console-waveform" viewBox="0 0 1440 20" preserveAspectRatio="none" aria-hidden="true"><path d="M0 12h245l12-5 10 10 14-13 12 8 10-4h160l10-3 12 7 12-10 13 9 12-4h260l12-6 13 12 12-15 12 10 13-4h280l12-5 12 10 14-12 12 7 12-4h240" /></svg>
</header>
<main id="main-content">@if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
@yield('content')</main>
<footer class="site-footer"><div class="footer-top"><div><a class="brand" href="{{ route('home') }}"><span class="brand-mark-small"><i class="fa-solid fa-wave-square" aria-hidden="true"></i></span><span>Caldren</span></a><p>AI energy intelligence for better building decisions.</p></div><div><strong>Explore</strong><a href="{{ route('product') }}">Product</a><a href="{{ route('how') }}">How it works</a><a href="{{ route('pricing') }}">Pricing</a></div><div><strong>Company</strong><a href="{{ route('contact') }}">Contact</a><a href="{{ route('privacy') }}">Privacy</a><a href="{{ route('terms') }}">Terms</a></div></div><div class="footer-bottom"><span>© {{ date('Y') }} Caldren</span><span>Built for a clearer energy future.</span></div></footer></body></html>
