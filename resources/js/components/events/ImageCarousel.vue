<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        images: string[];
        alt?: string;
        /** Advance to the next image on hover (nice for dense card grids). */
        advanceOnHover?: boolean;
        class?: string;
    }>(),
    { alt: '', advanceOnHover: false },
);

const index = ref(0);
const count = computed(() => props.images.length);

function go(to: number) {
    if (count.value === 0) {
        return;
    }

    index.value = (to + count.value) % count.value;
}

function onHover() {
    if (props.advanceOnHover && count.value > 1) {
        go(index.value + 1);
    }
}
</script>

<template>
    <div
        :class="
            cn('group/carousel relative overflow-hidden bg-muted', props.class)
        "
        @mouseenter="onHover"
    >
        <!-- Slides -->
        <div
            class="flex h-full w-full transition-transform duration-500 ease-out"
            :style="{ transform: `translateX(-${index * 100}%)` }"
        >
            <img
                v-for="(src, i) in images"
                :key="src"
                :src="src"
                :alt="alt"
                loading="lazy"
                class="h-full w-full shrink-0 object-cover"
                :class="{ 'scale-105': i === index }"
            />
        </div>

        <!-- Controls (only when multiple) -->
        <template v-if="count > 1">
            <button
                type="button"
                aria-label="Previous image"
                class="absolute top-1/2 left-2 -translate-y-1/2 rounded-full bg-black/40 p-1 text-white opacity-0 backdrop-blur-sm transition group-hover/carousel:opacity-100 hover:bg-black/60"
                @click.stop.prevent="go(index - 1)"
            >
                <ChevronLeft class="size-4" />
            </button>
            <button
                type="button"
                aria-label="Next image"
                class="absolute top-1/2 right-2 -translate-y-1/2 rounded-full bg-black/40 p-1 text-white opacity-0 backdrop-blur-sm transition group-hover/carousel:opacity-100 hover:bg-black/60"
                @click.stop.prevent="go(index + 1)"
            >
                <ChevronRight class="size-4" />
            </button>

            <!-- Dots -->
            <div
                class="absolute right-0 bottom-2 left-0 flex justify-center gap-1.5"
            >
                <button
                    v-for="(src, i) in images"
                    :key="`dot-${src}`"
                    type="button"
                    :aria-label="`Go to image ${i + 1}`"
                    class="h-1.5 rounded-full bg-white/60 transition-all"
                    :class="
                        i === index ? 'w-5 bg-white' : 'w-1.5 hover:bg-white/80'
                    "
                    @click.stop.prevent="go(i)"
                />
            </div>
        </template>
    </div>
</template>
