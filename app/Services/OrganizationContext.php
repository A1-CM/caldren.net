<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationContext
{
    public function current(Request $request): Organization
    {
        $query = $request->user()->organizations();
        $id = $request->session()->get('organization_id');
        $organization = $id ? (clone $query)->where('organizations.id', $id)->first() : null;
        $organization ??= $query->firstOrFail();
        $organization->loadMissing('subscription');

        return $organization;
    }
}
