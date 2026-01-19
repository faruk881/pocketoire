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
            $table->string('slug')->unique();
            $table->enum('status', ['pending', 'approved', 'rejected', 'banned'])->default('pending');
            $table->text('status_reason')->nullable(); // If storefront is banned or rejected the message will store here
            $table->text('bio')->nullable();
            
            $table->string('instagram_link')->nullable();
            $table->string('tiktok_link')->nullable();

            // Track who moderated (Example: who approved, rejected.)
            $table->foreignId('moderated_by')->nullable()->constrained('users');        
            $table->timestamp('moderated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
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
