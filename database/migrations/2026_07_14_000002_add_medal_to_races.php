<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('races', function (Blueprint $table) {
            // Result medal for this crew/race: gold | silver | bronze | null (no medal).
            $table->string('medal', 10)->nullable()->after('schedule');
        });
    }

    public function down(): void {
        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn('medal');
        });
    }
};
