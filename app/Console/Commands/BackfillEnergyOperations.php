<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Services\DailyEnergySummaryService;
use App\Services\OperationalAlertService;
use App\Services\OpportunityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('caldren:backfill-energy-operations {--building= : Backfill one building ID}')]
#[Description('Build daily summaries, operational opportunities, and current data alerts from existing records')]
class BackfillEnergyOperations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DailyEnergySummaryService $summaries, OpportunityService $opportunities, OperationalAlertService $alerts): int
    {
        $query = Building::query()->with(['forecasts.recommendations']);
        if ($this->option('building') !== null) {
            $query->whereKey((int) $this->option('building'));
        }
        $processed = 0;
        $query->chunkById(50, function ($buildings) use ($summaries, $opportunities, $alerts, &$processed): void {
            foreach ($buildings as $building) {
                $summaries->rebuild($building);
                foreach ($building->forecasts->where('status', 'complete') as $forecast) {
                    $opportunities->createFromForecast($forecast);
                }
                $alerts->afterImport($building->fresh());
                $processed++;
                $this->line("Backfilled {$building->name}");
            }
        });
        $this->info("Energy operations backfill complete for {$processed} buildings.");

        return self::SUCCESS;
    }
}
