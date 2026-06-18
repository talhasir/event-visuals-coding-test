<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CalendarCheck } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EventCard } from '@/types/events';

const props = defineProps<{ event: EventCard }>();

const open = ref(false);

const form = useForm({ name: '', email: '' });

function submit() {
    form.post(`/events/${props.event.id}/attendees`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                "You're on the list! Check your email for confirmation.",
            );
            form.reset();
            open.value = false;
        },
        onError: () => {
            // Field errors render inline; nudge with a toast too.
            toast.error('Please check the form and try again.');
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <Button size="sm" @click="open = true">
            <CalendarCheck class="size-4" />
            Attend
        </Button>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>Register for {{ event.name }}</DialogTitle>
                <DialogDescription>
                    {{ event.time.local_label }} · {{ event.location.label }}.
                    We'll email you a confirmation and a reminder as the date
                    approaches.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="attendee-name">Name</Label>
                    <Input
                        id="attendee-name"
                        v-model="form.name"
                        placeholder="Ada Lovelace"
                        autocomplete="name"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="attendee-email">Email</Label>
                    <Input
                        id="attendee-email"
                        v-model="form.email"
                        type="email"
                        placeholder="ada@example.com"
                        autocomplete="email"
                    />
                    <p
                        v-if="form.errors.email"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? 'Registering…'
                                : 'Confirm attendance'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
