<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // When true, the helm seat may be any gender even in a Women's race.
    // Organizer discretion; default false.
    public function up(): void {
        Schema::table('competitions', function (Blueprint $table) {
            $table->boolean('helm_any_gender')->default(false);
        });
    }

    public function down(): void {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('helm_any_gender');
        });
    }
};
