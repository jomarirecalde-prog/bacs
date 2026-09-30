<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelOrderDestination extends Model
{
    protected $fillable = [
        'travel_order_id',
        'destination',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function travelOrder(): BelongsTo
    {
        return $this->belongsTo(TravelOrder::class);
    }
}
