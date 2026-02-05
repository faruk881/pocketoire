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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_commissioned')->default(false)->index();
            $table->string('product_code')->nullable();
            $table->string('booking_ref')->index();
            $table->string('transaction_ref')->unique();
            $table->string('event_type',50)->index(); //'CONFIRMATION', 'REJECTION', 'AMENDMENT','CANCELLATION','CUSTOMER_CANCELLATION
            $table->string('campaign_value')->nullable(); // keep raw value for reference
            $table->date('travel_date')->nullable();
            $table->string('travel_time')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('platform_commission', 10, 2)->nullable();
            $table->decimal('creator_commission', 10, 2)->nullable();
            $table->integer('creator_commission_percent')->nullable();
            $table->string('currency')->default('USD');
            $table->enum('status', ['confirmed','rejected','amended'])->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
