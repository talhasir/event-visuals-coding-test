# Event Visuals — Coding Test

Two distinct, modern event-browsing experiences built over a realistic, fully-seeded dataset
(**1.25M events**): a **card gallery** and an **interactive map**. Includes local images, offline
reverse-geocoded addresses, timezone-aware times, date/location filtering, and attendee registration
with confirmation + reminder emails.

> Stack: Laravel 13 · Inertia · Vue 3 · Tailwind 4 · shadcn-vue · Leaflet · Pest. See
> [`DECISIONS.md`](DECISIONS.md) for the reasoning behind every choice (especially how it's built to
> hold up at 1.25M rows).

## Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Seed. Defaults to the full 1.25M dataset (~2.5GB, ~1–2 min).
php artisan db:seed
#   Fast path for a quick look:  SEED_ROWS=20000 php artisan db:seed
```

Run everything (server + queue worker + Vite + logs) with one command:

```bash
composer dev
```

Then open:

- **/events-visual-1** — the gallery
- **/events-visual-2** — the map
- **/events** — the original table view (kept and de-bugged)

The home route `/` redirects to the gallery.

## Features

| Requirement | Where |
| --- | --- |
| Two distinct layouts | `Events/VisualOne.vue` (gallery), `Events/VisualTwo.vue` (map) |
| 2+ local images per event | generated posters via `App\Support\EventPoster` + `Event::images()` |
| Human-readable address from lat/lng | `App\Support\CityDirectory` (offline, nearest-anchor) |
| Timezone-aware date/time | event-local in `Event::toCardArray()`, viewer-local in `useEventTime.ts` |
| Filter by date **and** location | `Event::scopeFilter()` + `EventFilters.vue` |
| Attendee registration | `POST /events/{event}/attendees`, `AttendDialog.vue` |
| Confirmation email | `App\Mail\AttendeeConfirmation` (queued) |
| Reminders 3 days + 24h before | `php artisan events:send-reminders`, scheduled hourly |

## Emails

`MAIL_MAILER=log` by default — sent mail is written to `storage/logs/laravel.log`. Mailables are queued
(`QUEUE_CONNECTION=database`), so run a worker to flush them (`composer dev` already does):

```bash
php artisan queue:work
```

Try it: register on any event → a confirmation appears in the log. For reminders, run the command
(seeded data includes near-future events):

```bash
php artisan events:send-reminders   # idempotent — a second run sends nothing new
```

## Quality

All gates pass:

```bash
composer test          # Pint + PHPStan (larastan level 7) + Pest
npm run lint:check && npm run format:check && npm run types:check
```
