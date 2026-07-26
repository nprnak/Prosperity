<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Navigation across the report formats. Rendered on every report page,
 * including the detailed applications report, which is reached by its own
 * route and so has no registry key of its own.
 */
defineProps({
  reports: { type: Array, default: () => [] },
  // Registry key of the open report, or 'applications' for the detailed one.
  current: { type: String, default: '' },
});
</script>

<template>
  <nav class="flex flex-wrap gap-2">
    <Link
      :href="route('admin.reports')"
      class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
      :class="current === 'applications'
        ? 'border-gray-900 bg-gray-900 text-white'
        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
    >
      Applications (detailed)
    </Link>
    <Link
      v-for="report in reports"
      :key="report.key"
      :href="route('admin.reports.show', report.key)"
      class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
      :class="current === report.key
        ? 'border-gray-900 bg-gray-900 text-white'
        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
      :title="report.description"
    >
      {{ report.title }}
    </Link>
  </nav>
</template>
