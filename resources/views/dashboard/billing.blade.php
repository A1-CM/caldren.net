@extends('layouts.dashboard')
@section('title','Billing')
@section('breadcrumb','Workspace / Billing')
@section('content')
<div class="dash-heading"><div><span class="eyebrow">DEMO BILLING</span><h1>Plan and access</h1><p>Review your trial, building allowance, and demo plan.</p></div></div>
<div class="demo-warning"><strong>Simulation only</strong><p>This checkout does not connect to a payment processor or charge a card. Never enter a real card number.</p></div>
<div class="two-col"><section class="solid-panel padded"><span class="eyebrow">WORKSPACE PLAN</span>
@if($organization->subscription?->status === 'active' && $organization->subscription->plan_key && isset($plans[$organization->subscription->plan_key]))
@php($activePlan = $plans[$organization->subscription->plan_key])
<h2>{{ $activePlan['name'] }}</h2><div class="billing-price">{{ '$' }}{{ number_format($activePlan['monthly_price_cents'] / 100, 0) }} <span>USD / month</span></div><p>Up to {{ $activePlan['building_limit'] }} buildings with all current features and no team seat limit.</p>
@elseif($organization->trial_ends_at->isFuture())
<h2>Full trial workspace</h2><div class="billing-price">30 <span>days · no card required</span></div><p>Explore every current feature with up to 50 buildings. Choose a tier when you activate demo access.</p>
@else
<h2>Choose a demo plan</h2><p>Your trial has ended. Existing buildings and their data remain available. Select a plan to activate one month of demo access.</p>
@endif
<dl class="plan-details"><div><dt>Status</dt><dd>{{ $organization->accessStatus() }}</dd></div><div><dt>Buildings</dt><dd>{{ $organization->buildings()->count() }} / {{ $organization->buildingLimit() }}</dd></div><div><dt>Trial ends</dt><dd>{{ $organization->trial_ends_at->format('M j, Y') }}</dd></div><div><dt>Demo access through</dt><dd>{{ $organization->subscription?->current_period_ends_at?->format('M j, Y') ?? '—' }}</dd></div></dl>
</section><section class="solid-panel padded"><span class="eyebrow">DEMO CHECKOUT</span><h2>Choose a scale</h2><p class="panel-copy">The 4242 flow records the selected demo plan and adds one month of access. No money is charged; access does not renew.</p>
@if($isOwner)<form method="post" action="{{ route('billing.demo') }}">@csrf
<label>Plan<select name="plan_key" required><option value="">Choose a building allowance</option>@foreach($plans as $key => $plan)<option value="{{ $key }}" @selected(old('plan_key', $selectedPlanKey) === $key)>{{ $plan['name'] }} — USD {{ number_format($plan['monthly_price_cents'] / 100, 0) }}/month · up to {{ $plan['building_limit'] }} buildings</option>@endforeach</select></label>@error('plan_key')<p class="form-error">{{ $message }}</p>@enderror
<label>Demo card number<input name="demo_card" inputmode="numeric" value="4242 4242 4242 4242" maxlength="23" required></label>@error('demo_card')<p class="form-error">{{ $message }}</p>@enderror<button class="button full" type="submit">Simulate selected plan</button></form>@else<p class="helper">Only a workspace owner can activate demo access.</p>@endif
<p class="helper">Demo payments do not renew automatically. Each simulation adds one month of access.</p></section></div>
<div class="dash-section-head"><div><span class="eyebrow">ACTIVITY</span><h2>Demo payment history</h2></div></div>
<div class="solid-panel table-wrap"><table><thead><tr><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ \Carbon\Carbon::parse($payment->created_at)->format('M j, Y') }}</td><td>USD {{ number_format($payment->amount_cents/100,2) }}</td><td>Simulated</td></tr>@empty<tr><td colspan="3" class="table-empty">No demo payments recorded.</td></tr>@endforelse</tbody></table></div>
@endsection
