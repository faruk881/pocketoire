<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Destination;
use App\Models\ViatorDestination;

class RefreshViatorDestinations
{
    public function syncDestinations()
    {
        $response = Http::withHeaders([
            'Accept'          => 'application/json;version=2.0',
            'exp-api-key'     => env('VIATOR_API_KEY'),
            'Content-Type'    => 'application/json',
            'Accept-Language' => 'en-US'
        ])->get('https://api.sandbox.viator.com/partner/destinations');

        if ($response->failed()) {
            return false;
        }

        $data = $response->json();
        $destinations = $data['destinations'] ?? [];

        foreach ($destinations as $dest) {
            ViatorDestination::updateOrCreate(
                ['destination_id' => $dest['destinationId']], 
                [
                    'name'                  => $dest['name'],
                    'type'                  => $dest['type'] ?? null,
                    'default_currency_code' => $dest['defaultCurrencyCode'] ?? null,
                    'time_zone'             => $dest['timeZone'] ?? null,
                ]
            );
        }

        return count($destinations);
    }
}