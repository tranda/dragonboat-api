<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('athletes', function (Blueprint $table) {
            // club.motion.rs membership number — the member's public ID (used as the
            // "ID" column in the achievements CSV export). Filled by the Club sync.
            $table->unsignedInteger('member_id')->nullable()->after('edbf_id')->index();
        });
    }
    public function down(): void {
        Schema::table('athletes', function (Blueprint $table) {
            $table->dropColumn('member_id');
        });
    }
};
