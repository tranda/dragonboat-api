<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Max number of younger (adjacent-band) paddlers allowed per crew in an
    // older age category. Organizer discretion; default 0 (none) unless enabled.
    public function up(): void {
        Schema::table('competitions', function (Blueprint $table) {
            $table->integer('younger_allowance')->default(0);
        });
    }

    public function down(): void {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('younger_allowance');
        });
    }
};
