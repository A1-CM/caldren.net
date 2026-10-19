<?php

namespace App\Http\Controllers;

use App\Mail\ContactInquiry;
use App\Models\Building;
use App\Models\Invitation;
use App\Services\ForecastService;
use App\Services\OrganizationContext;
use App\Services\UsageImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CaldrenController extends Controller
{
    private function organization(Request $request)
    {
        return app(OrganizationContext::class)->current($request);
    }

    private function building(Request $request, Building $building): Building
    {
        abort_unless($building->organization_id === $this->organization($request)->id, 404);

        return $building;
    }

    private function access(Request $request): void
    {
        if (! $this->organization($request)->hasAccess()) {
            throw ValidationException::withMessages(['access' => 'Your trial has ended. Activate the demo subscription to continue.']);
        }
    }

    public function contact(Request $request): RedirectResponse
    {
        try {
            $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255', 'company' => 'nullable|string|max:160', 'message' => 'required|string|max:4000']);
        } catch (ValidationException $error) {
            $error->redirectTo(route('home').'#contact');

            throw $error;
        }
        $leadId = DB::table('contact_leads')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        try {
            if (app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true)) {
                throw new RuntimeException('Contact mail transport is not configured.');
            }

            Mail::to(config('mail.contact_to'))->send(new ContactInquiry($data, $leadId));
        } catch (\Throwable $error) {
            Log::error('Contact notification failed.', ['contact_lead_id' => $leadId, 'error' => $error->getMessage()]);

            return redirect()->to(route('home').'#contact')->with('warning', 'Your message was saved, but our email notification could not be sent.');
        }

        return redirect()->to(route('home').'#contact')->with('contact_success', 'Thanks. We received your message.');
    }

    public function dashboard(Request $request)
    {
        $organization = $this->organization($request);
        $buildings = $organization->buildings()->withCount(['readings', 'bills'])->orderBy('name')->get();
        $recentForecasts = $organization->buildings()->with(['forecasts' => fn ($q) => $q->where('status', 'complete')->latest()->limit(1)])->get()->flatMap->forecasts;

        return view('dashboard.index', compact('organization', 'buildings', 'recentForecasts'));
    }

    public function buildings(Request $request)
    {
        $organization = $this->organization($request);
        $buildings = $organization->buildings()->withCount(['readings', 'bills'])->orderBy('name')->get();

        return view('dashboard.buildings', compact('organization', 'buildings'));
    }

    public function createBuilding(Request $request)
    {
        $this->access($request);
        $organization = $this->organization($request);
        if (! $organization->canAddBuilding()) {
            throw ValidationException::withMessages(['name' => 'Your workspace has reached its building limit. Review your plan or remove a building before adding another.']);
        }
        $data = $request->validate(['name' => 'required|string|max:160', 'location' => 'nullable|string|max:160', 'timezone' => 'required|timezone', 'currency' => 'required|string|size:3', 'energy_rate' => 'required|numeric|min:0|max:100000', 'peak_rate' => 'nullable|numeric|min:0|max:100000', 'peak_start_hour' => 'nullable|integer|between:0,23', 'peak_end_hour' => 'nullable|integer|between:0,23']);
        $data['currency'] = strtoupper($data['currency']);
        $building = $organization->buildings()->create($data);

        return redirect()->route('buildings.show', $building)->with('success', 'Building added.');
    }

    public function showBuilding(Request $request, Building $building)
    {
        $building = $this->building($request, $building);
        $organization = $this->organization($request);
        $bills = $building->bills()->latest('period_start')->limit(12)->get();
        $billChart = ['labels' => $bills->reverse()->map(fn ($bill) => $bill->period_start->format('M Y'))->values()->all(), 'amounts' => $bills->reverse()->map(fn ($bill) => (float) $bill->amount)->values()->all()];
        $readings = $building->readings()->latest('recorded_at')->limit(500)->get()->reverse()->values();
        $forecast = $building->forecasts()->where('status', 'complete')->latest()->with(['points', 'recommendations'])->first();
        $latestRun = $building->forecasts()->latest()->first();

        return view('dashboard.building', compact('building', 'organization', 'bills', 'billChart', 'readings', 'forecast', 'latestRun'));
    }

    public function upload(Request $request, Building $building, UsageImporter $importer)
    {
        $building = $this->building($request, $building);
        $this->access($request);
        $request->validate(['file' => 'required|file|max:5120|mimes:csv,txt']);
        $count = $importer->import($building, $request->file('file'));

        return back()->with('success', "Imported $count readings.");
    }

    public function bill(Request $request, Building $building)
    {
        $building = $this->building($request, $building);
        $this->access($request);
        $data = $request->validate(['period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start', 'kwh' => 'required|numeric|min:0|max:100000000', 'amount' => 'required|numeric|min:0|max:100000000']);
        $overlaps = $building->bills()
            ->where('period_start', '<=', $data['period_end'])
            ->where('period_end', '>=', $data['period_start'])
            ->where(function ($query) use ($data) {
                $query->where('period_start', '!=', $data['period_start'])->orWhere('period_end', '!=', $data['period_end']);
            })->exists();
        if ($overlaps) {
            throw ValidationException::withMessages(['period_start' => 'This bill overlaps a period already recorded for the building.']);
        }
        $building->bills()->updateOrCreate(['period_start' => $data['period_start'], 'period_end' => $data['period_end']], ['kwh' => $data['kwh'], 'amount' => $data['amount']]);

        return back()->with('success', 'Bill saved.');
    }

    public function forecast(Request $request, Building $building, ForecastService $service)
    {
        $building = $this->building($request, $building);
        $this->access($request);
        $run = $service->generate($building);

        return back()->with($run->status === 'complete' ? 'success' : 'error', $run->status === 'complete' ? 'Seven-day forecast updated.' : 'The forecast could not be generated. Historical data is still available; please retry later.');
    }

    public function team(Request $request)
    {
        $organization = $this->organization($request);
        $members = $organization->users()->get();
        $isOwner = $organization->users()->where('users.id', $request->user()->id)->wherePivot('role', 'owner')->exists();
        $invitations = Invitation::where('organization_id', $organization->id)->whereNull('accepted_at')->where('expires_at', '>', now())->get();

        return view('dashboard.team', compact('organization', 'members', 'invitations', 'isOwner'));
    }

    public function invite(Request $request)
    {
        $organization = $this->organization($request);
        abort_unless($organization->users()->where('users.id', $request->user()->id)->wherePivot('role', 'owner')->exists(), 403);
        $data = $request->validate(['email' => 'required|email|max:255']);
        if ($organization->users()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'This person is already a member.']);
        }
        $token = Str::random(64);
        Invitation::updateOrCreate(['organization_id' => $organization->id, 'email' => $data['email']], ['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'accepted_at' => null]);
        $url = route('invitation.accept', ['token' => $token]);
        Mail::raw("You have been invited to {$organization->name} on Caldren. Join within seven days: $url", fn ($mail) => $mail->to($data['email'])->subject('Join your Caldren workspace'));

        return back()->with('success', 'Invitation sent.');
    }

    public function switchOrganization(Request $request)
    {
        $data = $request->validate(['organization_id' => 'required|integer']);
        abort_unless($request->user()->organizations()->where('organizations.id', $data['organization_id'])->exists(), 403);
        $request->session()->put('organization_id', (int) $data['organization_id']);

        return redirect()->route('dashboard');
    }

    public function billing(Request $request)
    {
        $organization = $this->organization($request);
        $isOwner = $organization->users()->where('users.id', $request->user()->id)->wherePivot('role', 'owner')->exists();
        $payments = DB::table('demo_payments')->where('organization_id', $organization->id)->latest()->get();
        $plans = config('plans');
        $requestedPlan = $request->query('plan');
        $selectedPlanKey = is_string($requestedPlan) && isset($plans[$requestedPlan]) ? $requestedPlan : $organization->subscription?->plan_key;

        return view('dashboard.billing', compact('organization', 'payments', 'isOwner', 'plans', 'selectedPlanKey'));
    }

    public function demoCheckout(Request $request)
    {
        $organization = $this->organization($request);
        abort_unless($organization->users()->where('users.id', $request->user()->id)->wherePivot('role', 'owner')->exists(), 403);
        $data = $request->validate([
            'demo_card' => 'required|string',
            'plan_key' => ['required', 'string', 'in:'.implode(',', array_keys(config('plans')))],
        ]);
        if (preg_replace('/\s+/', '', $data['demo_card']) !== '4242424242424242') {
            throw ValidationException::withMessages(['demo_card' => 'Use the displayed 4242 demo card number. No real cards are accepted.']);
        }
        $plan = config('plans.'.$data['plan_key']);
        DB::transaction(function () use ($organization, $data, $plan) {
            $start = collect([$organization->trial_ends_at, $organization->subscription?->current_period_ends_at, now()])->filter()->sort()->last();
            $organization->subscription()->updateOrCreate(['organization_id' => $organization->id], ['plan_key' => $data['plan_key'], 'status' => 'active', 'monthly_price_cents' => $plan['monthly_price_cents'], 'current_period_ends_at' => $start->copy()->addMonth()]);
            DB::table('demo_payments')->insert(['organization_id' => $organization->id, 'amount_cents' => $plan['monthly_price_cents'], 'currency' => 'USD', 'status' => 'simulated', 'demo_reference' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('success', 'Demo payment simulated for '.$plan['name'].'. Access is active for one month; no money was charged.');
    }
}
