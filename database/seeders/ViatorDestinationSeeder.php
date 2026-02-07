<?php

namespace Database\Seeders;

use App\Models\ViatorDestination;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class ViatorDestinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching Destinations from Viator API...');

        $baseUrl = config('services.viator.viator_api_base_url');
        $apiKey  = config('services.viator.viator_api_key');

        // 1. Call the API
        $response = Http::withHeaders([
            'Accept'          => 'application/json;version=2.0',
            'exp-api-key'     => $apiKey, // Ensure this is in your .env file
            'Content-Type'    => 'application/json',
            'Accept-Language' => 'en-US'
        ])->get($baseUrl.'/partner/destinations');

        if ($response->failed()) {
            $this->command->error('Failed to fetch destinations: ' . $response->body());
            return;
        }

        // 2. Decode the JSON response
        $data = $response->json();
        $destinations = $data['destinations'] ?? [];
        $total = count($destinations);

        $this->command->info("Found {$total} destinations. Saving to database...");

        // 3. Loop and Save
        // We use chunking to prevent memory issues if the list grows large in the future
        $chunks = array_chunk($destinations, 500);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $dest) {
                ViatorDestination::updateOrCreate(
                    // Check if this ID already exists
                    ['destination_id' => $dest['destinationId']], 
                    
                    // If found update, if not create with these values:
                    [
                        'name'                  => $dest['name'],
                        'type'                  => $dest['type'] ?? null,
                        'default_currency_code' => $dest['defaultCurrencyCode'] ?? null,
                        'time_zone'             => $dest['timeZone'] ?? null,
                    ]
                );
            }
        }

        $this->command->info('Destinations seeded successfully!');
    }
}
