@extends('layouts.dashboard')
@section('title', 'Plan & Access')
@section('breadcrumb', 'Workspace / Plan & Access')
@section('content')
<div class="dashboard-page-head"><div><span class="eyebrow">PLAN & ACCESS</span><h1>Workspace access</h1><p>Review the current plan, building allowance, and access period.</p></div></div>
<div class="activation-note"><i class="fa-solid fa-credit-card" aria-hidden="true"></i><div><strong>Plan activation checkout</strong><p>This form does not contact a card network or charge a card. It accepts only the displayed 4242 number to grant one month of access. Card details are checked for this request, then discarded and never saved.</p></div></div>
<div class="dashboard-two-column">
    <section class="dashboard-panel"><span class="eyebrow">CURRENT ACCESS</span>
        @if($organization->subscription?->status === 'active' && $organization->subscription->plan_key && isset($plans[$organization->subscription->plan_key]))
            @php($activePlan = $plans[$organization->subscription->plan_key])
            <h2>{{ $activePlan['name'] }}</h2><div class="billing-price">{{ '$' }}{{ number_format($activePlan['monthly_price_cents'] / 100, 0) }} <span>USD / month plan reference</span></div><p>Up to {{ $activePlan['building_limit'] }} buildings with all current features and no team seat limit.</p>
        @elseif($organization->trial_ends_at->isFuture())
            <h2>Site trial</h2><div class="billing-price">30 <span>days · no card required</span></div><p>Use current features with up to 3 buildings. Choose Site or Portfolio when the trial ends.</p>
        @else
            <h2>Choose an access plan</h2><p>Your trial has ended. Existing buildings and their data remain available. Activate Site or Portfolio to add one month of access.</p>
        @endif
        <dl class="plan-details"><div><dt>Status</dt><dd>{{ $organization->accessStatus() }}</dd></div><div><dt>Buildings</dt><dd>{{ $organization->buildings()->count() }} / {{ $organization->buildingLimit() }}</dd></div><div><dt>Trial ends</dt><dd>{{ $organization->trial_ends_at->format('M j, Y') }}</dd></div><div><dt>Active through</dt><dd>{{ $organization->subscription?->current_period_ends_at?->format('M j, Y') ?? '—' }}</dd></div></dl>
    </section>
    <section class="dashboard-panel checkout-panel"><span class="eyebrow">PLAN ACTIVATION</span><h2>Choose a building allowance</h2><p>Select your plan and enter the card details below. Only the displayed 4242 number is accepted; this is not a real payment checkout.</p>
        @if($isOwner)
            <form method="post" action="{{ route('billing.activate') }}">@csrf
                <label>Plan<select name="plan_key" required><option value="">Choose a building allowance</option>@foreach($checkoutPlans as $key => $plan)<option value="{{ $key }}" @selected(old('plan_key', $selectedPlanKey) === $key)>{{ $plan['name'] }} — USD {{ number_format($plan['monthly_price_cents'] / 100, 0) }}/month · up to {{ $plan['building_limit'] }} buildings</option>@endforeach</select></label>@error('plan_key')<p class="form-error">{{ $message }}</p>@enderror
                <div class="checkout-card-fields">
                    <div class="checkout-field-wide"><label>Cardholder name<input name="cardholder" value="{{ old('cardholder') }}" autocomplete="off" maxlength="120" placeholder="Name on card" required></label>@error('cardholder')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <div class="checkout-field-wide"><label>Card number<div class="card-number-wrap"><i class="fa-regular fa-credit-card" aria-hidden="true"></i><input name="card_number" inputmode="numeric" autocomplete="off" maxlength="32" placeholder="4242 4242 4242 4242" required></div></label>@error('card_number')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <div><label>Expiry date<input type="month" name="expiry" autocomplete="off" value="{{ old('expiry') }}" required></label>@error('expiry')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <div><label>Security code<input name="cvc" inputmode="numeric" autocomplete="off" maxlength="4" placeholder="CVC" required></label>@error('cvc')<p class="form-error">{{ $message }}</p>@enderror</div>
                </div>
                <button class="button full" type="submit">Activate selected plan</button>
            </form>
        @else
            <p class="helper">Only a workspace owner can activate a plan.</p>
        @endif
    </section>
</div>
<section class="dashboard-panel"><div class="panel-heading"><div><span class="eyebrow">ACTIVITY</span><h2>Activation history</h2></div></div><div class="responsive-table"><table><thead><tr><th>Date</th><th>Plan reference</th><th>Status</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ \Carbon\Carbon::parse($payment->created_at)->format('M j, Y') }}</td><td>USD {{ number_format($payment->amount_cents/100,2) }}</td><td>Activated</td></tr>@empty<tr><td colspan="3">No activations recorded.</td></tr>@endforelse</tbody></table></div></section>
@endsection
