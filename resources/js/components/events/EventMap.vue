<script setup lang="ts">
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import { useDebounceFn } from '@vueuse/core';
import L from 'leaflet';
import 'leaflet.markercluster';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { typeColor } from '@/lib/events';
import type { EventCard } from '@/types/events';

const props = defineProps<{ events: EventCard[]; selectedId: string | null }>();
const emit = defineEmits<{
    (
        e: 'bounds',
        value: { north: number; south: number; east: number; west: number },
    ): void;
    (e: 'select', id: string): void;
}>();

const el = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let cluster: L.MarkerClusterGroup | null = null;
const markers = new Map<string, L.Marker>();

function emitBounds() {
    if (!map) {
        return;
    }

    const b = map.getBounds();
    emit('bounds', {
        north: b.getNorth(),
        south: b.getSouth(),
        east: b.getEast(),
        west: b.getWest(),
    });
}

const debouncedBounds = useDebounceFn(emitBounds, 350);

function pinIcon(color: string, selected = false): L.DivIcon {
    const size = selected ? 22 : 16;

    return L.divIcon({
        className: 'event-pin',
        html: `<span style="
            display:block;width:${size}px;height:${size}px;border-radius:9999px;
            background:${color};border:2px solid #fff;
            box-shadow:0 0 0 ${selected ? 4 : 2}px ${color}55, 0 1px 3px rgba(0,0,0,.4);
            transition:all .15s ease;"></span>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
    });
}

function render() {
    if (!map || !cluster) {
        return;
    }

    cluster.clearLayers();
    markers.clear();

    for (const event of props.events) {
        const marker = L.marker([event.location.lat, event.location.lng], {
            icon: pinIcon(typeColor(event.type), event.id === props.selectedId),
        });
        marker.bindTooltip(`${event.name} · ${event.location.label}`, {
            direction: 'top',
            offset: [0, -8],
        });
        marker.on('click', () => emit('select', event.id));
        markers.set(event.id, marker);
        cluster.addLayer(marker);
    }
}

onMounted(() => {
    if (!el.value) {
        return;
    }

    map = L.map(el.value, { worldCopyJump: true, zoomControl: true }).setView(
        [30, 0],
        2,
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    cluster = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 50,
    });
    map.addLayer(cluster);

    map.on('moveend', debouncedBounds);
    // Kick off the first load once the map knows its size.
    setTimeout(() => {
        map?.invalidateSize();
        emitBounds();
    }, 0);
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});

watch(() => props.events, render, { deep: false });

// Re-style markers when the selection changes, and pan to the selected event.
watch(
    () => props.selectedId,
    (id) => {
        markers.forEach((marker, key) =>
            marker.setIcon(pinIcon(typeColor(eventType(key)), key === id)),
        );

        if (id) {
            const event = props.events.find((e) => e.id === id);

            if (event && map) {
                map.panTo([event.location.lat, event.location.lng]);
            }
        }
    },
);

function eventType(id: string): string {
    return props.events.find((e) => e.id === id)?.type ?? 'concert';
}
</script>

<template>
    <div ref="el" class="h-full w-full" />
</template>
