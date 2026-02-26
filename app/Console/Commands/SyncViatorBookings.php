<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\ViatorSyncState;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncViatorBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'viator:sync-sales';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync product sales from Viator using cursor-based pagination';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $this->info('Starting Viator sales sync...');
        Log::info('Start Viator sales sync: ' . Carbon::now());

        // Load last cursor from DB
        $cursor = ViatorSyncState::first()?->cursor;

        do {
            $params = ['count' => 50];
            if ($cursor) $params['cursor'] = $cursor;

            $response = Http::withHeaders([
                'Accept' => 'application/json;version=2.0',
                'Accept-Language' => 'en-US',
                'exp-api-key' => config('services.viator.viator_api_key'),
            ])->get(config('services.viator.viator_api_base_url') . '/partner/bookings/modified-since', $params);

            if ($response->failed()) {
                $this->error('Viator API request failed: ' . $response->status());
                Log::error('Viator API request failed', ['body' => $response->body()]);
                return Command::FAILURE;
            }

            $data = $response->json();
            $bookings = $data['bookings'] ?? [];

            foreach ($bookings as $booking) {
                // Find the product by Viator product code
                $product = Product::where('viator_product_code', $booking['bookedItem']['productCode'])->first();
                $product_id = $product ? $product->id : null;

                // Extract creator_id from campaign_value (e.g., "creator_2")
                $creatorId = null;
                if (!empty($booking['campaignValue'])) {
                    if (preg_match('/creator_(\d+)/', $booking['campaignValue'], $matches)) {
                        $creatorId = (int)$matches[1];

                        if (!User::where('id', $creatorId)->exists()) {
                            $creatorId = null;
                        }
                    }
                }

                // Insert or update the product sale
                Sale::updateOrCreate(
                    ['transaction_ref' => $booking['transactionRef']],
                    [
                        'product_id' => $product_id,
                        'user_id' => $creatorId,
                        'product_code' => $booking['bookedItem']['productCode'],
                        'booking_ref' => $booking['bookingRef'],
                        'event_type' => $booking['eventType'],
                        'campaign_value' => $booking['campaignValue'] ?? null,
                        'travel_date' => $booking['bookedItem']['travelDate'] ?? null,
                        'travel_time' => $booking['bookedItem']['travelTime'] ?? null,
                        'status' => $this->mapStatus($booking['eventType']),
                        'raw_payload' => json_encode($booking), // Ensure JSON string
                    ]
                );
            }

            // Save cursor only after successful processing
            $cursor = $data['nextCursor'] ?? null;
            if ($cursor) {
                ViatorSyncState::updateOrCreate(
                    ['service' => 'viator'],
                    ['cursor' => $cursor, 'last_success_at' => Carbon::now()]
                );
            }

        } while ($cursor);

        Log::info('Finished Viator sales sync: ' . Carbon::now());
        $this->info('Viator sales sync completed successfully.');
        return Command::SUCCESS;
    }

    /**
     * Map Viator event type to internal status
     */
    private function mapStatus($eventType)
    {
        return match($eventType) {
            'CONFIRMATION' => 'confirmed',
            'REJECTION' => 'rejected',
            'AMENDMENT' => 'amended',
            default => 'confirmed',
        };
    }
}
