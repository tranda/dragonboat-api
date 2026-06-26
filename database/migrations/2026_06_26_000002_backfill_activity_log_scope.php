<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // race + layout entries: entity_id is the race id → derive both scopes.
        foreach (DB::table('races')->select('id', 'team_id', 'competition_id')->get() as $race) {
            DB::table('activity_log')
                ->whereIn('entity_type', ['race', 'layout'])
                ->where('entity_id', (string) $race->id)
                ->whereNull('team_id')
                ->update(['team_id' => $race->team_id, 'competition_id' => $race->competition_id]);
        }

        // athlete entries have no entity_id, so match on entity_name → team.
        // Only backfill when the name resolves to a single team (avoid guessing).
        $byName = DB::table('athletes')->select('name', 'team_id')->get()->groupBy('name');
        foreach ($byName as $name => $rows) {
            $teamIds = $rows->pluck('team_id')->unique();
            if ($teamIds->count() !== 1) continue;
            DB::table('activity_log')
                ->where('entity_type', 'athlete')
                ->where('entity_name', $name)
                ->whereNull('team_id')
                ->update(['team_id' => $teamIds->first()]);
        }
    }

    public function down(): void {
        // Data backfill — nothing to reverse.
    }
};
