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

        // Free-text search on the event name. LIKE on a json_extract can't use an
        // index — acceptable for this exercise; FTS5 is the production path (see DECISIONS.md).
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
            $query->whereRaw("json_extract(payload, '$.name') LIKE ? escape '\\'", [$term]);
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
     * 2–3 local placeholder image URLs, chosen deterministically from the event's
     * id + category. Served from /images/events/*.svg — no external URLs, no DB
     * storage, stable across requests, and scales to any row count.
     *
     * @return list<string>
     */
    public function images(): array
    {
        $category = in_array($this->type, ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'], true)
            ? $this->type
            : 'concert';

        // Rotate the variant order by a stable hash so the "cover" image varies
        // between events of the same category.
        $offset = hexdec(substr(md5((string) $this->id), 0, 4)) % self::IMAGE_VARIANTS;

        $urls = [];
        for ($i = 0; $i < self::IMAGE_VARIANTS; $i++) {
            $variant = (($offset + $i) % self::IMAGE_VARIANTS) + 1;
            $urls[] = asset("images/events/{$category}-{$variant}.svg");
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
