# Decisions & Notes

A short tour of the choices behind this solution. The brief asked for two distinct event-browsing
pages over a **realistic, fully-seeded dataset (1.25M events)**, with local images, human-readable
addresses, sensible timezones, date/location filtering, and attendee registration with confirmation +
reminder emails. The throughline of every decision below is: **the dataset is fixed and large, so almost
every feature is really a read-path decision.**

## The two pages (intentionally different)

- **Visual 1 — Gallery** (`Events/VisualOne.vue`): a responsive card grid. Each card has an image
  carousel, the resolved city, the event-local date/time + a relative label ("in 3 days"), status/category
  badges, price, and an **Attend** button. Infinite scroll.
- **Visual 2 — Map** (`Events/VisualTwo.vue`): an interactive Leaflet map with marker clustering and a
  **synced side list** — hovering a list item highlights its pin and vice-versa. Same filter bar.

They share a filter bar, an attend dialog, an image carousel and the time composable, but the browsing
metaphor is genuinely different (browse-by-scroll vs. browse-by-place).

## Scale — the core of the test

The starter listing loaded the entire ~1.5KB JSON `payload` for every row (just to show a name), sorted
by an **unindexed** `created_time`, and paginated with `OFFSET`. It even shipped a bytes/ms meter
advertising the cost. Fixes:

1. **Column projection, not payload hydration.** `Event::scopeForListing()` selects only the real columns
   and `json_extract`s the four fields actually rendered (`name`, `description`, `venue.name`,
   `pricing.*`). This drops per-row response size ~95% — the fat `notes` padding never leaves the DB.
2. **Composite indexes that pair each filter with the sort.** A single-column index isn't enough: a
   filter + `ORDER BY created_time` made SQLite sort the *entire* matching partition in a temp B-tree
   (e.g. `type=concert` ⇒ 156k rows sorted ⇒ ~5s). The migration adds `(type, created_time)`,
   `(status, created_time)` and `(city, created_time)` so matching rows are read back already ordered —
   no sort. Plus `created_time` (default/date-only) and `(latitude, longitude)` (map bbox).
3. **A denormalized, indexed `city` column.** "Filter by location" as a lat/lng bounding box scans a wide
   latitude band (many cities share a latitude). Instead, each row is stamped with its resolved
   "City, Country" at seed time (and on model create), so the filter is an exact-match index range scan.
4. **Keyset (cursor) pagination** on `(created_time, id)` for the gallery feed instead of `OFFSET` — deep
   scroll stays O(1) and skips the `SELECT COUNT(*)` full scan that `paginate()` runs on every page.
5. **The map never loads everything.** `/api/events/map` *requires* viewport bounds, queries the lat/lng
   index, fetches `LIMIT 501` (one extra row to flag truncation in a single query, no separate COUNT),
   skips the ORDER BY (markers are spatial, not chronological), and returns a `truncated` flag so the UI
   says "zoom in" rather than rendering a million pins.
6. **Honest search.** Free-text name search uses `json_extract ... LIKE`, which can't use an index. Fine
   for this exercise; the production path is an FTS5 virtual table or a generated, indexed `name` column.
   Called out here rather than hidden.

**Measured at 1.25M rows** (server query+serialize time):

| Query | Before | After |
| --- | --- | --- |
| Gallery feed, first page | (full payload) | **24 ms**, ~21 KB |
| Filter by category | ~5,000 ms | **8 ms** |
| Filter by location (city) | ~1,400 ms | **8 ms** |
| Category + date + location combined | ~5,000 ms | **23 ms** |
| Map viewport (capped 500) | — | **~100 ms** |

## Addresses — offline reverse-geocoding

Events only carry lat/lng. Rather than call an external geocoder 1.25M times, I exploited the data: the
seeder jitters every point ±0.5° around ~85 known **city anchors**. `App\Support\CityDirectory` mirrors
that anchor list with names, countries and IANA timezones, and snaps any coordinate to its nearest anchor
(squared distance — anchors are degrees apart, jitter is sub-degree, so it's unambiguous). Output:
"Venue · City, Country", with zero network calls and trivial cost.

## Timezones

Events are global, so a single timestamp is two different wall-clock times to the organiser and the
viewer. I show **both**:

- **Event-local** time is computed server-side from the venue's anchor timezone (`toCardArray()` →
  `time.local_label` + tz abbreviation).
- **Viewer-local** time is computed client-side from the same absolute unix `starts_at` via `Intl`
  (`useEventTime.ts`), shown only when it differs from the event's timezone, alongside a relative label.

## Images — local, end-to-end, no DB bloat

Real per-event uploads make no sense at 1.25M rows. Instead I generated a small pool of **local gradient
SVG placeholders** per category (`public/images/events/*.svg`) and an `Event::images()` accessor that
deterministically picks 2–3 by hashing the event id (so the "cover" varies between events of the same
category). Files → model → API → carousel, fully local (no hotlinking), and it scales to any row count.
The accessor is the single swap-point if real stored images are added later.

## Attendees & emails

- `attendees` table: one row per registration, `unique(event_id, email)`, plus `reminder_72h_sent_at`
  and `reminder_24h_sent_at` stamps for idempotency.
- Registration (`POST /events/{event}/attendees`) validates name + email, blocks duplicates with a
  friendly message, and queues an `AttendeeConfirmation` mail.
- Reminders: `php artisan events:send-reminders` (scheduled hourly in `routes/console.php`) sends the
  **3-day** and **24-hour** mails. The 24h window takes precedence so a late registrant never gets a
  mislabelled "3 days" mail; the stamps make re-runs safe (no double-sends). Chunked for scale.
- `MAIL_MAILER=log` by default, so a reviewer can see every email in `storage/logs/laravel.log` after
  running the queue worker.

## Fixed starter bugs

- `Index.vue` called `aplyFilters` (typo) → the Filter button did nothing. Fixed.
- The listing rendered a "From" date input the controller **never applied**. The date filter now works
  (and is covered by a test).
- The listing query loaded the full payload and sorted on an unindexed column (see Scale).

## Quality gates

`composer lint:check` (Pint), `npm run lint:check` (ESLint), `npm run format:check` (Prettier),
`npm run types:check` (vue-tsc), `phpstan analyse` (larastan **level 7**), and `php artisan test`
(Pest) all pass. New code is PHPStan level-7 clean; the two **provided** data-generation files
(`EventSeeder`, `EventFactory`) shipped with pre-existing level-7 violations and are excluded from
analysis (noted in `phpstan.neon`) rather than rewritten, to avoid touching the supplied seeding harness.

## Tests

- `tests/Unit/CityDirectoryTest.php` — nearest-anchor resolution + bounding boxes.
- `tests/Feature/EventListingTest.php` — feed shape (no payload leak, ≥2 images), date/city/status
  filters, map bounds + validation, detail page address/timezone.
- `tests/Feature/AttendeeRegistrationTest.php` — registration, queued confirmation, duplicate rejection,
  validation.
- `tests/Feature/EventReminderTest.php` — 24h + 3-day windows, idempotency, out-of-window no-op.

## Trade-offs / what I'd do next

- FTS5 (or a generated indexed column) for name search at scale.
- A small caching layer for the city/options payload (it's static).
- Real image storage + an upload flow if events ever get bespoke artwork.
- The legacy `/events` table view is kept (de-bugged) for continuity; the two visual pages are the focus.
