<?php

namespace App\Support;

/**
 * Generative event-poster artwork.
 *
 * Produces a unique, premium-looking SVG poster for each event, derived
 * deterministically from the event id — so every event gets its own art, the
 * three images per event differ, and events of the same category stay visually
 * cohesive (shared palette). Pure string building (sub-millisecond), no stored
 * files, served locally, scales to any number of rows.
 *
 * Aesthetic: a mesh-gradient base (diagonal gradient + soft "screen" light
 * blobs), a layered geometric motif, fine film grain and a vignette.
 */
final class EventPoster
{
    private const W = 1200;

    private const H = 800;

    /**
     * Per-category palettes as [hue, saturation%, lightness%] triples. The first
     * two drive the base gradient; all of them tint the light blobs.
     *
     * @var array<string, list<array{0:int,1:int,2:int}>>
     */
    private const PALETTES = [
        'concert' => [[265, 80, 55], [315, 85, 55], [230, 80, 62]],
        'conference' => [[215, 85, 55], [195, 90, 52], [250, 70, 58]],
        'meetup' => [[168, 72, 45], [150, 68, 46], [188, 80, 48]],
        'workshop' => [[35, 95, 56], [18, 90, 54], [5, 85, 56]],
        'festival' => [[330, 85, 58], [18, 92, 58], [45, 95, 60]],
        'sports' => [[2, 85, 55], [350, 80, 50], [222, 85, 56]],
        'networking' => [[245, 78, 62], [278, 75, 60], [200, 88, 56]],
        'exhibition' => [[222, 16, 34], [222, 14, 22], [40, 32, 56]],
    ];

    private int $state;

    public function __construct(string $seed)
    {
        // xorshift32 seeded from the id+variant; never zero.
        $this->state = crc32($seed) ?: 0x9E3779B9;
    }

    /**
     * Render a poster for the given seed + category + variant.
     */
    public static function svg(string $seed, string $category, int $variant): string
    {
        $category = isset(self::PALETTES[$category]) ? $category : 'concert';

        return (new self($seed.':'.$variant))->build($category);
    }

    private function build(string $category): string
    {
        $palette = self::PALETTES[$category];
        $shift = (int) $this->between(-22, 22);

        [$g1, $g2, $g3] = array_map(fn ($c) => $this->shiftHue($c, $shift), $palette);

        $w = self::W;
        $h = self::H;

        $base = $this->hsl($g1);
        $mid = $this->hsl($this->mix($g1, $g2));
        $end = $this->hsl($this->shade($g2, -10));

        // 3–4 soft light blobs for the mesh-gradient glow.
        $blobs = '';
        $blobColors = [$this->light($g3, 14), $this->light($g2, 10), $this->light($g1, 18)];
        $count = (int) $this->between(3, 4.99);
        for ($i = 0; $i < $count; $i++) {
            $cx = (int) $this->between(0, $w);
            $cy = (int) $this->between(0, $h);
            $r = (int) $this->between($w * 0.28, $w * 0.55);
            $color = $this->hsl($blobColors[$i % count($blobColors)]);
            $blobs .= sprintf(
                '<circle cx="%d" cy="%d" r="%d" fill="url(#blob%d)" style="mix-blend-mode:screen" />',
                $cx, $cy, $r, $i
            );
            $blobs = '<radialGradient id="blob'.$i.'" cx="50%" cy="50%" r="50%">'
                .'<stop offset="0%" stop-color="'.$color.'" stop-opacity="0.85"/>'
                .'<stop offset="100%" stop-color="'.$color.'" stop-opacity="0"/></radialGradient>'.$blobs;
        }

        $motif = $this->motif();

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}" preserveAspectRatio="xMidYMid slice" role="img">
          <defs>
            <linearGradient id="bg" x1="0" y1="0" x2="{$w}" y2="{$h}" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="{$base}"/>
              <stop offset="52%" stop-color="{$mid}"/>
              <stop offset="100%" stop-color="{$end}"/>
            </linearGradient>
            <radialGradient id="vig" cx="50%" cy="42%" r="75%">
              <stop offset="55%" stop-color="#000" stop-opacity="0"/>
              <stop offset="100%" stop-color="#000" stop-opacity="0.40"/>
            </radialGradient>
            <linearGradient id="scrim" x1="0" y1="{$h}" x2="0" y2="0" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#000" stop-opacity="0.34"/>
              <stop offset="38%" stop-color="#000" stop-opacity="0"/>
            </linearGradient>
            <filter id="grain"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" stitchTiles="stitch"/>
              <feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 0.5 0"/></filter>
          </defs>

          <rect width="{$w}" height="{$h}" fill="url(#bg)"/>
          {$blobs}
          <g opacity="0.9">{$motif}</g>
          <rect width="{$w}" height="{$h}" fill="url(#scrim)"/>
          <rect width="{$w}" height="{$h}" fill="url(#vig)"/>
          <rect width="{$w}" height="{$h}" filter="url(#grain)" opacity="0.06"/>
        </svg>
        SVG;
    }

    /**
     * One of several abstract geometric motifs, chosen by the seed.
     */
    private function motif(): string
    {
        $w = self::W;
        $h = self::H;
        $white = 'rgba(255,255,255,';

        return match ($this->pick(['rings', 'waves', 'grid', 'rays', 'arcs'])) {
            'rings' => (function () use ($w, $white) {
                $cx = (int) $this->between($w * 0.55, $w * 0.9);
                $cy = (int) $this->between(80, 260);
                $out = '';
                for ($r = 70; $r <= 520; $r += 58) {
                    $out .= sprintf('<circle cx="%d" cy="%d" r="%d" fill="none" stroke="%s0.10)" stroke-width="2"/>', $cx, $cy, $r, $white);
                }

                return $out;
            })(),
            'waves' => (function () use ($w, $h, $white) {
                $out = '';
                for ($i = 0; $i < 5; $i++) {
                    $y = (int) ($h * 0.45 + $i * 70);
                    $amp = (int) $this->between(30, 70);
                    $out .= sprintf(
                        '<path d="M0 %d C %d %d, %d %d, %d %d S %d %d, %d %d" fill="none" stroke="%s%.2f)" stroke-width="2.5"/>',
                        $y, $w * 0.25, $y - $amp, $w * 0.5, $y + $amp, $w * 0.5, $y,
                        $w * 0.85, $y - $amp, $w, $y, $white, 0.12 - $i * 0.015
                    );
                }

                return $out;
            })(),
            'grid' => (function () use ($w, $h, $white) {
                $out = '';
                $gap = 46;
                for ($x = $gap; $x < $w; $x += $gap) {
                    for ($y = $gap; $y < $h; $y += $gap) {
                        $out .= sprintf('<circle cx="%d" cy="%d" r="2" fill="%s0.16)"/>', $x, $y, $white);
                    }
                }

                return '<g opacity="0.7">'.$out.'</g>';
            })(),
            'rays' => (function () use ($w, $h, $white) {
                $ox = $this->pick([0, $w]);
                $oy = (int) $this->between(-40, $h * 0.3);
                $out = '';
                for ($i = 0; $i < 9; $i++) {
                    $tx = (int) $this->between(0, $w);
                    $out .= sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s0.07)" stroke-width="46"/>', $ox, $oy, $tx, $h, $white);
                }

                return '<g style="mix-blend-mode:soft-light">'.$out.'</g>';
            })(),
            default => (function () use ($w, $h, $white) {
                $cx = (int) $this->between($w * 0.1, $w * 0.4);
                $cy = (int) $this->between($h * 0.6, $h);
                $out = '';
                for ($r = 120; $r <= 760; $r += 70) {
                    $out .= sprintf('<circle cx="%d" cy="%d" r="%d" fill="none" stroke="%s0.09)" stroke-width="2.5"/>', $cx, $cy, $r, $white);
                }

                return $out;
            })(),
        };
    }

    /* ----------------------------- helpers ----------------------------- */

    private function rand(): float
    {
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= ($x >> 17);
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x & 0xFFFFFFFF;

        return $this->state / 0xFFFFFFFF;
    }

    private function between(float $a, float $b): float
    {
        return $a + ($b - $a) * $this->rand();
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items)
    {
        return $items[(int) floor($this->rand() * count($items)) % count($items)];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $c
     */
    private function hsl(array $c): string
    {
        return sprintf('hsl(%d, %d%%, %d%%)', ($c[0] + 360) % 360, max(0, min(100, $c[1])), max(0, min(100, $c[2])));
    }

    /**
     * @param  array{0:int,1:int,2:int}  $c
     * @return array{0:int,1:int,2:int}
     */
    private function shiftHue(array $c, int $deg): array
    {
        return [($c[0] + $deg + 360) % 360, $c[1], $c[2]];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $c
     * @return array{0:int,1:int,2:int}
     */
    private function light(array $c, int $by): array
    {
        return [$c[0], $c[1], min(100, $c[2] + $by)];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $c
     * @return array{0:int,1:int,2:int}
     */
    private function shade(array $c, int $by): array
    {
        return [$c[0], $c[1], max(0, $c[2] + $by)];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $a
     * @param  array{0:int,1:int,2:int}  $b
     * @return array{0:int,1:int,2:int}
     */
    private function mix(array $a, array $b): array
    {
        return [(int) (($a[0] + $b[0]) / 2), (int) (($a[1] + $b[1]) / 2), (int) (($a[2] + $b[2]) / 2)];
    }
}
