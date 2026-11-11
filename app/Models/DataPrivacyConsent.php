<?php

namespace App\Models;

use App\Enums\DataPrivacyConsentAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataPrivacyConsent extends Model
{
    protected $fillable = [
        'user_id',
        'notice_version',
        'action',
        'consented_at',
        'ip_address',
        'user_agent',
        'notice_text_hash',
    ];

    protected function casts(): array
    {
        return [
            'action' => DataPrivacyConsentAction::class,
            'consented_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
