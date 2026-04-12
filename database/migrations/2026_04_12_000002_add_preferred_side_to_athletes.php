<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('athletes', function (Blueprint $table) {
            $table->enum('preferred_side', ['left', 'right', 'both'])->nullable()->after('is_bcp');
        });
    }
    public function down(): void {
        Schema::table('athletes', function (Blueprint $table) {
            $table->dropColumn('preferred_side');
        });
    }
};
