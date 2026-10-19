<?php

namespace App\Services;

use App\Models\Building;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UsageImporter
{
    public function import(Building $building, UploadedFile $file): int
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'Could not read the CSV file.']);
        }
        try {
            $header = fgetcsv($handle);
            if ($header) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            if ($header !== ['timestamp', 'kwh']) {
                throw ValidationException::withMessages(['file' => 'Use the exact CSV headers: timestamp,kwh.']);
            }
            $rows = [];
            $seen = [];
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if ($line > 5001) {
                    throw ValidationException::withMessages(['file' => 'CSV files may contain at most 5,000 readings.']);
                }
                if (count($values) !== 2) {
                    throw ValidationException::withMessages(['file' => "Invalid row $line. Use timestamp,kwh."]);
                }
                $rawTimestamp = trim($values[0]);
                try {
                    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $rawTimestamp)) {
                        $local = CarbonImmutable::createFromFormat('!Y-m-d H:i', $rawTimestamp, $building->timezone);
                        $valid = $local && $local->format('Y-m-d H:i') === $rawTimestamp;
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $rawTimestamp)) {
                        $local = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:sP', $rawTimestamp);
                        $valid = $local && $local->format('Y-m-d\TH:i:sP') === $rawTimestamp;
                    } else {
                        $valid = false;
                    }
                } catch (\Throwable) {
                    $valid = false;
                }
                if (! $valid) {
                    throw ValidationException::withMessages(['file' => "Invalid timestamp on row $line. Use YYYY-MM-DD HH:MM or ISO 8601 with an offset."]);
                }
                $kwh = trim($values[1]);
                if (! is_numeric($kwh) || (float) $kwh < 0 || (float) $kwh > 1000000) {
                    throw ValidationException::withMessages(['file' => "Invalid kWh on row $line."]);
                }
                $timestamp = $local->utc()->format('Y-m-d H:i:s');
                if (isset($seen[$timestamp])) {
                    throw ValidationException::withMessages(['file' => "Duplicate timestamp on row $line."]);
                }
                $seen[$timestamp] = true;
                $rows[] = ['building_id' => $building->id, 'recorded_at' => $timestamp, 'kwh' => $kwh, 'created_at' => now(), 'updated_at' => now()];
            }
            if (! $rows) {
                throw ValidationException::withMessages(['file' => 'The CSV has no readings.']);
            }
            DB::transaction(fn () => DB::table('usage_readings')->upsert($rows, ['building_id', 'recorded_at'], ['kwh', 'updated_at']));

            return count($rows);
        } finally {
            fclose($handle);
        }
    }
}
