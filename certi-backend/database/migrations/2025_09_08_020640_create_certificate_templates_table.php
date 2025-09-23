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
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('activity_type', ['course', 'event', 'other'])->default('other');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_active')->default(true);
            $table->json('coordinates_x')->nullable()->comment('X coordinates for text positioning (signature, QR, etc.)');
            $table->json('coordinates_y')->nullable()->comment('Y coordinates for text positioning (signature, QR, etc.)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
