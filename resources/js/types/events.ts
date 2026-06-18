export interface EventTime {
    starts_at: number; // absolute unix seconds
    timezone: string; // event-local IANA timezone
    local: string; // event-local ISO 8601
    local_label: string; // preformatted event-local label
    tz_abbr: string; // event-local tz abbreviation
}

export interface EventLocation {
    label: string;
    city: string;
    country: string;
    lat: number;
    lng: number;
}

export interface EventCard {
    id: string;
    name: string;
    type: string;
    status: string;
    description: string | null;
    venue: string | null;
    price: number | null;
    currency: string;
    images: string[];
    location: EventLocation;
    time: EventTime;
    attendees_count?: number;
}

export interface CityOption {
    label: string;
    lat: number;
    lng: number;
}

export interface FilterOptions {
    cities: CityOption[];
    types: string[];
    statuses: string[];
}

export interface EventFilterState {
    q: string;
    from: string;
    to: string;
    type: string;
    status: string;
    city: string;
}
