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
        Schema::create('storefronts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('name');
            $table->enum('status', ['pending', 'approved', 'rejected', 'banned'])->default('pending');
            $table->string('status_reason')->nullable(); // If storefront is banned or rejected the message will store here
            $table->text('bio')->nullable();

            // Auto generate or user can set custom url for storefronts
            $table->string('url')->unique();
            $table->string('instagram_link')->nullable();
            $table->string('tiktok_link')->nullable();

            // Track who moderated (Example: who approved, rejected.)
            $table->foreignId('moderated_by')->nullable()->constrained('users');        
            $table->timestamp('moderated_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storefronts');
    }
};
