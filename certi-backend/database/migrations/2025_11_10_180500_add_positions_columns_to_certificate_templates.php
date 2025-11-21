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
            if (!Schema::hasColumn('certificate_templates', 'name_position')) {
                $table->json('name_position')->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('certificate_templates', 'qr_position')) {
                $table->json('qr_position')->nullable()->after('name_position');
            }
            if (!Schema::hasColumn('certificate_templates', 'background_image_size')) {
                $table->json('background_image_size')->nullable()->after('qr_position');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            if (Schema::hasColumn('certificate_templates', 'background_image_size')) {
                $table->dropColumn('background_image_size');
            }
            if (Schema::hasColumn('certificate_templates', 'qr_position')) {
                $table->dropColumn('qr_position');
            }
            if (Schema::hasColumn('certificate_templates', 'name_position')) {
                $table->dropColumn('name_position');
            }
        });
    }
};
