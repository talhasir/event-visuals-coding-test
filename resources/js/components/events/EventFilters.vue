<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { reactive, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { titleCase } from '@/lib/events';
import type { EventFilterState, FilterOptions } from '@/types/events';

defineProps<{ options: FilterOptions }>();
const emit = defineEmits<{ (e: 'change', value: EventFilterState): void }>();

const state = reactive<EventFilterState>({
    q: '',
    from: '',
    to: '',
    type: '',
    status: '',
    city: '',
});

const emitChange = useDebounceFn(() => emit('change', { ...state }), 250);

// Any filter change re-queries (debounced so typing doesn't spam the server).
watch(state, emitChange);

function clear() {
    Object.assign(state, {
        q: '',
        from: '',
        to: '',
        type: '',
        status: '',
        city: '',
    });
}

const hasActiveFilters = () => Object.values(state).some(Boolean);

defineExpose({ state });
</script>

<template>
    <div class="flex flex-wrap items-end gap-3">
        <!-- Search -->
        <div class="relative min-w-48 flex-1">
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="state.q"
                placeholder="Search events…"
                class="pl-9"
            />
        </div>

        <!-- Date range -->
        <div class="flex flex-col gap-1">
            <label
                class="text-xs font-medium text-muted-foreground"
                for="filter-from"
                >From</label
            >
            <Input
                id="filter-from"
                v-model="state.from"
                type="date"
                class="w-38"
            />
        </div>
        <div class="flex flex-col gap-1">
            <label
                class="text-xs font-medium text-muted-foreground"
                for="filter-to"
                >To</label
            >
            <Input id="filter-to" v-model="state.to" type="date" class="w-38" />
        </div>

        <!-- Location -->
        <div class="flex flex-col gap-1">
            <label
                class="text-xs font-medium text-muted-foreground"
                for="filter-city"
                >Location</label
            >
            <select
                id="filter-city"
                v-model="state.city"
                class="h-9 w-48 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
            >
                <option value="">All locations</option>
                <option
                    v-for="c in options.cities"
                    :key="c.label"
                    :value="c.label"
                >
                    {{ c.label }}
                </option>
            </select>
        </div>

        <!-- Category -->
        <div class="flex flex-col gap-1">
            <label
                class="text-xs font-medium text-muted-foreground"
                for="filter-type"
                >Category</label
            >
            <select
                id="filter-type"
                v-model="state.type"
                class="h-9 w-40 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
            >
                <option value="">All categories</option>
                <option v-for="t in options.types" :key="t" :value="t">
                    {{ titleCase(t) }}
                </option>
            </select>
        </div>

        <!-- Status -->
        <div class="flex flex-col gap-1">
            <label
                class="text-xs font-medium text-muted-foreground"
                for="filter-status"
                >Status</label
            >
            <select
                id="filter-status"
                v-model="state.status"
                class="h-9 w-36 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
            >
                <option value="">Any status</option>
                <option v-for="s in options.statuses" :key="s" :value="s">
                    {{ titleCase(s) }}
                </option>
            </select>
        </div>

        <Button
            v-if="hasActiveFilters()"
            variant="ghost"
            size="sm"
            @click="clear"
        >
            <X class="size-4" />
            Clear
        </Button>
    </div>
</template>
