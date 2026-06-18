<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Loader2, MapPin, MapPinned } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import AttendDialog from '@/components/events/AttendDialog.vue';
import EventFilters from '@/components/events/EventFilters.vue';
import EventMap from '@/components/events/EventMap.vue';
import { Badge } from '@/components/ui/badge';
import { formatPrice, statusVariant, titleCase, typeColor } from '@/lib/events';
import type {
    EventCard,
    EventFilterState,
    FilterOptions,
} from '@/types/events';

defineProps<{ options: FilterOptions }>();

type Bounds = { north: number; south: number; east: number; west: number };

const events = ref<EventCard[]>([]);
const loading = ref(false);
const truncated = ref(false);
const returned = ref(0);
const selectedId = ref<string | null>(null);

let filters: EventFilterState | null = null;
let lastBounds: Bounds | null = null;
const listEl = ref<HTMLElement | null>(null);

function queryString(bounds: Bounds): string {
    const params = new URLSearchParams({
        north: String(bounds.north),
        south: String(bounds.south),
        east: String(bounds.east),
        west: String(bounds.west),
    });

    if (filters) {
        for (const [key, value] of Object.entries(filters)) {
            if (value) {
                params.set(key, value);
            }
        }
    }

    return params.toString();
}

async function fetchMap(bounds: Bounds) {
    lastBounds = bounds;
    loading.value = true;

    try {
        const res = await fetch(`/api/events/map?${queryString(bounds)}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await res.json();
        events.value = payload.data;
        returned.value = payload.returned;
        truncated.value = payload.truncated;
    } finally {
        loading.value = false;
    }
}

function onBounds(bounds: Bounds) {
    fetchMap(bounds);
}

function applyFilters(next: EventFilterState) {
    filters = next;

    if (lastBounds) {
        fetchMap(lastBounds);
    }
}

async function selectFromList(id: string) {
    selectedId.value = id;
}

async function selectFromMap(id: string) {
    selectedId.value = id;
    await nextTick();
    // Scroll the matching list card into view.
    listEl.value
        ?.querySelector(`[data-event="${id}"]`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<template>
    <Head title="Events · Map" />

    <div class="flex h-[calc(100vh-4rem)] flex-col gap-3 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <MapPinned class="size-6 text-primary" />
                <h1 class="text-2xl font-semibold tracking-tight">
                    Events Map
                </h1>
            </div>
            <p class="text-sm text-muted-foreground">
                Pan and zoom to explore events by location — the list stays in
                sync with the map.
            </p>
        </header>

        <div class="rounded-xl border bg-card/80 p-3 shadow-sm">
            <EventFilters :options="options" @change="applyFilters" />
        </div>

        <div
            class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-[1fr_380px]"
        >
            <!-- Map -->
            <div
                class="relative min-h-90 overflow-hidden rounded-xl border shadow-sm"
            >
                <EventMap
                    :events="events"
                    :selected-id="selectedId"
                    @bounds="onBounds"
                    @select="selectFromMap"
                />
                <div
                    v-if="loading"
                    class="absolute top-3 right-3 z-500 flex items-center gap-2 rounded-full bg-card/90 px-3 py-1.5 text-xs shadow-sm backdrop-blur"
                >
                    <Loader2 class="size-3.5 animate-spin" /> Loading…
                </div>
            </div>

            <!-- Synced list -->
            <div
                class="flex min-h-0 flex-col rounded-xl border bg-card shadow-sm"
            >
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <span class="text-sm font-medium">In view</span>
                    <span class="text-xs text-muted-foreground">
                        {{ returned.toLocaleString()
                        }}<template v-if="truncated">+ (zoom in)</template>
                        events
                    </span>
                </div>

                <div ref="listEl" class="flex-1 space-y-2 overflow-y-auto p-3">
                    <p
                        v-if="!loading && events.length === 0"
                        class="py-12 text-center text-sm text-muted-foreground"
                    >
                        No events in this area. Try zooming out or changing
                        filters.
                    </p>

                    <article
                        v-for="event in events"
                        :key="event.id"
                        :data-event="event.id"
                        class="cursor-pointer rounded-lg border p-2.5 transition-all hover:bg-accent"
                        :class="
                            event.id === selectedId ? 'ring-2 ring-primary' : ''
                        "
                        @mouseenter="selectFromList(event.id)"
                    >
                        <div class="flex gap-3">
                            <img
                                :src="event.images[0]"
                                :alt="event.name"
                                loading="lazy"
                                class="size-16 shrink-0 rounded-md object-cover"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="size-2 shrink-0 rounded-full"
                                        :style="{
                                            background: typeColor(event.type),
                                        }"
                                    />
                                    <Link
                                        :href="`/events/${event.id}`"
                                        class="truncate text-sm font-semibold hover:underline"
                                    >
                                        {{ event.name }}
                                    </Link>
                                </div>
                                <p
                                    class="mt-0.5 flex items-center gap-1 truncate text-xs text-muted-foreground"
                                >
                                    <MapPin class="size-3 shrink-0" />
                                    {{ event.location.label }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ event.time.local_label }} ({{
                                        event.time.tz_abbr
                                    }})
                                </p>
                                <div
                                    class="mt-1.5 flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <Badge
                                            :variant="
                                                statusVariant(event.status)
                                            "
                                            class="text-[10px] capitalize"
                                        >
                                            {{ titleCase(event.status) }}
                                        </Badge>
                                        <span class="text-xs font-medium">{{
                                            formatPrice(event)
                                        }}</span>
                                    </div>
                                    <AttendDialog :event="event" />
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </div>
</template>
