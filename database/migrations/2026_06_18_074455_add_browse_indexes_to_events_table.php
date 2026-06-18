<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make the browse read-path fast at 1.25M rows.
     *
     * The pages sort by `created_time` and filter by date, category, status and
     * location. Originally only `status` was indexed, so every other access path
     * was a full scan, and a filter combined with the ORDER BY made SQLite sort
     * the entire matching partition in a temp B-tree (seconds).
     *
     * The fix is twofold:
     *  - a denormalized, indexed `city` column so "filter by location" is an
     *    exact-match index range scan instead of a wide latitude-band scan;
     *  - composite indexes that pair each equality filter with the sort column,
     *    so the matching rows are read back already ordered (no temp B-tree).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Resolved "City, Country" — stamped at seed time / on model create.
            $table->string('city')->nullable()->after('longitude');

            // (created_time, id) matches the keyset feed's ORDER BY created_time
            // DESC, id DESC. Without the `id` tie-breaker in the index, the cursor
            // predicate `created_time < X OR (created_time = X AND id < Y)` falls
            // back to a full scan + full sort on every page after the first.
            $table->index(['created_time', 'id']);         // default sort + keyset pagination + date range
            $table->index(['type', 'created_time', 'id']); // category filter + keyset sort
            $table->index(['status', 'created_time', 'id']); // status filter + keyset sort
            $table->index(['city', 'created_time', 'id']);   // location filter + keyset sort
            $table->index(['latitude', 'longitude']);        // map viewport bounding box
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['created_time', 'id']);
            $table->dropIndex(['type', 'created_time', 'id']);
            $table->dropIndex(['status', 'created_time', 'id']);
            $table->dropIndex(['city', 'created_time', 'id']);
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropColumn('city');
        });
    }
};
