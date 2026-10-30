@extends('layouts.dashboard')
@section('title', 'Energy operations overview')
@section('breadcrumb', 'Intelligence / Overview')
@section('content')
<div class="dashboard-page-head"><div><span class="eyebrow">ENERGY OPERATIONS WORKSPACE</span><h1>{{ $organization->name }}</h1><p>A current view of energy use, forecast readiness, operational signals, and work in progress.</p></div><div class="quick-actions"><a class="button small" href="{{ route('buildings.index') }}">Add or review buildings</a><a class="button button-secondary small" href="{{ route('reports.index') }}">Create report</a></div></div>
<section class="operations-metrics" aria-label="Portfolio performance">
    <article class="ops-metric"><span>30-day energy use</span><strong>{{ number_format($energyUse, 1) }} <small>kWh</small></strong><p>Across available interval summaries.</p></article>
    <article class="ops-metric"><span>Estimated standard-rate cost</span><strong>@forelse($estimatedCosts as $currency => $cost){{ $currency }} {{ number_format($cost, 2) }}@if(!$loop->last)<br>@endif @empty — @endforelse</strong><p>Separated by building currency.</p></article>
    <article class="ops-metric"><span>Highest recorded site peak</span><strong>{{ number_format($highestPeak, 1) }} <small>kW</small></strong><p>Highest individual building peak.</p></article>
    <article class="ops-metric"><span>Forecast ready</span><strong>{{ $forecastReady }} / {{ $buildings->count() }}</strong><p>14 complete days at 75% coverage.</p></article>
    <article class="ops-metric {{ $openAlerts ? 'metric-attention' : '' }}"><span>Open alerts</span><strong>{{ $openAlerts }}</strong><p><a href="{{ route('alerts.index') }}">Review operational signals</a></p></article>
    <article class="ops-metric"><span>Active opportunities</span><strong>{{ $activeOpportunities }}</strong><p><a href="{{ route('opportunities.index') }}">Open the work register</a></p></article>
    <article class="ops-metric"><span>Data health</span><strong>{{ $healthyBuildings }} / {{ $buildings->count() }}</strong><p><a href="{{ route('data-health') }}">Buildings meeting coverage</a></p></article>
</section>
<div class="dashboard-two-column">
    <section class="dashboard-panel"><div class="panel-heading"><div><span class="eyebrow">PORTFOLIO RANKING</span><h2>Top-consuming buildings</h2></div><a href="{{ route('analytics') }}">Open analytics</a></div>
        @forelse($topBuildings as $building)<a class="ranking-row" href="{{ route('buildings.show', $building) }}"><span><strong>{{ $building->name }}</strong><small>{{ $building->location ?: 'Location not set' }}</small></span><b>{{ number_format($building->dailySummaries->sum('kwh'), 1) }} kWh</b></a>@empty<div class="empty-state"><i class="fa-solid fa-wave-square" aria-hidden="true"></i><h3>No interval summaries yet</h3><p>Upload timestamp and kWh readings to build the portfolio view.</p></div>@endforelse
    </section>
    <section class="dashboard-panel"><div class="panel-heading"><div><span class="eyebrow">NEXT ACTIONS</span><h2>Move the workspace forward</h2></div></div><div class="action-list"><a href="{{ route('data-health') }}"><i class="fa-solid fa-heart-pulse"></i><span><strong>Resolve data gaps</strong><small>Check coverage, staleness and tariff completeness.</small></span></a><a href="{{ route('forecasts.index') }}"><i class="fa-solid fa-cloud-sun"></i><span><strong>Review forecast readiness</strong><small>See eligible buildings and latest seven-day outlooks.</small></span></a><a href="{{ route('opportunities.index') }}"><i class="fa-solid fa-list-check"></i><span><strong>Assign operational work</strong><small>Plan and track recommendations with your team.</small></span></a></div></section>
</div>
@endsection
