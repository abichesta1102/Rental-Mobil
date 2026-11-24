<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_id', 'stage', 'path', 'note'])]
class DeliveryPhoto extends Model
{
    protected $appends = ['photo_url'];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->path ? '/storage/' . $this->path : null;
    }
}
