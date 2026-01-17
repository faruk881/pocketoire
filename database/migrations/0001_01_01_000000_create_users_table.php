<?php

use Illuminate\Database\Console\Migrations\StatusCommand;
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            
            // OTP
            $table->string('otp')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamp('otp_expires_at')->nullable();

            $table->string('password');

            $table->string('profile_photo')->nullable();
            $table->string('cover_photo')->nullable();

            $table->enum('account_type',['buyer','creator','admin'])->default('buyer');
            $table->enum('status', ['active', 'suspended', 'banned'])->default('active');
            $table->string('status_reason')->nullable(); // If storefront is banned or rejected the message will store here
            
            

            // Stripe info
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_account_id')->nullable();
            $table->boolean('stripe_onboarded')->default(false);

            // Moderation
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();

            // For user with google.
            $table->string('google_id')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
