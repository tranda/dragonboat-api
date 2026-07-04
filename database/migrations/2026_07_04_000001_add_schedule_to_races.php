<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('races', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('category');
            $table->string('stage', 50)->nullable()->after('scheduled_at');
        });
    }

    public function down(): void {
        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'stage']);
        });
    }
};
