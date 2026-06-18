<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Clock, MapPin, Ticket } from '@lucide/vue';
import { computed } from 'vue';
import AttendDialog from '@/components/events/AttendDialog.vue';
import ImageCarousel from '@/components/events/ImageCarousel.vue';
import { Badge } from '@/components/ui/badge';
import { useEventTime } from '@/composables/useEventTime';
import { formatPrice, statusVariant, titleCase, typeColor } from '@/lib/events';
import type { EventCard } from '@/types/events';

const props = defineProps<{ event: EventCard }>();

const { viewerLocalLabel, relativeLabel, isUpcoming, showViewerTime } =
    useEventTime(() => props.event.time);

const accent = computed(() => typeColor(props.event.type));
</script>

<template>
    <article
        class="group flex flex-col overflow-hidden rounded-xl border bg-card shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
    >
        <div class="relative aspect-[3/2] w-full">
            <ImageCarousel
                :images="event.images"
                :alt="event.name"
                advance-on-hover
                class="h-full w-full"
            />

            <!-- Category chip -->
            <span
                class="absolute top-3 left-3 rounded-full px-2.5 py-1 text-xs font-semibold text-white shadow-sm backdrop-blur-sm"
                :style="{ backgroundColor: accent }"
            >
                {{ titleCase(event.type) }}
            </span>

            <Badge
                :variant="statusVariant(event.status)"
                class="absolute top-3 right-3 capitalize"
            >
                {{ titleCase(event.status) }}
            </Badge>

            <!-- Relative time pill -->
            <span
                class="absolute bottom-3 left-3 flex items-center gap-1 rounded-full bg-black/55 px-2.5 py-1 text-xs font-medium text-white backdrop-blur-sm"
            >
                <Clock class="size-3" />
                {{ isUpcoming ? relativeLabel : 'Past event' }}
            </span>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <Link
                :href="`/events/${event.id}`"
                class="line-clamp-1 text-base font-semibold hover:underline"
            >
                {{ event.name }}
            </Link>

            <p
                v-if="event.description"
                class="line-clamp-2 text-sm text-muted-foreground"
            >
                {{ event.description }}
            </p>

            <div class="mt-auto flex flex-col gap-1.5 text-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <MapPin
                        class="size-4 shrink-0"
                        :style="{ color: accent }"
                    />
                    <span class="truncate"
                        >{{ event.venue }} · {{ event.location.label }}</span
                    >
                </div>
                <div class="flex items-center gap-2 text-muted-foreground">
                    <CalendarDays
                        class="size-4 shrink-0"
                        :style="{ color: accent }"
                    />
                    <span class="truncate">
                        {{ event.time.local_label }}
                        <span class="text-xs">({{ event.time.tz_abbr }})</span>
                    </span>
                </div>
                <div
                    v-if="showViewerTime"
                    class="flex items-center gap-2 pl-6 text-xs text-muted-foreground/80"
                >
                    Your time: {{ viewerLocalLabel }}
                </div>
            </div>

            <div class="flex items-center justify-between border-t pt-3">
                <span class="flex items-center gap-1.5 text-sm font-semibold">
                    <Ticket class="size-4" :style="{ color: accent }" />
                    {{ formatPrice(event) }}
                </span>
                <AttendDialog :event="event" />
            </div>
        </div>
    </article>
</template>
