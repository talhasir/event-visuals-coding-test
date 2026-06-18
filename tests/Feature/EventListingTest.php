<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: create an event with sensible, overridable defaults. The factory
 * builds the full payload; we override the top-level columns the listing reads.
 */
function makeEvent(array $attributes = []): Event
{
    return Event::factory()->for(User::factory())->create($attributes);
}

it('renders the events listing shell without authentication', function () {
    $this->get(route('events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Index')
            ->has('statuses', 4)
            ->where('filters.from', '2023-01-01')
        );
});

it('returns a cursor-paginated card feed without leaking the payload blob', function () {
    makeEvent([
        'type' => 'concert',
        'status' => 'published',
        'created_time' => 1_700_000_000,
        'latitude' => 40.7128,
        'longitude' => -74.0060,
    ]);

    $response = $this->getJson(route('events.feed'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'type', 'status', 'images', 'location' => ['label', 'lat', 'lng'], 'time' => ['starts_at', 'timezone', 'local_label']]],
            'next_cursor',
            'has_more',
            'stats' => ['ms', 'bytes'],
        ])
        ->assertJsonPath('data.0.type', 'concert')
        ->assertJsonPath('data.0.location.label', 'New York, United States')
        ->assertJsonPath('data.0.time.timezone', 'America/New_York');

    // Two or more locally served images, and no raw payload field.
    expect(count($response->json('data.0.images')))->toBeGreaterThanOrEqual(2);
    expect($response->json('data.0'))->not->toHaveKey('payload');
});

it('filters the feed by status', function () {
    makeEvent(['status' => 'published', 'created_time' => 1_700_000_000]);
    makeEvent(['status' => 'cancelled', 'created_time' => 1_700_000_000]);

    $this->getJson(route('events.feed', ['status' => 'cancelled']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'cancelled');
});

it('filters the feed by a date range', function () {
    makeEvent(['created_time' => strtotime('2025-06-15 12:00:00')]);
    makeEvent(['created_time' => strtotime('2025-01-15 12:00:00')]);

    $this->getJson(route('events.feed', ['from' => '2025-06-01', 'to' => '2025-06-30']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters the feed by city via a bounding box', function () {
    makeEvent(['latitude' => 40.7128, 'longitude' => -74.0060, 'created_time' => 1_700_000_000]); // New York
    makeEvent(['latitude' => 35.6762, 'longitude' => 139.6503, 'created_time' => 1_700_000_000]); // Tokyo

    $this->getJson(route('events.feed', ['city' => 'New York, United States']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.location.label', 'New York, United States');
});

it('searches event names through the full-text index', function () {
    makeEvent(['payload' => ['name' => 'Quantum Jazz Festival'], 'created_time' => 1_700_000_000]);
    makeEvent(['payload' => ['name' => 'Rock Climbing Meetup'], 'created_time' => 1_700_000_000]);

    // Token + prefix match finds the right event...
    $this->getJson(route('events.feed', ['q' => 'jazz']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Quantum Jazz Festival');

    // ...and a no-match term returns empty (and, at scale, instantly).
    $this->getJson(route('events.feed', ['q' => 'zzzxxx']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns only events inside the requested map bounds', function () {
    makeEvent(['latitude' => 40.7128, 'longitude' => -74.0060, 'created_time' => 1_700_000_000]); // New York
    makeEvent(['latitude' => 35.6762, 'longitude' => 139.6503, 'created_time' => 1_700_000_000]); // Tokyo

    $this->getJson(route('events.map', ['north' => 41, 'south' => 40, 'east' => -73, 'west' => -75]))
        ->assertOk()
        ->assertJsonPath('returned', 1)
        ->assertJsonPath('truncated', false)
        ->assertJsonPath('data.0.location.city', 'New York');
});

it('requires bounds for the map endpoint', function () {
    $this->getJson(route('events.map'))->assertStatus(422);
});

it('shows an event detail page with a resolved address and timezone', function () {
    $event = makeEvent([
        'latitude' => 51.5074,
        'longitude' => -0.1278,
        'created_time' => 1_700_000_000,
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->where('event.id', $event->id)
            ->where('event.location.label', 'London, United Kingdom')
            ->where('event.time.timezone', 'Europe/London')
        );
});

it('renders the two visualization pages and the dashboard without authentication', function () {
    $this->get(route('events.visual1'))->assertOk();
    $this->get(route('events.visual2'))->assertOk();
    $this->get(route('dashboard'))->assertOk();
});
