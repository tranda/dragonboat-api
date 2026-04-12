<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Add team_id to users
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('is_active');
        });

        // Add team_id to athletes
        Schema::table('athletes', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('is_removed');
        });

        // Add competition_id and team_id to races
        Schema::table('races', function (Blueprint $table) {
            $table->unsignedBigInteger('competition_id')->nullable()->after('display_order');
            $table->unsignedBigInteger('team_id')->nullable()->after('competition_id');
        });

        // Add competition_id and team_id to app_config
        Schema::table('app_config', function (Blueprint $table) {
            $table->unsignedBigInteger('competition_id')->nullable()->after('id');
            $table->unsignedBigInteger('team_id')->nullable()->after('competition_id');
        });

        // Seed: create first competition and team, assign all existing data
        $compId = DB::table('competitions')->insertGetId([
            'name' => 'Munich 2026',
            'year' => 2026,
            'location' => 'Munich',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teamId = DB::table('teams')->insertGetId([
            'name' => 'National Team Serbia',
            'country' => 'Serbia',
            'type' => 'national',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link team to competition
        DB::table('competition_team')->insert([
            'competition_id' => $compId,
            'team_id' => $teamId,
        ]);

        // Assign all existing data to this team and competition
        DB::table('users')->update(['team_id' => $teamId]);
        DB::table('athletes')->update(['team_id' => $teamId]);
        DB::table('races')->update(['competition_id' => $compId, 'team_id' => $teamId]);
        DB::table('app_config')->update(['competition_id' => $compId, 'team_id' => $teamId]);
    }

    public function down(): void {
        Schema::table('app_config', function (Blueprint $table) {
            $table->dropColumn(['competition_id', 'team_id']);
        });
        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn(['competition_id', 'team_id']);
        });
        Schema::table('athletes', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
    }
};
