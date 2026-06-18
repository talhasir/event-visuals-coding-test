import type { EventCard } from '@/types/events';

export function formatPrice(
    event: Pick<EventCard, 'price' | 'currency'>,
): string {
    if (event.price === null || event.price === 0) {
        return 'Free';
    }

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: event.currency || 'USD',
            maximumFractionDigits: 0,
        }).format(event.price);
    } catch {
        return `${event.currency} ${event.price.toFixed(0)}`;
    }
}

export function titleCase(value: string): string {
    return value
        .replace(/(^|[\s-])\w/g, (c) => c.toUpperCase())
        .replace(/_/g, ' ');
}

/** Map an event status to a shadcn Badge variant. */
export function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'published':
            return 'default';
        case 'sold_out':
            return 'secondary';
        case 'cancelled':
            return 'destructive';
        default:
            return 'outline';
    }
}

/** Accent color per category — used for map markers and subtle card accents. */
export function typeColor(type: string): string {
    const map: Record<string, string> = {
        concert: '#7c3aed',
        conference: '#2563eb',
        meetup: '#0d9488',
        workshop: '#ea580c',
        festival: '#db2777',
        sports: '#dc2626',
        networking: '#6366f1',
        exhibition: '#475569',
    };

    return map[type] ?? '#6b7280';
}
