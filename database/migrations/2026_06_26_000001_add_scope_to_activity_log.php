<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('user_name');
            $table->unsignedBigInteger('competition_id')->nullable()->after('team_id');
            $table->index(['team_id', 'competition_id']);
        });
    }
    public function down(): void {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'competition_id']);
            $table->dropColumn(['team_id', 'competition_id']);
        });
    }
};
