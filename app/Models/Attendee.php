<?php

namespace App\Models;

use Database\Factories\AttendeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendee extends Model
{
    /** @use HasFactory<AttendeeFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'email',
        'reminder_72h_sent_at',
        'reminder_24h_sent_at',
    ];

    protected $casts = [
        'reminder_72h_sent_at' => 'datetime',
        'reminder_24h_sent_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
