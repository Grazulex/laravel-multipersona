<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('multipersona.table_name', 'personas'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('context')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('user_id');
            $table->string('user_type')->default('App\Models\User');
            $table->timestamps();

            $table->index(['user_id', 'user_type']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('multipersona.table_name', 'personas'));
    }
};
