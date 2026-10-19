<?php

namespace App\Livewire;

use App\Services\OrganizationContext;
use Illuminate\View\View;
use Livewire\Component;

class BuildingDirectory extends Component
{
    public string $search = '';

    public function render(): View
    {
        $organization = app(OrganizationContext::class)->current(request());
        $buildings = $organization->buildings()->withCount(['readings', 'bills'])->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes(mb_substr($this->search, 0, 80), '%_\\').'%'))->orderBy('name')->get();

        return view('livewire.building-directory', compact('buildings'));
    }
}
