<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('multipersona.table_name', 'personas');

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'active_until')) {
                $table->timestamp('active_until')->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'revert_to_persona_id')) {
                $table->unsignedBigInteger('revert_to_persona_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        $tableName = config('multipersona.table_name', 'personas');

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'active_until')) {
                $table->dropIndex(['active_until']);
                $table->dropColumn('active_until');
            }

            if (Schema::hasColumn($tableName, 'revert_to_persona_id')) {
                $table->dropColumn('revert_to_persona_id');
            }
        });
    }
};
