<script setup>
defineProps({
    tabs: { type: Array, required: true }, // [{ key, label, count }]
    modelValue: { type: String, required: true },
    search: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'update:search', 'search']);
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200">
        <nav class="flex gap-1" aria-label="Tabs">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                @click="emit('update:modelValue', tab.key)"
                :class="[
                    'border-b-2 px-3 py-2 text-sm font-medium transition',
                    modelValue === tab.key
                        ? 'border-brand text-brand'
                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700',
                ]"
            >
                {{ tab.label }}
                <span
                    class="ml-1.5 rounded-full px-1.5 py-0.5 text-xs"
                    :class="modelValue === tab.key ? 'bg-brand-50 text-brand' : 'bg-slate-100 text-slate-500'"
                >{{ tab.count }}</span>
            </button>
        </nav>

        <form class="flex gap-2 pb-2" @submit.prevent="emit('search')">
            <input
                :value="search"
                @input="emit('update:search', $event.target.value)"
                type="search"
                placeholder="Search by name, number..."
                class="w-56 rounded-md border-slate-300 text-sm focus:border-brand focus:ring-brand"
            />
            <button
                type="submit"
                class="rounded-md bg-brand px-3 py-1.5 text-sm font-medium text-white transition hover:bg-brand-600"
            >
                Search
            </button>
        </form>
    </div>
</template>
