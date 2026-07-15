<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
  logs: Object,
  logNames: Array,
  filters: Object,
});

const form = reactive({
  log_name: props.filters?.log_name || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
});

const search = () => {
  router.get(route('admin.logs'), Object.fromEntries(Object.entries(form).filter(([, v]) => v)), {
    preserveState: true,
    preserveScroll: true,
  });
};

const eventClass = (event) => {
  const map = {
    login: 'bg-blue-100 text-blue-700',
    logout: 'bg-gray-100 text-gray-700',
    login_failed: 'bg-red-100 text-red-700',
    created: 'bg-green-100 text-green-700',
    updated: 'bg-yellow-100 text-yellow-700',
    deleted: 'bg-red-100 text-red-700',
  };
  return map[event] || 'bg-indigo-100 text-indigo-700';
};

const browserOf = (userAgent) => {
  if (!userAgent) return '—';
  if (userAgent.includes('Edg/')) return 'Edge';
  if (userAgent.includes('Chrome/')) return 'Chrome';
  if (userAgent.includes('Firefox/')) return 'Firefox';
  if (userAgent.includes('Safari/')) return 'Safari';
  return userAgent.slice(0, 30);
};
</script>

<template>
  <Head title="Admin - Activity Log" />
  <PanelLayout>
    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Activity Log</h2>

      <!-- Filters -->
      <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Log</label>
          <select v-model="form.log_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            <option value="">All Logs</option>
            <option v-for="name in logNames" :key="name" :value="name">{{ name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
          <input v-model="form.date_from" type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
          <input v-model="form.date_to" type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
        </div>
        <div>
          <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700" @click="search">Search</button>
        </div>
      </div>

      <!-- Activity Table -->
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Log</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Browser</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ log.created_at }}</td>
              <td class="px-4 py-3 text-sm text-gray-900">{{ log.causer || 'System' }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ log.log_name }}</td>
              <td class="px-4 py-3 text-sm">
                <span v-if="log.event" class="px-2 py-1 rounded text-xs font-semibold uppercase" :class="eventClass(log.event)">
                  {{ log.event }}
                </span>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ log.description }}</td>
              <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ log.ip || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-600" :title="log.user_agent">{{ browserOf(log.user_agent) }}</td>
            </tr>
            <tr v-if="!logs.data.length">
              <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No activity recorded for these filters.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm text-gray-600">
          Showing {{ logs.from || 0 }} to {{ logs.to || 0 }} of {{ logs.total }} entries
        </span>
        <div class="flex flex-wrap gap-1">
          <template v-for="(link, i) in logs.links" :key="i">
            <Link v-if="link.url" :href="link.url" preserve-scroll
                class="px-3 py-1.5 text-sm border rounded"
                :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 hover:bg-gray-50'"
                v-html="link.label" />
            <span v-else class="px-3 py-1.5 text-sm border border-gray-200 rounded text-gray-400" v-html="link.label" />
          </template>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
