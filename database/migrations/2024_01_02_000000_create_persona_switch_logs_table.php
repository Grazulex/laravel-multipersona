<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('multipersona.audit.table', 'persona_switch_logs'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('from_persona_id')->nullable();
            $table->string('from_persona_name')->nullable();
            $table->unsignedBigInteger('to_persona_id')->nullable();
            $table->string('to_persona_name');
            $table->json('context')->nullable();
            $table->timestamp('switched_at');

            $table->index(['user_id', 'switched_at']);
            $table->index('from_persona_id');
            $table->index('to_persona_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('multipersona.audit.table', 'persona_switch_logs'));
    }
};
