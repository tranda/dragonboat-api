<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pdf_tokens', function (Blueprint $table) {
            $table->string('token', 64)->primary();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('expires_at');
        });
    }
    public function down(): void { Schema::dropIfExists('pdf_tokens'); }
};
