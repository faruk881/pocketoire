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
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('booking_ref')->index();
            $table->string('transaction_ref')->unique();
            $table->string('event_type',50)->index(); //'CONFIRMATION', 'REJECTION', 'AMENDMENT','CANCELLATION','CUSTOMER_CANCELLATION
            $table->string('campaign_value')->nullable(); // keep raw value for reference
            $table->date('travel_date')->nullable();
            $table->string('travel_time')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('affiliate_comission', 10, 2)->nullable();
            $table->decimal('creator_comission', 10, 2)->nullable();
            $table->integer('creator_comission_percent')->nullable();
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
