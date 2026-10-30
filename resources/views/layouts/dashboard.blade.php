<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#102A33">
    <link rel="icon" type="image/webp" href="{{ asset('images/favicon.webp') }}">
    <title>@yield('title', 'Dashboard') — Caldren</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-body">
<a class="skip-link" href="#main-content">Skip to content</a>
@php
    $navigation = [
        'Intelligence' => [
            ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'fa-gauge-high', 'label' => 'Overview'],
            ['route' => 'analytics', 'match' => 'analytics', 'icon' => 'fa-chart-line', 'label' => 'Analytics'],
            ['route' => 'forecasts.index', 'match' => 'forecasts.*', 'icon' => 'fa-cloud-sun', 'label' => 'Forecasts'],
            ['route' => 'reports.index', 'match' => 'reports.*', 'icon' => 'fa-file-export', 'label' => 'Reports'],
        ],
        'Operations' => [
            ['route' => 'opportunities.index', 'match' => 'opportunities.*', 'icon' => 'fa-bullseye', 'label' => 'Opportunities'],
            ['route' => 'alerts.index', 'match' => 'alerts.*', 'icon' => 'fa-bell', 'label' => 'Alerts'],
            ['route' => 'data-health', 'match' => 'data-health', 'icon' => 'fa-heart-pulse', 'label' => 'Data Health'],
        ],
        'Portfolio' => [['route' => 'buildings.index', 'match' => 'buildings.*', 'icon' => 'fa-building', 'label' => 'Buildings']],
        'Workspace' => [
            ['route' => 'team', 'match' => 'team', 'icon' => 'fa-users', 'label' => 'Team'],
            ['route' => 'billing', 'match' => 'billing', 'icon' => 'fa-key', 'label' => 'Plan & Access'],
        ],
    ];
@endphp
<div class="dashboard-shell">
    <aside class="dashboard-sidebar">
        <a class="brand brand-light" href="{{ route('dashboard') }}" aria-label="Caldren dashboard"><img class="brand-logo" src="{{ asset('images/logo.webp') }}" alt="" width="2731" height="1535"></a>
        <nav aria-label="Dashboard navigation">
            @foreach($navigation as $group => $items)
                <div class="sidebar-group"><div class="sidebar-label">{{ strtoupper($group) }}</div>
                    @foreach($items as $item)<a class="{{ request()->routeIs($item['match']) ? 'active' : '' }}" href="{{ route($item['route']) }}"><i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }}</a>@endforeach
                </div>
            @endforeach
        </nav>
        <div class="sidebar-bottom"><span class="sidebar-badge"><i class="fa-solid fa-bolt" aria-hidden="true"></i> Energy operations</span><a href="{{ route('home') }}">← Back to website</a><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form></div>
    </aside>
    <div class="dashboard-main">
        <header class="dashboard-topbar glass">
            <div class="dashboard-mobile-brand"><a class="brand" href="{{ route('dashboard') }}" aria-label="Caldren dashboard"><img class="brand-logo" src="{{ asset('images/logo.webp') }}" alt="" width="2731" height="1535"></a></div>
            <div class="topbar-breadcrumb">@yield('breadcrumb', 'Workspace / Overview')</div>
            <div class="topbar-right">
                @if(auth()->user()->organizations()->count() > 1)<form method="post" action="{{ route('organization.switch') }}">@csrf<select name="organization_id" aria-label="Switch workspace" onchange="this.form.submit()">@foreach(auth()->user()->organizations as $workspace)<option value="{{ $workspace->id }}" @selected(app(\App\Services\OrganizationContext::class)->current(request())->id === $workspace->id)>{{ $workspace->name }}</option>@endforeach</select></form>@endif
                <span class="topbar-date">{{ now()->format('D, M j') }}</span><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="topbar-name">{{ auth()->user()->name }}</span>
            </div>
        </header>
        <details class="mobile-dashboard-menu"><summary><span><i class="fa-solid fa-bars" aria-hidden="true"></i> Workspace menu</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><nav aria-label="Mobile dashboard">@foreach($navigation as $group => $items)<strong>{{ $group }}</strong>@foreach($items as $item)<a href="{{ route($item['route']) }}">{{ $item['label'] }}</a>@endforeach @endforeach<form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form></nav></details>
        <main id="main-content" class="dashboard-content">
            @if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="flash error" role="alert">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="flash error" role="alert">{{ $errors->first() }}</div>@endif
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
