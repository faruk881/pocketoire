<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use App\Models\Album;
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
            $data['storefront_id'] = auth()->user()->storefront->id;

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

    public function getAlbums(){
        try{
            $storefront_id = auth()->user()->storefront->id;
            $albums = Album::where('storefront_id', $storefront_id)->get(['id','name','slug','description']);

            return apiSuccess('Albums retrieved successfully.', $albums);

        } catch(\Throwable $e){
            return apiError($e->getMessage());
        }
    
    }

    public function updateAlbum(UpdateAlbumRequest $request, $id) {
        try {
            $album = Album::findOrFail($id);

            if ($album->storefront_id !== auth()->user()->storefront->id) {
                return apiError('You are not authorized to update this album.', 403);
            }

            $data = $request->validated();
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug;
            $counter = 1;

            while (Album::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $data['slug'] = $slug;
            $album->update($data);

            return apiSuccess('Album updated successfully.', $album);


        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
    }
}
