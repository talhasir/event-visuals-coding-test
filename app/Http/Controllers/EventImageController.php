<?php

namespace App\Http\Controllers;

use App\Support\EventPoster;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EventImageController extends Controller
{
    /**
     * Serve a generated event poster as SVG. Everything needed to render is in
     * the query string (seed/category/variant), so there's no database lookup —
     * the response is deterministic and immutably cacheable.
     */
    public function __invoke(Request $request): Response
    {
        $seed = preg_replace('/[^a-z0-9]/i', '', (string) $request->query('seed', '0')) ?: '0';
        $category = (string) $request->query('c', 'concert');
        $variant = max(1, min(6, (int) $request->query('v', 1)));

        $svg = EventPoster::svg($seed, $category, $variant);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
