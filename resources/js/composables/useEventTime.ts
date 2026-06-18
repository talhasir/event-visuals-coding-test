import { computed } from 'vue';
import type { EventTime } from '@/types/events';

/**
 * Events are global, so a single timestamp means different wall-clock times to
 * the organiser and the viewer. The backend resolves the *event-local* time
 * (from the venue's timezone); this composable adds the *viewer-local* time and
 * a relative label, both derived from the same absolute unix `starts_at`.
 */
export function useEventTime(time: () => EventTime) {
    const startsAtMs = computed(() => time().starts_at * 1000);

    const viewerTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    // Same moment, rendered in the viewer's own timezone.
    const viewerLocalLabel = computed(() =>
        new Intl.DateTimeFormat(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            timeZoneName: 'short',
        }).format(new Date(startsAtMs.value)),
    );

    const isUpcoming = computed(() => startsAtMs.value > Date.now());

    // "in 3 days" / "2 hours ago", picking the largest sensible unit.
    const relativeLabel = computed(() => {
        const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
        const diffSeconds = Math.round((startsAtMs.value - Date.now()) / 1000);
        const abs = Math.abs(diffSeconds);

        const units: [Intl.RelativeTimeFormatUnit, number][] = [
            ['year', 31_536_000],
            ['month', 2_592_000],
            ['week', 604_800],
            ['day', 86_400],
            ['hour', 3_600],
            ['minute', 60],
        ];

        for (const [unit, seconds] of units) {
            if (abs >= seconds) {
                return rtf.format(Math.round(diffSeconds / seconds), unit);
            }
        }

        return rtf.format(diffSeconds, 'second');
    });

    // Show the viewer's time only when it differs from the event's timezone.
    const showViewerTime = computed(() => viewerTimezone !== time().timezone);

    return {
        viewerLocalLabel,
        relativeLabel,
        isUpcoming,
        showViewerTime,
        viewerTimezone,
    };
}
