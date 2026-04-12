<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('athletes', function (Blueprint $table) {
            $table->boolean('is_helm')->default(false)->after('preferred_side');
            $table->boolean('is_drummer')->default(false)->after('is_helm');
            $table->string('edbf_id', 50)->nullable()->after('is_drummer');
        });
    }
    public function down(): void {
        Schema::table('athletes', function (Blueprint $table) {
            $table->dropColumn(['is_helm', 'is_drummer', 'edbf_id']);
        });
    }
};
