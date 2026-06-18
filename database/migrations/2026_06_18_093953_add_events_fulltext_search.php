<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Full-text search for event names.
     *
     * Searching names via `json_extract(payload,'$.name') LIKE '%x%'` can't use
     * an index — at 1.25M rows a rare/no-match term scans the whole table and
     * parses every payload (30s+). An FTS5 virtual table turns name search into
     * an indexed token lookup (sub-millisecond, even for no matches).
     *
     * The table is kept in sync with `events` by triggers, so it stays correct
     * for the bulk seeder and for any model create/update/delete. Existing rows
     * are backfilled once here.
     */
    public function up(): void
    {
        // Only SQLite ships FTS5 here; guard so other drivers don't break.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement("CREATE VIRTUAL TABLE events_search USING fts5(event_id UNINDEXED, name, tokenize='unicode61')");

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER events_search_ai AFTER INSERT ON events BEGIN
                INSERT INTO events_search(event_id, name) VALUES (new.id, json_extract(new.payload, '$.name'));
            END;
            CREATE TRIGGER events_search_ad AFTER DELETE ON events BEGIN
                DELETE FROM events_search WHERE event_id = old.id;
            END;
            CREATE TRIGGER events_search_au AFTER UPDATE ON events BEGIN
                DELETE FROM events_search WHERE event_id = old.id;
                INSERT INTO events_search(event_id, name) VALUES (new.id, json_extract(new.payload, '$.name'));
            END;
        SQL);

        // Backfill any rows that already exist (no-op on a fresh database, since
        // the seeder runs after migrations and the insert trigger handles it).
        DB::statement("INSERT INTO events_search(event_id, name) SELECT id, json_extract(payload, '$.name') FROM events");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS events_search_ai; DROP TRIGGER IF EXISTS events_search_ad; DROP TRIGGER IF EXISTS events_search_au;');
        Schema::dropIfExists('events_search');
    }
};
