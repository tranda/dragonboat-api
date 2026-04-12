<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('competition_athlete', function (Blueprint $table) {
            $table->unsignedBigInteger('competition_id');
            $table->unsignedBigInteger('athlete_id');
            $table->primary(['competition_id', 'athlete_id']);
            $table->foreign('competition_id')->references('id')->on('competitions')->onDelete('cascade');
            $table->foreign('athlete_id')->references('id')->on('athletes')->onDelete('cascade');
        });

        // Register all existing athletes for all existing competitions
        $competitions = DB::table('competitions')->pluck('id');
        $athletes = DB::table('athletes')->pluck('id');
        foreach ($competitions as $compId) {
            foreach ($athletes as $athleteId) {
                DB::table('competition_athlete')->insert([
                    'competition_id' => $compId,
                    'athlete_id' => $athleteId,
                ]);
            }
        }
    }
    public function down(): void { Schema::dropIfExists('competition_athlete'); }
};
