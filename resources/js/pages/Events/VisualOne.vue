<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { LayoutGrid, Loader2 } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import EventCard from '@/components/events/EventCard.vue';
import EventFilters from '@/components/events/EventFilters.vue';
import type {
    EventCard as EventCardType,
    EventFilterState,
    FilterOptions,
} from '@/types/events';

defineProps<{ options: FilterOptions }>();

const rows = ref<EventCardType[]>([]);
const cursor = ref<string | null>(null);
const hasMore = ref(true);
const loading = ref(false);
const initialised = ref(false);
const total = ref(0);
const loadedBytes = ref(0);

let filters: EventFilterState | null = null;
const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

function queryString(): string {
    const params = new URLSearchParams();

    if (cursor.value) {
        params.set('cursor', cursor.value);
    }

    if (filters) {
        for (const [key, value] of Object.entries(filters)) {
            if (value) {
                params.set(key, value);
            }
        }
    }

    return params.toString();
}

async function loadMore() {
    if (loading.value || !hasMore.value) {
        return;
    }

    loading.value = true;

    try {
        const res = await fetch(`/api/events/feed?${queryString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await res.json();

        rows.value.push(...payload.data);
        cursor.value = payload.next_cursor;
        hasMore.value = payload.has_more;
        total.value = rows.value.length;
        loadedBytes.value += payload.stats.bytes;
        initialised.value = true;
    } finally {
        loading.value = false;
    }
}

function applyFilters(next: EventFilterState) {
    filters = next;
    rows.value = [];
    cursor.value = null;
    hasMore.value = true;
    loadedBytes.value = 0;
    initialised.value = false;
    loadMore();
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) {
                loadMore();
            }
        },
        { rootMargin: '600px' },
    );

    if (sentinel.value) {
        observer.observe(sentinel.value);
    }

    loadMore();
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Events · Gallery" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <LayoutGrid class="size-6 text-primary" />
                <h1 class="text-2xl font-semibold tracking-tight">
                    Discover Events
                </h1>
            </div>
            <p class="text-sm text-muted-foreground">
                Browse upcoming concerts, festivals and meetups around the
                world.
            </p>
        </header>

        <div
            class="sticky top-2 z-10 rounded-xl border bg-card/80 p-3 shadow-sm backdrop-blur"
        >
            <EventFilters :options="options" @change="applyFilters" />
        </div>

        <!-- Grid -->
        <div
            class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <transition-group name="card">
                <EventCard
                    v-for="event in rows"
                    :key="event.id"
                    :event="event"
                />
            </transition-group>
        </div>

        <!-- Empty -->
        <div
            v-if="initialised && rows.length === 0 && !loading"
            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed py-20 text-center"
        >
            <p class="text-lg font-medium">No events match your filters</p>
            <p class="text-sm text-muted-foreground">
                Try widening the date range or clearing the location.
            </p>
        </div>

        <!-- Loader / sentinel -->
        <div ref="sentinel" class="flex items-center justify-center py-6">
            <span
                v-if="loading"
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <Loader2 class="size-4 animate-spin" /> Loading events…
            </span>
            <span
                v-else-if="!hasMore && rows.length > 0"
                class="text-sm text-muted-foreground"
            >
                You've reached the end · {{ total.toLocaleString() }} loaded
            </span>
        </div>
    </div>
</template>

<style scoped>
.card-enter-active {
    transition: all 0.4s ease;
}
.card-enter-from {
    opacity: 0;
    transform: translateY(12px);
}
</style>
