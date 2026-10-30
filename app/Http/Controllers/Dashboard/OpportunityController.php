<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Building;
use App\Models\Opportunity;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpportunityController extends DashboardController
{
    public function index(Request $request)
    {
        $organization = $this->organization($request);
        $filters = $request->validate(['status' => ['nullable', Rule::in(Opportunity::STATUSES)], 'priority' => ['nullable', Rule::in(Opportunity::PRIORITIES)], 'building_id' => 'nullable|integer', 'assignee_id' => 'nullable|integer']);
        $query = $organization->opportunities()->with(['building', 'assignee', 'activities.user'])->latest();
        foreach (['status', 'priority', 'building_id', 'assignee_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }
        $opportunities = $query->get();
        $buildings = $organization->buildings()->orderBy('name')->get();
        $members = $organization->users()->orderBy('name')->get();

        return view('dashboard.opportunities', compact('organization', 'opportunities', 'buildings', 'members', 'filters'));
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate(['building_id' => 'nullable|integer', 'title' => 'required|string|max:255', 'description' => 'nullable|string|max:3000', 'priority' => ['required', Rule::in(Opportunity::PRIORITIES)], 'assignee_id' => 'nullable|integer', 'due_date' => 'nullable|date']);
        $this->validateWorkspaceReferences($organization->id, $data);
        $opportunity = $organization->opportunities()->create($data + ['source' => 'manual', 'status' => 'new']);
        $opportunity->activities()->create(['user_id' => $request->user()->id, 'action' => 'created']);

        return back()->with('success', 'Opportunity created.');
    }

    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $opportunity = $this->opportunity($request, $opportunity);
        $data = $request->validate(['status' => ['required', Rule::in(Opportunity::STATUSES)], 'priority' => ['required', Rule::in(Opportunity::PRIORITIES)], 'assignee_id' => 'nullable|integer', 'due_date' => 'nullable|date', 'note' => 'nullable|string|max:2000']);
        $this->validateWorkspaceReferences($opportunity->organization_id, $data);
        $fromStatus = $opportunity->status;
        $opportunity->update($data + ['completed_at' => $data['status'] === 'completed' ? now() : null, 'dismissed_at' => $data['status'] === 'dismissed' ? now() : null]);
        $opportunity->activities()->create(['user_id' => $request->user()->id, 'action' => $fromStatus === $data['status'] ? 'updated' : 'status_changed', 'from_status' => $fromStatus, 'to_status' => $data['status'], 'note' => $data['note'] ?? null]);

        return back()->with('success', 'Opportunity updated.');
    }

    /** @param array<string, mixed> $data */
    private function validateWorkspaceReferences(int $organizationId, array $data): void
    {
        if (! empty($data['building_id'])) {
            abort_unless(Building::query()->where('organization_id', $organizationId)->whereKey($data['building_id'])->exists(), 422);
        }
        if (! empty($data['assignee_id'])) {
            abort_unless(Organization::findOrFail($organizationId)->users()->where('users.id', $data['assignee_id'])->exists(), 422);
        }
    }
}
