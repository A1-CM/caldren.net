<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\OperationalAlert;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Services\OrganizationContext;
use Illuminate\Http\Request;

abstract class DashboardController extends Controller
{
    protected function organization(Request $request): Organization
    {
        return app(OrganizationContext::class)->current($request);
    }

    protected function building(Request $request, Building $building): Building
    {
        abort_unless($building->organization_id === $this->organization($request)->id, 404);

        return $building;
    }

    protected function opportunity(Request $request, Opportunity $opportunity): Opportunity
    {
        abort_unless($opportunity->organization_id === $this->organization($request)->id, 404);

        return $opportunity;
    }

    protected function alert(Request $request, OperationalAlert $alert): OperationalAlert
    {
        abort_unless($alert->organization_id === $this->organization($request)->id, 404);

        return $alert;
    }

    protected function isOwner(Request $request): bool
    {
        return $this->organization($request)->users()->where('users.id', $request->user()->id)->wherePivot('role', 'owner')->exists();
    }
}
