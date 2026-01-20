<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAlbumRequest;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AlbumController extends Controller
{
    public function createAlbum(CreateAlbumRequest $request) {

        try {
            $data = $request->validated();
            
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug;
            $counter = 1;

            while (Album::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $data['slug'] = $slug;
            $data['user_id'] = auth()->id();

            $album = Album::create($data);

            return apiSuccess('Album Created', $album, 201);

        } catch (\Throwable $e) {
            return apiError(
                'Failed to create album',
                500,
                ['exception' => $e->getMessage()]
            );
        }
            
    }
}
