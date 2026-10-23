<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102A33">
    <link rel="icon" type="image/webp" href="{{ asset('images/favicon.webp') }}">
    <meta name="description" content="Sign in or create a Caldren workspace for AI-assisted energy forecasting and optimization insights.">
    <title>@yield('title', 'Caldren account')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="site-body auth-body">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="auth-header">
        <div class="auth-header-inner">
            <div class="auth-brand-group">
                <a class="auth-home-button" href="{{ route('home') }}" aria-label="Back to Caldren home">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                </a>
                <a class="auth-brand" href="{{ route('home') }}" aria-label="Caldren home">
                    <span class="auth-brand-tile"><img src="{{ asset('images/logo.webp') }}" alt="Caldren" width="120" height="68"></span>
                </a>
            </div>
            <a class="auth-contact-link" href="{{ route('home') }}#contact"><i class="fa-regular fa-envelope" aria-hidden="true"></i><span>Contact</span></a>
        </div>
    </header>
    <main id="main-content" class="auth-main" tabindex="-1">
        @if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
        @yield('content')
    </main>
    <footer class="auth-footer">
        <div class="auth-footer-inner">
            <span>© {{ date('Y') }} Caldren</span>
            <nav aria-label="Account page information">
                <a href="{{ route('home') }}#privacy">Privacy</a>
                <a href="{{ route('home') }}#terms">Terms</a>
                <a href="mailto:hello@caldren.net">hello@caldren.net</a>
            </nav>
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
</body>
</html>
