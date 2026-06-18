<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { statusVariant, titleCase } from '@/lib/events';
import type { EventCard } from '@/types/events';

const props = defineProps<{
    filters: { status: string | null; from: string };
    statuses: string[];
}>();

const form = reactive({
    status: props.filters.status ?? '',
    from: props.filters.from ?? '',
});

const rows = ref<EventCard[]>([]);
const cursor = ref<string | null>(null);
const hasMore = ref(true);
const loadedBytes = ref(0);
const loadedMs = ref(0);
const loading = ref(false);
const hasLoadedOnce = ref(false);

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const loadedSize = computed(() => {
    const kb = loadedBytes.value / 1024;

    return kb < 1024 ? `${kb.toFixed(1)} KB` : `${(kb / 1024).toFixed(2)} MB`;
});

const loadedSeconds = computed(() => (loadedMs.value / 1000).toFixed(1));

async function loadMore() {
    if (loading.value || !hasMore.value) {
        return;
    }

    loading.value = true;

    const params = new URLSearchParams();

    if (cursor.value) {
        params.set('cursor', cursor.value);
    }

    if (form.status) {
        params.set('status', form.status);
    }

    if (form.from) {
        params.set('from', form.from);
    }

    try {
        const response = await fetch(`/events/data?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();

        rows.value.push(...payload.data);
        cursor.value = payload.next_cursor;
        hasMore.value = payload.has_more;
        loadedBytes.value += payload.stats.bytes;
        loadedMs.value += payload.stats.ms;
        hasLoadedOnce.value = true;
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    rows.value = [];
    cursor.value = null;
    hasMore.value = true;
    loadedBytes.value = 0;
    loadedMs.value = 0;
    hasLoadedOnce.value = false;
    loadMore();
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) {
                loadMore();
            }
        },
        { rootMargin: '400px' },
    );

    if (sentinel.value) {
        observer.observe(sentinel.value);
    }

    loadMore();
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Events" />

    <div class="flex flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Events</h1>
            <p class="text-sm text-muted-foreground">
                Classic table view · projected, indexed, cursor-paginated.
            </p>
        </div>

        <form
            class="flex flex-wrap items-end gap-3"
            @submit.prevent="applyFilters"
        >
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="status"
                    >Status</label
                >
                <select
                    id="status"
                    v-model="form.status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">All</option>
                    <option v-for="s in statuses" :key="s" :value="s">
                        {{ titleCase(s) }}
                    </option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="from"
                    >From</label
                >
                <input
                    id="from"
                    v-model="form.from"
                    type="date"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                />
            </div>
            <Button type="submit">Filter</Button>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Event</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Location</th>
                        <th class="px-3 py-2 font-medium">When (local)</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="event in rows"
                        :key="event.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-3 py-2 font-medium">{{ event.name }}</td>
                        <td class="px-3 py-2">{{ titleCase(event.type) }}</td>
                        <td class="px-3 py-2">
                            <Badge :variant="statusVariant(event.status)">{{
                                titleCase(event.status)
                            }}</Badge>
                        </td>
                        <td class="px-3 py-2">{{ event.location.label }}</td>
                        <td class="px-3 py-2">
                            {{ event.time.local_label }} ({{
                                event.time.tz_abbr
                            }})
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Link
                                :href="`/events/${event.id}`"
                                class="text-primary hover:underline"
                                >View</Link
                            >
                        </td>
                    </tr>
                    <tr v-if="!loading && hasLoadedOnce && rows.length === 0">
                        <td
                            colspan="6"
                            class="px-3 py-8 text-center text-muted-foreground"
                        >
                            No events found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div ref="sentinel"></div>

        <div class="py-2 text-sm text-gray-400">
            <span v-if="loading">loading...</span>
            <span v-else-if="hasLoadedOnce"
                >Loaded {{ loadedSize }} in {{ loadedSeconds }}s</span
            >
        </div>
    </div>
</template>
