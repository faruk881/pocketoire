<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $guarded = ['id'];
    
    public function product() {
        return $this->belongsTo(Product::class);
    }

    // 🔥 Override image attribute
    public function getImageAttribute($value)
    {
        if ($this->source === 'upload') {
            return Storage::disk('public')->url($value);
        }

        return $value; // viator already full URL
    }

    // 🔥 auto delete image file when record is deleted
    protected static function booted()
    {
        static::deleting(function ($image) {
            if ($image->image && Storage::disk('public')->exists($image->image)) {
                Storage::disk('public')->delete($image->image);
            }
        });
    }
}
