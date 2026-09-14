<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  lots: { type: Object, required: true },
  vehicles: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  staffOptions: { type: Array, default: () => [] },
});

const page = usePage();
const canManage = computed(() => (page.props.auth?.permissions || []).includes('jar.dispatch.manage'));

const form = useForm({ jar_vehicle_id: '', jar_driver_id: '', assigned_staff_id: '', route_area: '', max_jars: 80 });
const submit = () => form.post(route('jar.dispatch.store'));

const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const statusClass = (status) => ({
  loading: 'bg-yellow-100 text-yellow-700',
  dispatched: 'bg-blue-100 text-blue-700',
  returned: 'bg-purple-100 text-purple-700',
  reconciled: 'bg-green-100 text-green-700',
}[status] || 'bg-gray-100 text-gray-700');
</script>

<template>
  <Head title="Vehicle Lots" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Vehicle Lots</h2>
        <p class="mt-1 text-sm text-gray-700">Create a lot, load jars (max 80), then dispatch.</p>
      </div>

      <form v-if="canManage" class="rounded-lg bg-white p-5 shadow" @submit.prevent="submit">
        <h3 class="mb-3 text-sm font-semibold text-gray-900">Create a new lot</h3>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <select v-model="form.jar_vehicle_id" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">Vehicle</option>
            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.vehicle_number }}</option>
          </select>
          <select v-model="form.jar_driver_id" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">Driver (optional)</option>
            <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
          <select v-model="form.assigned_staff_id" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">Assigned staff</option>
            <option v-for="s in staffOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <input v-model="form.route_area" type="text" placeholder="Route/area" class="rounded-md border-gray-300 text-sm shadow-sm" />
          <input v-model.number="form.max_jars" type="number" min="1" max="80" placeholder="Max jars" class="rounded-md border-gray-300 text-sm shadow-sm" />
        </div>
        <PrimaryButton class="mt-3" :disabled="form.processing">Create Lot</PrimaryButton>
      </form>

      <div class="rounded-lg bg-white p-6 shadow">
        <div v-if="lots.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[760px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Lot</th>
                <th class="py-2 px-4 text-left">Vehicle</th>
                <th class="py-2 px-4 text-left">Staff</th>
                <th class="py-2 px-4 text-left">Route</th>
                <th class="py-2 px-4 text-right">Jars</th>
                <th class="py-2 px-4 text-left">Status</th>
                <th class="py-2 px-4 text-right"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lot in lots.data" :key="lot.id" class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-900">{{ lot.lot_code }}</td>
                <td class="py-2 px-4">{{ lot.vehicle?.vehicle_number }}</td>
                <td class="py-2 px-4">{{ lot.assigned_staff?.name }}</td>
                <td class="py-2 px-4">{{ lot.route_area || '—' }}</td>
                <td class="py-2 px-4 text-right">{{ lot.items_count }} / {{ lot.max_jars }}</td>
                <td class="py-2 px-4">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(lot.status)">{{ humanize(lot.status) }}</span>
                </td>
                <td class="py-2 px-4 text-right">
                  <Link :href="route('jar.dispatch.show', lot.id)" class="text-xs font-semibold text-brand-700 hover:text-brand-900">Open →</Link>
                </td>
              </tr>
            </tbody>
          </table>
          <Pagination :meta="lots" label="lots" />
        </div>
        <p v-else class="py-16 text-center text-sm text-gray-500">No vehicle lots yet.</p>
      </div>
    </div>
  </PanelLayout>
</template>
