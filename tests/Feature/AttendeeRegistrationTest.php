<?php

use App\Mail\AttendeeConfirmation;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function makeAttendeeEvent(array $attributes = []): Event
{
    return Event::factory()->for(User::factory())->create($attributes);
}

it('registers an attendee and emails them a confirmation', function () {
    Mail::fake();
    $event = makeAttendeeEvent();

    $this->post(route('events.attendees.store', $event), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('attendees', [
        'event_id' => $event->id,
        'email' => 'ada@example.com',
    ]);

    Mail::assertQueued(AttendeeConfirmation::class, fn ($mail) => $mail->hasTo('ada@example.com'));
});

it('rejects a duplicate registration for the same event', function () {
    Mail::fake();
    $event = makeAttendeeEvent();
    Attendee::factory()->for($event)->create(['email' => 'ada@example.com']);

    $this->from(route('events.show', $event))
        ->post(route('events.attendees.store', $event), [
            'name' => 'Ada Again',
            'email' => 'ada@example.com',
        ])
        ->assertSessionHasErrors('email');

    expect(Attendee::where('event_id', $event->id)->count())->toBe(1);
    Mail::assertNothingQueued();
});

it('validates the registration input', function () {
    $event = makeAttendeeEvent();

    $this->post(route('events.attendees.store', $event), ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email']);
});
