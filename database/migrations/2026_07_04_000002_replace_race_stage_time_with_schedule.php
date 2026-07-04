<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// A crew races multiple rounds (Heat, Repechage, Final...), each at its own time.
// Replace the single stage/scheduled_at columns with a JSON list of { stage, time } entries.
return new class extends Migration {
    public function up(): void {
        Schema::table('races', function (Blueprint $table) {
            // Stored as JSON text; MariaDB here doesn't accept the native `json` type,
            // and the model casts `schedule` to array (same pattern as app_config).
            $table->longText('schedule')->nullable()->after('category');
        });

        // Backfill: fold any existing single stage/scheduled_at into a one-item schedule.
        foreach (DB::table('races')->whereNotNull('scheduled_at')->get() as $race) {
            DB::table('races')->where('id', $race->id)->update([
                'schedule' => json_encode([[
                    'stage' => $race->stage,
                    'time' => \Carbon\Carbon::parse($race->scheduled_at)->toIso8601String(),
                ]]),
            ]);
        }

        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'stage']);
        });
    }

    public function down(): void {
        Schema::table('races', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('category');
            $table->string('stage', 50)->nullable()->after('scheduled_at');
        });

        foreach (DB::table('races')->whereNotNull('schedule')->get() as $race) {
            $entries = json_decode($race->schedule, true) ?: [];
            if (!empty($entries[0])) {
                DB::table('races')->where('id', $race->id)->update([
                    'scheduled_at' => $entries[0]['time'] ?? null,
                    'stage' => $entries[0]['stage'] ?? null,
                ]);
            }
        }

        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn('schedule');
        });
    }
};
