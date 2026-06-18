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

            $table->index('created_time');                 // default sort + date-only range
            $table->index(['type', 'created_time']);       // category filter + sort
            $table->index(['status', 'created_time']);     // status filter + sort
            $table->index(['city', 'created_time']);       // location filter + sort
            $table->index(['latitude', 'longitude']);      // map viewport bounding box
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['created_time']);
            $table->dropIndex(['type', 'created_time']);
            $table->dropIndex(['status', 'created_time']);
            $table->dropIndex(['city', 'created_time']);
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropColumn('city');
        });
    }
};
