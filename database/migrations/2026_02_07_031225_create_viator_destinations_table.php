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
        Schema::create('viator_destinations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('destination_id')->unique(); // Viator's ID
            $table->string('name');
            $table->string('type')->nullable(); // e.g., CITY, COUNTRY, REGION
            $table->string('default_currency_code')->nullable();
            $table->string('time_zone')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viator_destinations');
    }
};
