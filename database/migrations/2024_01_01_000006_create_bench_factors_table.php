<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bench_factors', function (Blueprint $table) {
            $table->id();
            $table->enum('boat_type', ['standard', 'small'])->unique();
            $table->json('factors');
        });
    }
    public function down(): void { Schema::dropIfExists('bench_factors'); }
};
