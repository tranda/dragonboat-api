<?php
namespace App\Console\Commands;

use App\Models\Race;
use Illuminate\Console\Command;

// One-shot: set the Munich 2026 race schedule (stage + time) on each Serbia crew.
// Times are Munich local (CEST, +02:00). Repechage/Grand Final are "planned" slots —
// included even where Serbia's advancement is decided by results, so they count toward
// conflict detection. Crews are matched by id, then case-insensitive name; anything not
// found is skipped and reported. Run: php artisan races:seed-serbia-schedule
class SeedSerbiaSchedule extends Command
{
    protected $signature = 'races:seed-serbia-schedule';
    protected $description = 'Set Munich 2026 stage schedules on Serbia crews';

    public function handle(): int
    {
        // [id, name, schedule[ [stage, time(ISO8601 +02:00)] ]]
        $rows = [
            // Thu 9 Jul — 1000m (standard)
            ['ST_Senior_B_Mixed_1000m', 'ST Senior B Mixed 1000m', [
                ['stage' => 'Heat 2',      'time' => '2026-07-09T10:00:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-09T11:15:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-09T12:45:00+02:00'],
            ]],
            ['ST_Senior_A_Women_1000m', 'ST Senior A Women 1000m', [
                ['stage' => 'Round 1', 'time' => '2026-07-09T14:15:00+02:00'],
                ['stage' => 'Round 2', 'time' => '2026-07-09T15:15:00+02:00'],
            ]],
            ['ST_Senior_A_open_1000m', 'ST Senior A Open 1000m', [
                ['stage' => 'Round 1', 'time' => '2026-07-09T16:15:00+02:00'],
                ['stage' => 'Round 2', 'time' => '2026-07-09T17:00:00+02:00'],
            ]],

            // Fri 10 Jul — 200m (small)
            ['SM_Senior_B_Mixed_200m', 'SM Senior B Mixed 200m', [
                ['stage' => 'Heat 2',      'time' => '2026-07-10T09:25:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-10T10:15:00+02:00'],
                ['stage' => 'Repechage 2', 'time' => '2026-07-10T10:20:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-10T11:15:00+02:00'],
            ]],
            ['SM_Senior_A_Mixed_200m', 'SM Senior A Mixed 200m', [
                ['stage' => 'Heat 2',      'time' => '2026-07-10T09:45:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-10T10:30:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-10T11:25:00+02:00'],
            ]],
            ['SM_BCP_Open_200m', 'SM BCP Open 200m', [
                ['stage' => 'Round 1', 'time' => '2026-07-10T11:00:00+02:00'],
                ['stage' => 'Round 2', 'time' => '2026-07-10T11:50:00+02:00'],
            ]],
            ['SM_Senior_A_Women_200m', 'SM Senior A Women 200m', [
                ['stage' => 'Heat 2',      'time' => '2026-07-10T14:50:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-10T16:30:00+02:00'],
                ['stage' => 'Repechage 2', 'time' => '2026-07-10T16:35:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-10T17:25:00+02:00'],
            ]],
            ['SM_Senior_B_Women_200m', 'SM Senior B Women 200m', [
                ['stage' => 'Heat 2',      'time' => '2026-07-10T15:30:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-10T16:15:00+02:00'],
                ['stage' => 'Repechage 2', 'time' => '2026-07-10T16:20:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-10T17:15:00+02:00'],
            ]],
            ['SM_Senior_B_Open_200m', 'SM Senior B Open 200m', [
                ['stage' => 'Heat 2',      'time' => '2026-07-10T15:55:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-10T16:45:00+02:00'],
                ['stage' => 'Repechage 2', 'time' => '2026-07-10T16:50:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-10T17:30:00+02:00'],
            ]],

            // Sat 11 Jul — 500m (small)
            ['SM_Senior_B_Mixed_500m', 'SM Senior B Mixed 500m', [
                ['stage' => 'Heat 1',      'time' => '2026-07-11T09:20:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-11T10:15:00+02:00'],
                ['stage' => 'Repechage 2', 'time' => '2026-07-11T10:20:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-11T11:20:00+02:00'],
            ]],
            ['SM_Senior_A_Mixed_500m', 'SM Senior A Mixed 500m', [
                ['stage' => 'Heat 1',      'time' => '2026-07-11T09:40:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-11T10:30:00+02:00'],
                ['stage' => 'Repechage 2', 'time' => '2026-07-11T10:35:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-11T11:30:00+02:00'],
            ]],
            ['SM_BCP_Open_500m', 'SM BCP Open 500m', [
                ['stage' => 'Round 1', 'time' => '2026-07-11T11:05:00+02:00'],
                ['stage' => 'Round 2', 'time' => '2026-07-11T11:55:00+02:00'],
            ]],
            ['SM_Senior_A_Women_500m', 'SM Senior A Women 500m', [
                ['stage' => 'Heat 1',      'time' => '2026-07-11T14:45:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-11T16:25:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-11T17:15:00+02:00'],
            ]],
            ['SM_Senior_B_Women_500m', 'SM Senior B Women 500m', [
                ['stage' => 'Heat 2',      'time' => '2026-07-11T15:30:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-11T16:10:00+02:00'],
                ['stage' => 'Repechage 2', 'time' => '2026-07-11T16:15:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-11T17:05:00+02:00'],
            ]],
            ['SM_Senior_B_Open_500m', 'SM Senior B Open 500m', [
                ['stage' => 'Heat 2',      'time' => '2026-07-11T16:05:00+02:00'],
                ['stage' => 'Repechage 1', 'time' => '2026-07-11T16:40:00+02:00'],
                ['stage' => 'Repechage 2', 'time' => '2026-07-11T16:45:00+02:00'],
                ['stage' => 'Grand Final', 'time' => '2026-07-11T17:25:00+02:00'],
            ]],

            // Sun 12 Jul — 2000m (small) — direct staggered finals
            ['SM_Senior_A_Mixed_2000m', 'SM Senior A Mixed 2000m', [
                ['stage' => 'Final', 'time' => '2026-07-12T09:00:00+02:00'],
            ]],
            ['SM_Senior_B_Mixed_2000m', 'SM Senior B Mixed 2000m', [
                ['stage' => 'Final', 'time' => '2026-07-12T10:20:00+02:00'],
            ]],
            ['SM_Senior_A_Women_2000m', 'SM Senior A Women 2000m', [
                ['stage' => 'Final', 'time' => '2026-07-12T12:00:00+02:00'],
            ]],
            ['SM_Senior_B_Open_2000m', 'SM Senior B Open 2000m', [
                ['stage' => 'Final', 'time' => '2026-07-12T13:30:00+02:00'],
            ]],
            ['SM_Senior_B_Women_2000m', 'SM Senior B Women 2000m', [
                ['stage' => 'Final', 'time' => '2026-07-12T14:00:00+02:00'],
            ]],
            ['SM_BCP_Open_2000m', 'SM BCP Open 2000m', [
                ['stage' => 'Final', 'time' => '2026-07-12T14:00:00+02:00'],
            ]],
        ];

        $set = 0; $missing = [];
        foreach ($rows as [$id, $name, $schedule]) {
            $race = Race::find($id)
                ?? Race::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            if (!$race) { $missing[] = $name; continue; }
            $race->schedule = $schedule;
            $race->save();
            $set++;
            $this->line("  set {$race->name} (" . count($schedule) . " rounds)");
        }

        $this->info("Schedules set on {$set} crew(s).");
        if ($missing) {
            $this->warn('No crew found for: ' . implode(', ', $missing));
        }
        return self::SUCCESS;
    }
}
