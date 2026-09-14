<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
  batches: { type: Object, required: true },
});

const form = useForm({ production_date: '' });
const submit = () => form.post(route('jar.production.store'));

const registerForm = useForm({ count: 10 });
const registerJars = () => registerForm.post(route('jar.production.register-jars'), { preserveScroll: true });

const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const statusClass = (status) => ({
  open: 'bg-yellow-100 text-yellow-700',
  pending_approval: 'bg-blue-100 text-blue-700',
  completed: 'bg-green-100 text-green-700',
}[status] || 'bg-gray-100 text-gray-700');
</script>

<template>
  <Head title="Production Batches" />
  <PanelLayout>
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-2xl font-bold text-gray-900">Production Batches</h2>
          <p class="mt-1 text-sm text-gray-700">Cleaning → Refilling → Sealing → Scan → Quality Approval.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <form class="rounded-lg bg-white p-5 shadow" @submit.prevent="submit">
          <h3 class="mb-3 text-sm font-semibold text-gray-900">Start a new batch</h3>
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="mb-1 block text-xs font-medium text-gray-700">Production date</label>
              <input v-model="form.production_date" type="date" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-brand focus:ring-brand" />
            </div>
            <PrimaryButton :disabled="form.processing">Create Batch</PrimaryButton>
          </div>
        </form>

        <form class="rounded-lg bg-white p-5 shadow" @submit.prevent="registerJars">
          <h3 class="mb-3 text-sm font-semibold text-gray-900">Register new jar codes</h3>
          <p class="mb-3 text-xs text-gray-600">Mints fresh jar codes to print as labels before they're ever scanned into a batch.</p>
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="mb-1 block text-xs font-medium text-gray-700">How many</label>
              <input v-model.number="registerForm.count" type="number" min="1" max="200" class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-brand focus:ring-brand" />
            </div>
            <PrimaryButton :disabled="registerForm.processing">Register</PrimaryButton>
          </div>
        </form>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <div v-if="batches.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[640px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Batch</th>
                <th class="py-2 px-4 text-left">Production Date</th>
                <th class="py-2 px-4 text-right">Jars</th>
                <th class="py-2 px-4 text-left">Status</th>
                <th class="py-2 px-4 text-right"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="batch in batches.data" :key="batch.id" class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-900">{{ batch.batch_code }}</td>
                <td class="py-2 px-4">{{ batch.production_date }}</td>
                <td class="py-2 px-4 text-right">{{ batch.items_count }}</td>
                <td class="py-2 px-4">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(batch.status)">{{ humanize(batch.status) }}</span>
                </td>
                <td class="py-2 px-4 text-right">
                  <Link :href="route('jar.production.show', batch.id)" class="text-xs font-semibold text-brand-700 hover:text-brand-900">Open →</Link>
                </td>
              </tr>
            </tbody>
          </table>
          <Pagination :meta="batches" label="batches" />
        </div>
        <p v-else class="py-16 text-center text-sm text-gray-500">No production batches yet.</p>
      </div>
    </div>
  </PanelLayout>
</template>
