<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('athletes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('weight', 5, 2)->nullable();
            $table->enum('gender', ['M', 'F']);
            $table->integer('year_of_birth')->nullable();
            $table->boolean('is_bcp')->default(false);
            $table->boolean('is_removed')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('athletes'); }
};
