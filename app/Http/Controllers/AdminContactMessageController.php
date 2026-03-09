<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

class AdminContactMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $message = ContactMessage::paginate($perPage);

        return apiSuccess('Contact messages are loaded',$message);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {

            $message = ContactMessage::where('id',$id)->first();
            
            if(!$message) {
                return apiError('message not found');
            }

            if($message->status === 'read') {
                return apiError('message already read');
            }

            $message = $message->update([
                'status' => 'read'
            ]);

            return apiSuccess('message updated', $message);
        }catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
