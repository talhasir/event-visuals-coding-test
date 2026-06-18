<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendeeRequest;
use App\Mail\AttendeeConfirmation;
use App\Models\Attendee;
use App\Models\Event;
use App\Support\CityDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /** Hard cap on markers returned for a single map viewport. */
    private const MAP_LIMIT = 500;

    /**
     * Filters accepted across the browse endpoints. Pulled once and reused so
     * every endpoint filters identically.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->only(['from', 'to', 'type', 'status', 'city', 'q']);
    }

    /**
     * Options shared with the filter UIs (cities, categories, statuses).
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'cities' => array_map(
                fn ($c) => ['label' => $c['label'], 'lat' => $c['lat'], 'lng' => $c['lng']],
                CityDirectory::all(),
            ),
            'types' => ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'],
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
        ];
    }

    /* ---------------------------------------------------------------------
     | Pages
     * ------------------------------------------------------------------- */

    /**
     * The original table listing. Kept (and de-bugged) so the starter page still
     * works: the `from` filter is now actually applied and the query no longer
     * loads the fat payload blob.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => [
                'status' => $request->status,
                'from' => $request->input('from', '2023-01-01'),
            ],
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $start = microtime(true);

        $paginator = Event::query()
            ->forListing()
            ->filter($request->only(['from', 'to', 'status', 'type']))
            ->orderByDesc('created_time')
            ->orderByDesc('id')
            ->cursorPaginate(50)
            ->withQueryString();

        $items = collect($paginator->items())->map->toCardArray()->all();

        return response()->json([
            'data' => $items,
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
            'stats' => [
                'ms' => (int) round((microtime(true) - $start) * 1000),
                'bytes' => strlen((string) json_encode($items)),
            ],
        ]);
    }

    public function visualOne(Request $request): Response
    {
        return Inertia::render('Events/VisualOne', [
            'options' => $this->filterOptions(),
            'filters' => $this->filters($request),
        ]);
    }

    public function visualTwo(Request $request): Response
    {
        return Inertia::render('Events/VisualTwo', [
            'options' => $this->filterOptions(),
            'filters' => $this->filters($request),
        ]);
    }

    public function show(Event $event): Response
    {
        return Inertia::render('Events/Show', [
            'event' => $event->toCardArray() + [
                'attendees_count' => $event->attendees()->count(),
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     | Data (JSON) — consumed by the Vue pages via fetch()
     * ------------------------------------------------------------------- */

    /**
     * Card feed for Visual 1. Keyset (cursor) pagination on (created_time, id)
     * so deep scroll stays O(1) and avoids the COUNT(*) full scan paginate() runs.
     */
    public function feed(Request $request): JsonResponse
    {
        $start = microtime(true);

        $paginator = Event::query()
            ->forListing()
            ->filter($this->filters($request))
            ->orderByDesc('created_time')
            ->orderByDesc('id')
            ->cursorPaginate(30)
            ->withQueryString();

        $items = collect($paginator->items())->map->toCardArray()->all();

        return response()->json([
            'data' => $items,
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
            'stats' => [
                'ms' => (int) round((microtime(true) - $start) * 1000),
                'bytes' => strlen((string) json_encode($items)),
            ],
        ]);
    }

    /**
     * Markers for Visual 2. Requires viewport bounds so we never load the whole
     * table; queries the lat/lng index and caps the result, reporting how many
     * matched so the UI can prompt the user to zoom in.
     */
    public function map(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'north' => ['required', 'numeric'],
            'south' => ['required', 'numeric'],
            'east' => ['required', 'numeric'],
            'west' => ['required', 'numeric'],
        ]);

        $bounds = [
            'south' => (float) $validated['south'],
            'west' => (float) $validated['west'],
            'north' => (float) $validated['north'],
            'east' => (float) $validated['east'],
        ];

        // Markers are spatial, not a chronological feed, so we skip the ORDER BY
        // (which would force a sort of the whole bbox partition) and fetch one
        // extra row to detect truncation in a single index-only query.
        $rows = Event::query()
            ->forListing()
            ->filter($this->filters($request))
            ->withinBounds($bounds)
            ->limit(self::MAP_LIMIT + 1)
            ->get();

        $truncated = $rows->count() > self::MAP_LIMIT;

        $events = $rows->take(self::MAP_LIMIT)->map->toCardArray()->values()->all();

        return response()->json([
            'data' => $events,
            'returned' => count($events),
            'truncated' => $truncated,
        ]);
    }

    /* ---------------------------------------------------------------------
     | Attendees
     * ------------------------------------------------------------------- */

    public function storeAttendee(StoreAttendeeRequest $request, Event $event): RedirectResponse
    {
        $attendee = Attendee::create([
            'event_id' => $event->id,
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
        ]);

        // Queued; with MAIL_MAILER=log the confirmation lands in storage/logs.
        Mail::to($attendee->email)->send(new AttendeeConfirmation($attendee));

        return back()->with('success', "You're on the list — a confirmation email is on its way.");
    }
}
