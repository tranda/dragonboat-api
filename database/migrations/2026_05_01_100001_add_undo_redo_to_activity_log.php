<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('entity_id')->nullable()->after('entity_name');
            $table->json('before_state')->nullable()->after('details');
            $table->json('after_state')->nullable()->after('before_state');
            $table->boolean('is_undone')->default(false)->after('after_state');
            $table->index(['entity_type', 'entity_id', 'is_undone']);
        });
    }
    public function down(): void {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id', 'is_undone']);
            $table->dropColumn(['entity_id', 'before_state', 'after_state', 'is_undone']);
        });
    }
};
