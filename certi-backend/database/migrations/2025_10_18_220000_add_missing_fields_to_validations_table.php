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
        Schema::table('validations', function (Blueprint $table) {
            if (!Schema::hasColumn('validations', 'validation_code')) {
                $table->string('validation_code')->unique()->after('id');
            }
            if (!Schema::hasColumn('validations', 'validator_ip')) {
                $table->string('validator_ip')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('validations', 'validator_user_agent')) {
                $table->string('validator_user_agent')->nullable()->after('validator_ip');
            }
            // No renombramos validated_at para evitar dependencia de doctrine/dbal y romper código existente
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validations', function (Blueprint $table) {
            if (Schema::hasColumn('validations', 'validation_code')) {
                $table->dropColumn('validation_code');
            }
            if (Schema::hasColumn('validations', 'validator_ip')) {
                $table->dropColumn('validator_ip');
            }
            if (Schema::hasColumn('validations', 'validator_user_agent')) {
                $table->dropColumn('validator_user_agent');
            }
            // No revertimos rename ya que no se realizó
        });
    }
};
