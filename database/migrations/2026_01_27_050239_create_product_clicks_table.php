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
        Schema::create('product_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // If a registered user clicked
            $table->string('visitor_id')->nullable(); // If a visitor clicked
            $table->string('ip_address')->nullable(); // To track unique views
            $table->string('user_agent')->nullable(); // Browser info (Chrome/Mobile)
            $table->timestamps();
            $table->index(['product_id', 'visitor_id', 'created_at']); // this will handle about 20+ million rows.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_clicks');
    }
};
