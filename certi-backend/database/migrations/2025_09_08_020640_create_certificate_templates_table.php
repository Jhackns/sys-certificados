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
        // Verificamos si la tabla ya existe para evitar errores
        if (!Schema::hasTable('certificate_templates')) {
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

                // Campos para almacenar las coordenadas y configuración del código QR
                $table->json('qr_position')->nullable()->comment('Posición y configuración del código QR (x, y, width, height)');

                // Campos para almacenar las coordenadas y configuración del nombre del usuario
                $table->json('name_position')->nullable()->comment('Posición y configuración del texto del nombre (x, y, width, height, font, size)');

                // Campo para almacenar el tamaño original de la imagen de fondo
                $table->json('background_image_size')->nullable()->comment('Tamaño original de la imagen de fondo (width, height)');

                // Campo para la integración con Canva
                $table->string('canva_design_id')->nullable()->comment('ID del diseño en Canva para generación automática');

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
