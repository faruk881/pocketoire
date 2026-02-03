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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type',['credit','debit','adjustment'])->index();
            $table->enum('source',['sale_commission','refund','adjustment','payout','payout_request','payout_failed'])->index();
            $table->decimal('amount', 12, 2)->unsigned();
            $table->decimal('balance_before', 12, 2)->unsigned();
            $table->decimal('balance_after', 12, 2)->unsigned();
            $table->enum('status',['pending','completed','failed'])->default('pending')->index();
            $table->string('reference')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
