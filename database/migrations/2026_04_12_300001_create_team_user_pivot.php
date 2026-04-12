<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('team_user', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['team_id', 'user_id']);
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Migrate existing team_id assignments to pivot
        $users = DB::table('users')->whereNotNull('team_id')->get();
        foreach ($users as $user) {
            DB::table('team_user')->insert([
                'team_id' => $user->team_id,
                'user_id' => $user->id,
            ]);
        }
    }

    public function down(): void {
        Schema::dropIfExists('team_user');
    }
};
