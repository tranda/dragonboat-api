<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('races', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->enum('boat_type', ['standard', 'small']);
            $table->integer('num_rows');
            $table->string('distance', 50)->nullable();
            $table->enum('gender_category', ['Open', 'Women', 'Mixed']);
            $table->string('age_category', 50);
            $table->string('category')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('races'); }
};
