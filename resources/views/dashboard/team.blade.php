@extends('layouts.dashboard')
@section('title','Team')
@section('breadcrumb','Workspace / Team')
@section('content')<div class="dash-heading"><div><span class="eyebrow">WORK TOGETHER</span><h1>Team</h1><p>People who can see {{ $organization->name }} and its buildings.</p></div></div><div class="two-col"><section class="solid-panel padded"><h2>Members</h2>@foreach($members as $member)<div class="member-row"><span class="avatar">{{ strtoupper(substr($member->name,0,1)) }}</span><div><strong>{{ $member->name }}</strong><small>{{ $member->email }}</small></div><span class="role-tag">{{ ucfirst($member->pivot->role) }}</span></div>@endforeach</section><section class="solid-panel padded"><span class="eyebrow">GROW YOUR TEAM</span><h2>Invite a teammate</h2><p class="panel-copy">An invitation link is sent by email and expires in seven days. Only owners can invite teammates.</p>@if($isOwner)<form method="post" action="{{ route('team.invite') }}">@csrf
<label>Email address<input type="email" name="email" required placeholder="teammate@company.com"></label><button class="button" type="submit">Send invitation</button></form>@else<p class="helper">Ask a workspace owner to invite teammates.</p>@endif @if($invitations->isNotEmpty())<h3>Pending invitations</h3>@foreach($invitations as $invitation)<p class="pending-invite">{{ $invitation->email }} · expires {{ $invitation->expires_at->format('M j') }}</p>@endforeach @endif</section></div>
<section class="solid-panel padded training-consent-panel">
    <span class="eyebrow">OPTIONAL MODEL TRAINING</span><h2>Contribute readings to improve future demand models</h2>
    <p class="panel-copy">This setting is off by default. If enabled, de-identified daily energy readings from this workspace may be used to train future demand models. The training data file contains no account, organization, building, or user identifiers. A separate private consent manifest is retained for activation checks and is not sent to the GPU trainer. Forecasting your own workspace does not require opting in. You can withdraw at any time.</p>
    @if($isOwner)
        @if($modelTrainingOptedIn)
            <p class="consent-enabled"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> This workspace is currently opted in.</p>
            <form method="post" action="{{ route('team.model-training-consent') }}">@csrf<input type="hidden" name="enabled" value="0"><button class="button button-ghost" type="submit">Withdraw consent</button></form>
        @else
            <p class="helper">No readings from this workspace are included in training.</p>
            <form method="post" action="{{ route('team.model-training-consent') }}">@csrf<input type="hidden" name="enabled" value="1"><label class="consent-checkbox"><input type="checkbox" name="consent" value="1" required> I am the workspace owner and consent to Caldren using de-identified daily energy readings from this workspace to train future demand models.</label><button class="button" type="submit">Opt in to model training</button></form>
        @endif
    @else
        <p class="helper">Only a workspace owner can change this setting. Current status: {{ $modelTrainingOptedIn ? 'opted in' : 'off' }}.</p>
    @endif
</section>@endsection
