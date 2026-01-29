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
        Schema::create('viator_sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('service')->default('viator')->unique();
            $table->string('cursor')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viator_sync_states');
    }
};
