<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('layouts', function (Blueprint $table) {
            $table->id();
            $table->string('race_id')->unique();
            $table->unsignedBigInteger('drummer_id')->nullable();
            $table->unsignedBigInteger('helm_id')->nullable();
            $table->json('left_seats');
            $table->json('right_seats');
            $table->json('reserves');
            $table->timestamps();
            $table->foreign('race_id')->references('id')->on('races')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('layouts'); }
};
