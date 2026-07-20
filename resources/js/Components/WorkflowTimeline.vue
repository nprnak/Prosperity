<script setup>
import { computed } from 'vue';

/**
 * The audit trail for one record: who acted, at which stage, with what
 * remarks. Grouped by cycle, because an applicant edit retires the previous
 * cycle's sign-offs and reading them as one run would misrepresent the record.
 */
const props = defineProps({
    events: { type: Array, default: () => [] },
    // Newest first reads better in a queue; oldest first tells the story on a
    // detail page.
    order: { type: String, default: 'desc' },
});

const cycles = computed(() => {
    const grouped = new Map();

    for (const event of props.events) {
        const cycle = event.cycle ?? 1;
        if (!grouped.has(cycle)) grouped.set(cycle, []);
        grouped.get(cycle).push(event);
    }

    const sortEvents = (a, b) => (props.order === 'desc' ? b.id - a.id : a.id - b.id);

    return [...grouped.entries()]
        .sort(([a], [b]) => (props.order === 'desc' ? b - a : a - b))
        .map(([cycle, items]) => ({ cycle, items: [...items].sort(sortEvents) }));
});

const latestCycle = computed(() =>
    props.events.reduce((max, event) => Math.max(max, event.cycle ?? 1), 1));

const toneFor = (action) => ({
    approve: 'bg-green-600',
    send_back: 'bg-gray-500',
    return_to_applicant: 'bg-amber-500',
}[action] ?? 'bg-gray-400');

const formatWhen = (value) => {
    if (!value) return '';

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};
</script>

<template>
    <div>
        <p v-if="!events.length" class="text-sm text-gray-600">
            No review activity yet. Actions taken by the verifier, reviewer and approver will appear here.
        </p>

        <div v-for="group in cycles" :key="group.cycle" class="mb-5 last:mb-0">
            <p v-if="latestCycle > 1" class="mb-2 text-sm font-semibold text-gray-700">
                Submission {{ group.cycle }}
                <span v-if="group.cycle === latestCycle" class="font-normal text-gray-600">(current)</span>
                <span v-else class="font-normal text-gray-600">(superseded by a later correction)</span>
            </p>

            <ol class="relative space-y-4 border-l border-gray-200 pl-5">
                <li v-for="event in group.items" :key="event.id" class="relative">
                    <span
                        class="absolute -left-[1.5625rem] top-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-white"
                        :class="toneFor(event.action)"
                        aria-hidden="true"
                    />

                    <p class="text-sm text-gray-900">
                        <span class="font-semibold">{{ event.actor?.name ?? 'Removed user' }}</span>
                        <span class="text-gray-700"> · {{ event.stage_label }}</span>
                        <span class="text-gray-700"> · {{ event.action_label }}</span>
                    </p>

                    <p class="mt-0.5 text-xs text-gray-600">
                        <time :datetime="event.created_at">{{ formatWhen(event.created_at) }}</time>
                    </p>

                    <p class="mt-1 max-w-[70ch] text-sm text-gray-800">{{ event.remarks }}</p>
                </li>
            </ol>
        </div>
    </div>
</template>
