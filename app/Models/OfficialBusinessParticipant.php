<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialBusinessParticipant extends Model
{
    protected $fillable = [
        'official_business_request_id',
        'employee_id',
        'participant_name',
        'participant_type',
        'sort_order',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessRequest::class, 'official_business_request_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
