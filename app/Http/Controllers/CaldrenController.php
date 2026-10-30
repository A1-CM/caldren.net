<?php

namespace App\Http\Controllers;

use App\Mail\ContactInquiry;
use App\Models\Building;
use App\Models\ForecastModelRelease;
use App\Models\Invitation;
use App\Models\ModelTrainingConsentEvent;
use App\Services\DailyEnergySummaryService;
use App\Services\ForecastReadinessService;
use App\Services\ForecastService;
use App\Services\OperationalAlertService;
use App\Services\OpportunityService;
use App\Services\OrganizationContext;
use App\Services\TurnstileVerifier;
use App\Services\UsageImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
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
            throw ValidationException::withMessages(['access' => 'Your trial has ended. Activate a plan to continue uploads and forecasting.']);
        }
    }

    public function contact(Request $request, TurnstileVerifier $turnstile): RedirectResponse
    {
        $turnstile->verify($request, 'default', route('home').'#contact');

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

    public function campusSalesInquiry(Request $request, TurnstileVerifier $turnstile): RedirectResponse
    {
        $turnstile->verify($request, 'sales', route('home').'#pricing');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'company' => 'required|string|max:160',
            'estimated_building_count' => 'required|integer|min:1|max:1000000',
            'operating_regions' => 'required|string|max:255',
            'available_energy_data' => 'required|in:monthly_bills,interval_readings,both,not_sure',
            'goals' => 'required|string|max:2000',
            'target_timeline' => 'required|in:exploring,within_3_months,within_6_months,later',
            'message' => 'nullable|string|max:4000',
        ]);

        if ($validator->fails()) {
            return redirect()->to(route('home').'#pricing')
                ->withErrors($validator, 'sales')
                ->withInput()
                ->with('open_sales_modal', true);
        }

        $data = $validator->validated();
        $data['estimated_building_count'] = (int) $data['estimated_building_count'];
        $inquiry = $data + ['inquiry_type' => 'campus_sales'];
        $inquiry['message'] = $inquiry['message'] ?? '';
        $leadId = DB::table('contact_leads')->insertGetId($inquiry + ['created_at' => now(), 'updated_at' => now()]);

        try {
            if (app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true)) {
                throw new RuntimeException('Contact mail transport is not configured.');
            }

            Mail::to(config('mail.contact_to'))->send(new ContactInquiry($inquiry, $leadId));
        } catch (\Throwable $error) {
            Log::error('Campus sales inquiry notification failed.', ['contact_lead_id' => $leadId, 'error' => $error->getMessage()]);

            return redirect()->to(route('home').'#pricing')
                ->with('open_sales_modal', true)
                ->with('sales_warning', 'Your inquiry was saved, but our email notification could not be sent. Please contact us directly.');
        }

        return redirect()->to(route('home').'#pricing')
            ->with('open_sales_modal', true)
            ->with('sales_success', 'Thanks. Your Campus inquiry has been received.');
    }

    public function dashboard(Request $request, ForecastReadinessService $readiness)
    {
        $organization = $this->organization($request);
        $buildings = $organization->buildings()->withCount(['readings', 'bills'])->with(['dailySummaries' => fn ($query) => $query->where('summary_date', '>=', now()->subDays(29))])->orderBy('name')->get();
        $recentForecasts = $organization->buildings()->with(['forecasts' => fn ($q) => $q->where('status', 'complete')->latest()->limit(1)])->get()->flatMap->forecasts;
        $energyUse = $buildings->flatMap->dailySummaries->sum('kwh');
        $estimatedCosts = $buildings->groupBy('currency')->map(fn ($group): float => round($group->sum(fn ($building): float => $building->dailySummaries->sum('kwh') * (float) $building->energy_rate), 2));
        $highestPeak = $buildings->flatMap->dailySummaries->max('peak_kw') ?? 0;
        $forecastReady = $buildings->filter(fn ($building): bool => $readiness->assess($building)['ready'])->count();
        $openAlerts = $organization->alerts()->whereIn('status', ['open', 'acknowledged'])->count();
        $activeOpportunities = $organization->opportunities()->whereNotIn('status', ['completed', 'dismissed'])->count();
        $healthyBuildings = $buildings->filter(fn ($building): bool => ($building->dailySummaries->sortByDesc('summary_date')->first()?->coverage_percent ?? 0) >= 75)->count();
        $topBuildings = $buildings->sortByDesc(fn ($building): float => (float) $building->dailySummaries->sum('kwh'))->take(5);

        return view('dashboard.index', compact('organization', 'buildings', 'recentForecasts', 'energyUse', 'estimatedCosts', 'highestPeak', 'forecastReady', 'openAlerts', 'activeOpportunities', 'healthyBuildings', 'topBuildings'));
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
        return $this->buildingWorkspace($request, $building, 'overview');
    }

    public function buildingData(Request $request, Building $building)
    {
        return $this->buildingWorkspace($request, $building, 'data');
    }

    public function buildingForecasts(Request $request, Building $building)
    {
        return $this->buildingWorkspace($request, $building, 'forecasts');
    }

    public function buildingOpportunities(Request $request, Building $building)
    {
        return $this->buildingWorkspace($request, $building, 'opportunities');
    }

    public function buildingSettings(Request $request, Building $building)
    {
        return $this->buildingWorkspace($request, $building, 'settings');
    }

    private function buildingWorkspace(Request $request, Building $building, string $activeTab)
    {
        $building = $this->building($request, $building);
        $organization = $this->organization($request);
        $isOwner = $organization->users()->where('users.id', $request->user()->id)->wherePivot('role', 'owner')->exists();
        $bills = $building->bills()->latest('period_start')->limit(12)->get();
        $billChart = ['labels' => $bills->reverse()->map(fn ($bill) => $bill->period_start->format('M Y'))->values()->all(), 'amounts' => $bills->reverse()->map(fn ($bill) => (float) $bill->amount)->values()->all()];
        $readings = $building->readings()->latest('recorded_at')->limit(500)->get()->reverse()->values();
        $forecast = $building->forecasts()->where('status', 'complete')->latest()->with(['points', 'recommendations'])->first();
        $latestRun = $building->forecasts()->latest()->first();
        $imports = $building->dataImports()->with('uploader')->latest()->limit(20)->get();
        $summaries = $building->dailySummaries()->latest('summary_date')->limit(30)->get();
        $opportunities = $building->opportunities()->with(['assignee', 'activities.user'])->latest()->get();
        $alerts = $building->alerts()->latest('detected_at')->limit(20)->get();

        return view('dashboard.building', compact('building', 'organization', 'bills', 'billChart', 'readings', 'forecast', 'latestRun', 'imports', 'summaries', 'opportunities', 'alerts', 'activeTab', 'isOwner'));
    }

    public function upload(Request $request, Building $building, UsageImporter $importer, DailyEnergySummaryService $summaries, OperationalAlertService $alerts)
    {
        $building = $this->building($request, $building);
        $this->access($request);
        $request->validate(['file' => 'required|file|max:5120|mimes:csv,txt']);
        $dataImport = $building->dataImports()->create(['user_id' => $request->user()->id, 'filename' => mb_substr($request->file('file')->getClientOriginalName(), 0, 255), 'status' => 'processing']);
        try {
            $result = $importer->import($building, $request->file('file'));
            $summaries->rebuild($building, $result['local_dates']);
            $dataImport->update(['status' => 'completed', 'row_count' => $result['row_count'], 'inserted_count' => $result['inserted_count'], 'updated_count' => $result['updated_count'], 'completed_at' => now()]);
            $alerts->afterImport($building->fresh());
        } catch (\Throwable $error) {
            $dataImport->update(['status' => 'failed', 'failure_reason' => mb_substr($error instanceof ValidationException ? collect($error->errors())->flatten()->first() : 'The file could not be processed.', 0, 500), 'completed_at' => now()]);
            throw $error;
        }

        return back()->with('success', "Imported {$result['row_count']} readings: {$result['inserted_count']} new and {$result['updated_count']} updated.");
    }

    public function bill(Request $request, Building $building, OperationalAlertService $alerts)
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
        $bill = $building->bills()->updateOrCreate(['period_start' => $data['period_start'], 'period_end' => $data['period_end']], ['kwh' => $data['kwh'], 'amount' => $data['amount']]);
        $alerts->afterBill($building, $bill);

        return back()->with('success', 'Bill saved.');
    }

    public function forecast(Request $request, Building $building, ForecastService $service, OpportunityService $opportunities, OperationalAlertService $alerts)
    {
        $building = $this->building($request, $building);
        $this->access($request);
        $run = $service->generate($building);
        if ($run->status === 'complete') {
            $opportunities->createFromForecast($run);
        }
        $alerts->afterForecast($building, $run);

        return back()->with($run->status === 'complete' ? 'success' : 'error', $run->status === 'complete' ? 'Seven-day forecast updated.' : 'The forecast could not be generated. Historical data is still available; please retry later.');
    }

    public function updateBuildingSettings(Request $request, Building $building): RedirectResponse
    {
        $building = $this->building($request, $building);
        $organization = $this->organization($request);
        abort_unless($organization->users()->where('users.id', $request->user()->id)->wherePivot('role', 'owner')->exists(), 403);
        $data = $request->validate([
            'name' => 'required|string|max:160', 'location' => 'nullable|string|max:160', 'timezone' => 'required|timezone', 'currency' => 'required|string|size:3',
            'energy_rate' => 'required|numeric|min:0|max:100000', 'peak_rate' => 'nullable|numeric|min:0|max:100000', 'peak_start_hour' => 'nullable|integer|between:0,23', 'peak_end_hour' => 'nullable|integer|between:0,23',
            'forecast_peak_threshold_percent' => 'required|numeric|min:0|max:500', 'bill_change_threshold_percent' => 'required|numeric|min:0|max:500', 'coverage_alert_threshold_percent' => 'required|numeric|min:1|max:100', 'stale_after_hours' => 'required|integer|min:1|max:8760', 'alerts_enabled' => 'nullable|boolean',
        ]);
        $data['currency'] = strtoupper($data['currency']);
        $data['alerts_enabled'] = $request->boolean('alerts_enabled');
        $building->update($data);

        return back()->with('success', 'Building settings updated.');
    }

    public function team(Request $request)
    {
        $organization = $this->organization($request);
        $members = $organization->users()->get();
        $isOwner = $organization->users()->where('users.id', $request->user()->id)->wherePivot('role', 'owner')->exists();
        $invitations = Invitation::where('organization_id', $organization->id)->whereNull('accepted_at')->where('expires_at', '>', now())->get();

        $modelTrainingOptedIn = $organization->hasOptedIntoModelTraining();

        return view('dashboard.team', compact('organization', 'members', 'invitations', 'isOwner', 'modelTrainingOptedIn'));
    }

    public function updateModelTrainingConsent(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless($organization->users()->where('users.id', $request->user()->id)->wherePivot('role', 'owner')->exists(), 403);

        $enabled = $request->boolean('enabled');
        if ($enabled) {
            $request->validate(['consent' => 'required|accepted']);
        }

        $consentVersion = (string) config('services.nvidia.training_consent_version', 'v1');
        DB::transaction(function () use ($organization, $request, $enabled, $consentVersion): void {
            $lockedOrganization = $organization->newQuery()->lockForUpdate()->findOrFail($organization->id);
            if ($enabled === $lockedOrganization->hasOptedIntoModelTraining()) {
                return;
            }

            if ($enabled) {
                $lockedOrganization->update([
                    'model_training_opt_in_at' => now(),
                    'model_training_consent_version' => $consentVersion,
                    'model_training_consented_by' => $request->user()->id,
                ]);
                ModelTrainingConsentEvent::create([
                    'organization_id' => $lockedOrganization->id,
                    'user_id' => $request->user()->id,
                    'status' => 'opted_in',
                    'consent_version' => $consentVersion,
                ]);

                return;
            }

            ModelTrainingConsentEvent::create([
                'organization_id' => $lockedOrganization->id,
                'user_id' => $request->user()->id,
                'status' => 'withdrawn',
                'consent_version' => $lockedOrganization->model_training_consent_version ?? $consentVersion,
            ]);
            $lockedOrganization->update([
                'model_training_opt_in_at' => null,
                'model_training_consented_by' => null,
            ]);

            foreach (ForecastModelRelease::query()->where('status', 'active')->lockForUpdate()->get() as $release) {
                $trainingOrganizationIds = array_map('intval', $release->training_organization_ids ?? []);
                if (in_array($lockedOrganization->id, $trainingOrganizationIds, true)) {
                    $release->update(['status' => 'withdrawn']);
                }
            }
        });

        return back()->with('success', $enabled
            ? 'This workspace is opted in to model training.'
            : 'Consent withdrawn. Caldren will continue using Groq forecasts while the GPU model path is unavailable.');
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
        $selectedPlanKey = is_string($requestedPlan) && ($plans[$requestedPlan]['checkout_enabled'] ?? false) ? $requestedPlan : ($organization->subscription?->plan_key ?? ($organization->trial_ends_at->isFuture() ? 'site' : null));
        $checkoutPlans = array_filter($plans, fn (array $plan): bool => $plan['checkout_enabled'] ?? false);

        return view('dashboard.billing', compact('organization', 'payments', 'isOwner', 'plans', 'checkoutPlans', 'selectedPlanKey'));
    }

    public function activatePlan(Request $request)
    {
        $organization = $this->organization($request);
        abort_unless($organization->users()->where('users.id', $request->user()->id)->wherePivot('role', 'owner')->exists(), 403);
        $data = $request->validate([
            'activation_code' => 'required|string',
            'plan_key' => ['required', 'string', 'in:'.implode(',', array_keys(array_filter(config('plans'), fn (array $plan): bool => $plan['checkout_enabled'] ?? false)))],
        ]);
        if (preg_replace('/\s+/', '', $data['activation_code']) !== '4242424242424242') {
            throw ValidationException::withMessages(['activation_code' => 'Use the displayed plan activation code.']);
        }
        $plan = config('plans.'.$data['plan_key']);
        DB::transaction(function () use ($organization, $data, $plan) {
            $start = collect([$organization->trial_ends_at, $organization->subscription?->current_period_ends_at, now()])->filter()->sort()->last();
            $organization->subscription()->updateOrCreate(['organization_id' => $organization->id], ['plan_key' => $data['plan_key'], 'status' => 'active', 'monthly_price_cents' => $plan['monthly_price_cents'], 'current_period_ends_at' => $start->copy()->addMonth()]);
            DB::table('demo_payments')->insert(['organization_id' => $organization->id, 'amount_cents' => $plan['monthly_price_cents'], 'currency' => 'USD', 'status' => 'activated', 'demo_reference' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('success', $plan['name'].' access is active for one month. No card details were collected and no payment was processed.');
    }
}
