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
        Schema::table('certificate_templates', function (Blueprint $table) {
            // Eliminar campos antiguos de posicionamiento que ya no se usan
            if (Schema::hasColumn('certificate_templates', 'coordinates_x')) {
                $table->dropColumn('coordinates_x');
            }
            if (Schema::hasColumn('certificate_templates', 'coordinates_y')) {
                $table->dropColumn('coordinates_y');
            }
            if (Schema::hasColumn('certificate_templates', 'qr_position')) {
                $table->dropColumn('qr_position');
            }
            if (Schema::hasColumn('certificate_templates', 'name_position')) {
                $table->dropColumn('name_position');
            }
            if (Schema::hasColumn('certificate_templates', 'background_image_size')) {
                $table->dropColumn('background_image_size');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            // En caso de rollback, restaurar campos (aunque no se recomienda)
            $table->integer('coordinates_x')->nullable()->after('status');
            $table->integer('coordinates_y')->nullable()->after('coordinates_x');
            $table->json('qr_position')->nullable()->after('coordinates_y');
            $table->json('name_position')->nullable()->after('qr_position');
            $table->json('background_image_size')->nullable()->after('name_position');
        });
    }
};