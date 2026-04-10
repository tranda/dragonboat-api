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
            $table->longText('left_seats');
            $table->longText('right_seats');
            $table->longText('reserves');
            $table->timestamps();
            $table->foreign('race_id')->references('id')->on('races')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('layouts'); }
};
