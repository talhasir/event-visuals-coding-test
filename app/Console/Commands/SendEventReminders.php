<?php

namespace App\Console\Commands;

use App\Mail\EventReminder;
use App\Models\Attendee;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Email attendees a reminder 3 days and 24 hours before their event.';

    private const HOUR = 3600;

    public function handle(): int
    {
        $now = (int) Carbon::now()->timestamp;

        // The 24h reminder takes precedence: an attendee who registers late (less
        // than a day out) should get the "24 hours" mail, never a mislabelled
        // "3 days" one. So the 3-day window is [now+24h, now+72h].
        $sent72 = $this->send(
            column: 'reminder_72h_sent_at',
            window: '3 days',
            from: $now + (24 * self::HOUR),
            to: $now + (72 * self::HOUR),
        );

        $sent24 = $this->send(
            column: 'reminder_24h_sent_at',
            window: '24 hours',
            from: $now,
            to: $now + (24 * self::HOUR),
        );

        $this->info("Sent {$sent72} three-day and {$sent24} twenty-four-hour reminders.");

        return self::SUCCESS;
    }

    /**
     * Send (and stamp) one reminder window. Idempotent: the stamp column means a
     * re-run never double-sends. Chunked so it holds up against a large attendee
     * table.
     */
    private function send(string $column, string $window, int $from, int $to): int
    {
        $count = 0;

        Attendee::query()
            ->whereNull($column)
            ->with('event')
            ->whereHas('event', fn ($q) => $q->whereBetween('created_time', [$from, $to]))
            ->chunkById(500, function ($attendees) use ($column, $window, &$count) {
                foreach ($attendees as $attendee) {
                    Mail::to($attendee->email)->send(new EventReminder($attendee, $window));
                    $attendee->forceFill([$column => now()])->save();
                    $count++;
                }
            });

        return $count;
    }
}
