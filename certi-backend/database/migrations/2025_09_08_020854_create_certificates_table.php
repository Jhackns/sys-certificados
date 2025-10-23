<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('activity_id')->constrained('activities')->onDelete('cascade');
            $table->foreignId('id_template')->constrained('certificate_templates')->onDelete('cascade');
            $table->foreignId('signed_by')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('unique_code')->unique();
            $table->text('qr_url')->nullable();

            // Campos de verificación y QR
            $table->string('verification_code')->nullable()->unique();
            $table->string('verification_token')->nullable();
            $table->text('verification_url')->nullable();
            $table->string('qr_image_path')->nullable();
            
            $table->string('final_image_path')->nullable();
            $table->json('validation_data')->nullable();
            $table->integer('verification_count')->default(0);
            $table->timestamp('last_verified_at')->nullable();

            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->timestamp('issued_at');
            $table->enum('status', ['active', 'revoked', 'expired', 'issued', 'pending', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};