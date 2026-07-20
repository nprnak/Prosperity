<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Global flash surface. Every controller already redirects back with
 * ->with('success', ...), and refused workflow transitions come back as a
 * 'workflow' validation error, so both land here without per-page wiring.
 */
const page = usePage();
const toasts = ref([]);
const timers = new Map();

let nextId = 0;

const DURATION = 5000;

const dismiss = (id) => {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);

    if (timers.has(id)) {
        clearTimeout(timers.get(id));
        timers.delete(id);
    }
};

const push = (type, message) => {
    if (!message) return;

    // Re-flashing the same message shouldn't stack duplicates.
    if (toasts.value.some((toast) => toast.message === message)) return;

    const id = nextId++;
    toasts.value.push({ id, type, message });
    timers.set(id, setTimeout(() => dismiss(id), DURATION));
};

watch(
    () => page.props.flash?.success,
    (message) => push('success', message),
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (message) => push('error', message),
    { immediate: true },
);

// A refused transition (wrong stage, act-once, missing remarks) is the case
// users most need told about, and it arrives as a validation error.
watch(
    () => page.props.errors?.workflow,
    (message) => push('error', message),
    { immediate: true },
);

onBeforeUnmount(() => {
    timers.forEach((timer) => clearTimeout(timer));
    timers.clear();
});

const styles = computed(() => ({
    success: 'border-green-200 bg-green-50 text-green-800',
    error: 'border-red-200 bg-red-50 text-red-800',
}));
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-4 sm:items-end"
        role="status"
        aria-live="polite"
    >
        <TransitionGroup
            enter-from-class="translate-y-[-0.5rem] opacity-0"
            enter-active-class="transition duration-200 ease-out"
            leave-to-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg"
                :class="styles[toast.type]"
            >
                <span class="flex-1">{{ toast.message }}</span>
                <button
                    type="button"
                    class="shrink-0 font-semibold opacity-60 transition hover:opacity-100"
                    aria-label="Dismiss notification"
                    @click="dismiss(toast.id)"
                >
                    &times;
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
