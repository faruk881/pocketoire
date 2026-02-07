<?php

namespace App\Http\Controllers;

use App\Services\RefreshViatorDestinations;
use Illuminate\Http\Request;

class ViatorDestinationsController extends Controller
{
    public function refreshDestinations(RefreshViatorDestinations $viatorService){
        // This might take 5-10 seconds, so you might want to increase timeout
        set_time_limit(120); 

        $count = $viatorService->syncDestinations();

        if ($count === false) {
            return back()->with('error', 'Failed to connect to Viator API.');
        }

        return apiSuccess("Successfully synced {$count} destinations!");
    }
}
