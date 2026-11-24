<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'label', 'account_number', 'account_name', 'qris_image', 'is_active'])]
class PaymentMethod extends Model
{
    protected $appends = ['qris_image_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getQrisImageUrlAttribute(): ?string
    {
        return $this->qris_image ? '/storage/' . $this->qris_image : null;
    }
}
