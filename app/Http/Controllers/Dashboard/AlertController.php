<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\OperationalAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertController extends DashboardController
{
    public function index(Request $request)
    {
        $organization = $this->organization($request);
        $filters = $request->validate(['status' => ['nullable', Rule::in(OperationalAlert::STATUSES)], 'severity' => 'nullable|in:high,medium,low', 'type' => 'nullable|string|max:40', 'building_id' => 'nullable|integer']);
        $query = $organization->alerts()->with(['building', 'acknowledgedBy', 'resolvedBy'])->latest('detected_at');
        foreach (['status', 'severity', 'type', 'building_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }
        $alerts = $query->get();
        $buildings = $organization->buildings()->orderBy('name')->get();

        return view('dashboard.alerts', compact('organization', 'alerts', 'buildings', 'filters'));
    }

    public function update(Request $request, OperationalAlert $alert): RedirectResponse
    {
        $alert = $this->alert($request, $alert);
        $data = $request->validate(['status' => 'required|in:acknowledged,resolved']);
        if ($data['status'] === 'acknowledged') {
            $alert->update(['status' => 'acknowledged', 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now(), 'resolved_by' => null, 'resolved_at' => null]);
        } else {
            $alert->update(['status' => 'resolved', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        }

        return back()->with('success', $data['status'] === 'resolved' ? 'Alert resolved.' : 'Alert acknowledged.');
    }
}
