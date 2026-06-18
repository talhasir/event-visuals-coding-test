<?php

use App\Mail\EventReminder;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function eventStartingIn(int $seconds): Event
{
    return Event::factory()->for(User::factory())->create(['created_time' => time() + $seconds]);
}

it('sends a 24-hour reminder once and never resends it', function () {
    Mail::fake();
    $event = eventStartingIn(12 * 3600); // inside the 24h window
    $attendee = Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertQueued(EventReminder::class, 1);
    expect($attendee->refresh()->reminder_24h_sent_at)->not->toBeNull();

    // A second run must not resend.
    Mail::fake();
    $this->artisan('events:send-reminders')->assertSuccessful();
    Mail::assertNothingQueued();
});

it('sends a 3-day reminder for events in the 24h–72h window', function () {
    Mail::fake();
    $event = eventStartingIn(48 * 3600); // 2 days out → 3-day window
    $attendee = Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertQueued(EventReminder::class, fn ($mail) => $mail->window === '3 days');
    expect($attendee->refresh()->reminder_72h_sent_at)->not->toBeNull();
    expect($attendee->reminder_24h_sent_at)->toBeNull();
});

it('does not remind for events outside the reminder windows', function () {
    Mail::fake();
    $event = eventStartingIn(10 * 24 * 3600); // 10 days out
    Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertNothingQueued();
});
