<?php

namespace App\Models;

use App\Support\CityDirectory;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /** Number of distinct placeholder variants generated per category. */
    private const IMAGE_VARIANTS = 3;

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    protected static function booted(): void
    {
        // Stamp the denormalized `city` (used for fast location filtering) from
        // the coordinates whenever an event is created without one. The bulk
        // seeder sets it directly; this covers factory and regular creates.
        static::creating(function (Event $event): void {
            if (empty($event->city) && $event->latitude !== null && $event->longitude !== null) {
                $event->city = CityDirectory::nearest((float) $event->latitude, (float) $event->longitude)['label'];
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Attendee, $this> */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /**
     * Columns needed to render a listing/card without touching the fat `payload`
     * blob. The JSON fields we actually display are pulled out as cheap aliases
     * via json_extract — this keeps responses ~95% smaller at 1.25M rows.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeForListing(Builder $query): Builder
    {
        return $query
            ->select(['id', 'type', 'status', 'created_time', 'latitude', 'longitude', 'user_id'])
            ->selectRaw("json_extract(payload, '$.name') as name")
            ->selectRaw("json_extract(payload, '$.description') as description")
            ->selectRaw("json_extract(payload, '$.venue.name') as venue")
            ->selectRaw("json_extract(payload, '$.pricing.min_price') as price")
            ->selectRaw("json_extract(payload, '$.pricing.currency') as currency");
    }

    /**
     * Apply the browse filters. Every clause is backed by an index added in the
     * attendees/index migration (created_time, type, status, latitude/longitude).
     *
     * @param  Builder<Event>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Event>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        // Date range — `created_time` is the unix start timestamp.
        if (! empty($filters['from'])) {
            $query->where('created_time', '>=', Carbon::parse($filters['from'])->startOfDay()->timestamp);
        }

        if (! empty($filters['to'])) {
            $query->where('created_time', '<=', Carbon::parse($filters['to'])->endOfDay()->timestamp);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Location by city — an exact match on the denormalized, indexed column
        // (the (city, created_time) composite serves the filter + sort in one scan).
        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        // Free-text search on the event name via the FTS5 index (events_search) —
        // an indexed token lookup that stays fast even for rare/no-match terms,
        // unlike a json_extract LIKE which scans the whole table.
        if (! empty($filters['q']) && ($match = self::ftsMatch($filters['q'])) !== null) {
            $ids = DB::table('events_search')
                ->whereRaw('events_search MATCH ?', [$match])
                ->limit(2000)
                ->pluck('event_id');

            $query->whereIn('id', $ids);
        }

        return $query;
    }

    /**
     * @param  Builder<Event>  $query
     * @param  array{south: float, west: float, north: float, east: float}  $bounds
     * @return Builder<Event>
     */
    public function scopeWithinBounds(Builder $query, array $bounds): Builder
    {
        return $query
            ->whereBetween('latitude', [(float) $bounds['south'], (float) $bounds['north']])
            ->whereBetween('longitude', [(float) $bounds['west'], (float) $bounds['east']]);
    }

    /**
     * Turn raw user input into a safe FTS5 MATCH expression: alphanumeric tokens
     * with a prefix wildcard, AND-ed together (so "tech sum" matches
     * "Tech Summit"). Returns null when there's nothing searchable.
     */
    private static function ftsMatch(string $input): ?string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($input), $matches);

        $terms = array_map(static fn (string $t): string => $t.'*', $matches[0]);

        return $terms === [] ? null : implode(' ', $terms);
    }

    /**
     * 2–3 generated poster URLs, unique per event and served locally from the
     * event-poster route. The art is derived deterministically from the event id
     * (+ variant), so every event gets its own posters, they're stable across
     * requests, there's no stored-file or DB overhead, and it scales to any row
     * count. No external/hotlinked URLs.
     *
     * @return list<string>
     */
    public function images(): array
    {
        $category = in_array($this->type, ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'], true)
            ? $this->type
            : 'concert';

        $seed = substr(md5((string) $this->id), 0, 12);

        $urls = [];
        for ($v = 1; $v <= self::IMAGE_VARIANTS; $v++) {
            $urls[] = route('events.poster', ['seed' => $seed, 'c' => $category, 'v' => $v]);
        }

        return $urls;
    }

    /**
     * Resolve a display field from either the projected json_extract alias
     * (set by scopeForListing(), the fast path) or the fully-cast `payload`
     * array (when the whole model was loaded). Lets toCardArray() work in both
     * cases without ever forcing the fat payload to load.
     *
     * @param  list<string>  $payloadPath
     */
    private function cardField(string $alias, array $payloadPath): mixed
    {
        // Fast path: the alias column is present on the projected model.
        if (array_key_exists($alias, $this->attributes)) {
            return $this->attributes[$alias];
        }

        // Fallback: dig into the cast payload array.
        $value = $this->payload;
        foreach ($payloadPath as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * The API/card representation. Reads the projected aliases set by
     * scopeForListing() (so it never needs the full payload) plus the resolved
     * location and a timezone-aware time block.
     *
     * @return array<string, mixed>
     */
    public function toCardArray(): array
    {
        $startsAt = (int) $this->created_time;
        $location = CityDirectory::nearest((float) $this->latitude, (float) $this->longitude);

        $localStart = Carbon::createFromTimestampUTC($startsAt)->setTimezone($location['timezone']);

        $price = $this->cardField('price', ['pricing', 'min_price']);

        return [
            'id' => $this->id,
            'name' => $this->cardField('name', ['name']) ?? 'Untitled Event',
            'type' => $this->type,
            'status' => $this->status,
            'description' => $this->cardField('description', ['description']),
            'venue' => $this->cardField('venue', ['venue', 'name']),
            'price' => $price !== null ? (float) $price : null,
            'currency' => $this->cardField('currency', ['pricing', 'currency']) ?? 'USD',
            'images' => $this->images(),
            'location' => [
                'label' => $location['label'],
                'city' => $location['city'],
                'country' => $location['country'],
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude,
            ],
            'time' => [
                'starts_at' => $startsAt,                  // absolute unix moment
                'timezone' => $location['timezone'],       // event-local IANA tz
                'local' => $localStart->toIso8601String(),  // event-local ISO
                'local_label' => $localStart->format('D, M j Y · g:i A'),
                'tz_abbr' => $localStart->format('T'),
            ],
        ];
    }
}
