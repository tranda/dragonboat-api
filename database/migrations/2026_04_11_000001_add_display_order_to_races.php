<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('races', function (Blueprint $table) {
            $table->integer('display_order')->default(0)->after('category');
        });

        // Seed existing races with an initial order based on created_at
        $races = DB::table('races')->orderBy('created_at')->get();
        foreach ($races as $i => $race) {
            DB::table('races')->where('id', $race->id)->update(['display_order' => $i]);
        }
    }

    public function down(): void {
        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
    }
};
