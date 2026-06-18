<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Clock,
    MapPin,
    Ticket,
    Users,
} from '@lucide/vue';
import AttendDialog from '@/components/events/AttendDialog.vue';
import ImageCarousel from '@/components/events/ImageCarousel.vue';
import { Badge } from '@/components/ui/badge';
import { useEventTime } from '@/composables/useEventTime';
import { formatPrice, statusVariant, titleCase, typeColor } from '@/lib/events';
import type { EventCard } from '@/types/events';

const props = defineProps<{ event: EventCard }>();

const { viewerLocalLabel, relativeLabel, isUpcoming, showViewerTime } =
    useEventTime(() => props.event.time);
</script>

<template>
    <Head :title="event.name" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <Link
            href="/events-visual-1"
            class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Back to events
        </Link>

        <div class="overflow-hidden rounded-2xl border shadow-sm">
            <ImageCarousel
                :images="event.images"
                :alt="event.name"
                class="aspect-video w-full"
            />
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold text-white"
                    :style="{ backgroundColor: typeColor(event.type) }"
                >
                    {{ titleCase(event.type) }}
                </span>
                <Badge
                    :variant="statusVariant(event.status)"
                    class="capitalize"
                    >{{ titleCase(event.status) }}</Badge
                >
                <span
                    v-if="isUpcoming"
                    class="flex items-center gap-1 text-sm text-muted-foreground"
                >
                    <Clock class="size-4" /> {{ relativeLabel }}
                </span>
            </div>

            <h1 class="text-3xl font-bold tracking-tight">{{ event.name }}</h1>

            <p v-if="event.description" class="text-muted-foreground">
                {{ event.description }}
            </p>

            <div
                class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2"
            >
                <div class="flex items-start gap-3">
                    <MapPin class="mt-0.5 size-5 text-primary" />
                    <div>
                        <p class="text-sm font-medium">{{ event.venue }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ event.location.label }}
                        </p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <CalendarDays class="mt-0.5 size-5 text-primary" />
                    <div>
                        <p class="text-sm font-medium">
                            {{ event.time.local_label }} ({{
                                event.time.tz_abbr
                            }})
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Event-local time · {{ event.time.timezone }}
                        </p>
                        <p
                            v-if="showViewerTime"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Your time: {{ viewerLocalLabel }}
                        </p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <Ticket class="mt-0.5 size-5 text-primary" />
                    <div>
                        <p class="text-sm font-medium">
                            {{ formatPrice(event) }}
                        </p>
                        <p class="text-sm text-muted-foreground">From price</p>
                    </div>
                </div>
                <div
                    v-if="event.attendees_count !== undefined"
                    class="flex items-start gap-3"
                >
                    <Users class="mt-0.5 size-5 text-primary" />
                    <div>
                        <p class="text-sm font-medium">
                            {{ event.attendees_count.toLocaleString() }}
                            attending
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Register to join them
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <AttendDialog :event="event" />
                <span class="text-sm text-muted-foreground"
                    >Free cancellation · confirmation email sent instantly</span
                >
            </div>
        </div>
    </div>
</template>
