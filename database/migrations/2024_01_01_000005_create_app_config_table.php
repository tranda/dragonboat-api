<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('app_config', function (Blueprint $table) {
            $table->id();
            $table->integer('competition_year')->default(2026);
            $table->json('gender_policy');
            $table->json('age_rules');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('app_config'); }
};
