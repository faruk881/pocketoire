<?php

namespace App\Http\Controllers\HomePage;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function store(ContactMessageRequest $request) {
        try {
            $data = $request->validated();

            // Create contact message
            $contactMessage = ContactMessage::create($data);

            return apiSuccess('Your message has been received. We will get back to you shortly.', [
                'data' => $contactMessage
            ], 201);
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
    }
}
