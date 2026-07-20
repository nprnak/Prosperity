<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Renders a Laravel paginator's link set. Hidden entirely on a single page,
 * so a short queue doesn't carry dead chrome.
 */
const props = defineProps({
    // A paginator serialised by Inertia: { data, links, from, to, total }.
    meta: { type: Object, required: true },
    label: { type: String, default: 'results' },
});

const links = computed(() => props.meta?.links ?? []);
const hasPages = computed(() => links.value.length > 3);
</script>

<template>
    <nav
        v-if="hasPages"
        class="mt-4 flex flex-wrap items-center justify-between gap-3"
        :aria-label="`${label} pagination`"
    >
        <p class="text-sm text-gray-700">
            Showing <span class="font-medium">{{ meta.from }}</span>–<span class="font-medium">{{ meta.to }}</span>
            of <span class="font-medium">{{ meta.total }}</span> {{ label }}
        </p>

        <ul class="flex flex-wrap items-center gap-1">
            <li v-for="(link, index) in links" :key="index">
                <span
                    v-if="!link.url"
                    class="inline-flex min-w-9 justify-center rounded-lg px-3 py-1.5 text-sm text-gray-500"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    :aria-current="link.active ? 'page' : undefined"
                    class="inline-flex min-w-9 justify-center rounded-lg px-3 py-1.5 text-sm font-medium transition duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                    :class="link.active
                        ? 'bg-blue-600 text-white'
                        : 'text-gray-700 hover:bg-gray-100 active:bg-gray-200'"
                    v-html="link.label"
                />
            </li>
        </ul>
    </nav>
</template>
