<?php

namespace Database\Seeders;

use App\Models\PayoutThreshold;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PayoutThresholdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PayoutThreshold::updateOrCreate(
            [
                'minimum_amount' => env('MIN_PAYOUT_AMOUNT', 50),
                'maximum_amount' => env('MAX_PAYOUT_AMOUNT', 1000),
            ]
        );
    }
}
