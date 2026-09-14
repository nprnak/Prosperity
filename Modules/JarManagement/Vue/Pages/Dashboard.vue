<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
  BeakerIcon, TruckIcon, CheckCircleIcon, ArrowUturnLeftIcon,
  ArchiveBoxArrowDownIcon, ExclamationTriangleIcon, MapPinIcon, UserGroupIcon,
  BanknotesIcon, ClockIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  vehicleStatus: { type: Array, default: () => [] },
  productionStatus: { type: Array, default: () => [] },
  jarConditionBreakdown: { type: Object, default: () => ({}) },
  reconciliationBreakdown: { type: Object, default: () => ({}) },
});

const fmt = (v) => Number(v || 0).toLocaleString('en-IN');

const kpiCards = computed(() => [
  { label: 'Produced Today', value: fmt(props.summary.produced_today), icon: BeakerIcon, badgeClass: 'bg-blue-50 text-blue-700' },
  { label: 'Dispatched Today', value: fmt(props.summary.dispatched_today), icon: TruckIcon, badgeClass: 'bg-purple-50 text-purple-700' },
  { label: 'Delivered Today', value: fmt(props.summary.delivered_today), icon: CheckCircleIcon, badgeClass: 'bg-emerald-50 text-emerald-700' },
  { label: 'Empty Returned', value: fmt(props.summary.empty_returned_today), icon: ArrowUturnLeftIcon, badgeClass: 'bg-amber-50 text-amber-700' },
  { label: 'Factory Accepted', value: fmt(props.summary.factory_accepted_today), icon: ArchiveBoxArrowDownIcon, badgeClass: 'bg-teal-50 text-teal-700' },
  { label: 'Quarantined', value: fmt(props.summary.quarantined_today), icon: ExclamationTriangleIcon, badgeClass: 'bg-red-50 text-red-700' },
  { label: 'Currently in Vehicles', value: fmt(props.summary.currently_in_vehicles), icon: MapPinIcon, badgeClass: 'bg-slate-100 text-slate-700' },
  { label: 'Customer Outstanding Jars', value: fmt(props.summary.customer_outstanding_jars), icon: UserGroupIcon, badgeClass: 'bg-orange-50 text-orange-700' },
  { label: 'Active Trips', value: fmt(props.summary.active_trips), icon: ClockIcon, badgeClass: 'bg-indigo-50 text-indigo-700' },
  { label: "Today's Revenue", value: `Rs. ${fmt(props.summary.revenue_today)}`, icon: BanknotesIcon, badgeClass: 'bg-emerald-50 text-emerald-700' },
]);

const reconciliationEntries = computed(() => Object.entries(props.reconciliationBreakdown || {}));
const reconciliationLabel = { settled: 'Settled', outstanding: 'Outstanding', excess: 'Excess Returned' };
const reconciliationClass = { settled: 'text-green-700', outstanding: 'text-amber-700', excess: 'text-red-700' };

// Enum-cast attributes come back from Eloquent's JSON serialization as their
// plain backing string (e.g. "pending_approval"), not an object — labels are
// formatted client-side rather than assuming a {value,label} shape.
const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const lotStatusClass = (status) => ({
  loading: 'bg-yellow-100 text-yellow-700',
  dispatched: 'bg-blue-100 text-blue-700',
  returned: 'bg-purple-100 text-purple-700',
  reconciled: 'bg-green-100 text-green-700',
}[status] || 'bg-gray-100 text-gray-700');

const batchStatusClass = (status) => ({
  open: 'bg-yellow-100 text-yellow-700',
  pending_approval: 'bg-blue-100 text-blue-700',
  completed: 'bg-green-100 text-green-700',
}[status] || 'bg-gray-100 text-gray-700');

const conditionEntries = computed(() => Object.entries(props.jarConditionBreakdown || {}));
</script>

<template>
  <Head title="Jar Operations Dashboard" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Water Jar Operations</h2>
        <p class="mt-1 text-sm text-gray-700">Live status of production, dispatch, delivery and returns.</p>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="card in kpiCards" :key="card.label" class="rounded-lg bg-white p-5 shadow">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="card.badgeClass">
              <component :is="card.icon" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
              <p class="text-xs font-medium text-gray-700">{{ card.label }}</p>
              <p class="truncate text-lg font-bold text-gray-900">{{ card.value }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-lg bg-white p-6 shadow lg:col-span-2">
          <div class="mb-4 flex items-baseline justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Today's Vehicle Lots</h3>
            <Link :href="route('jar.dispatch.index')" class="text-xs font-semibold text-brand-700 hover:text-brand-900">Manage lots →</Link>
          </div>
          <div v-if="vehicleStatus.length" class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
              <thead>
                <tr class="border-b text-xs uppercase text-gray-500">
                  <th class="py-2 pr-4 text-left">Lot</th>
                  <th class="py-2 px-4 text-left">Vehicle</th>
                  <th class="py-2 px-4 text-left">Staff</th>
                  <th class="py-2 px-4 text-left">Route</th>
                  <th class="py-2 px-4 text-right">Jars</th>
                  <th class="py-2 px-4 text-left">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in vehicleStatus" :key="row.lot_code" class="border-b last:border-0">
                  <td class="py-2 pr-4 font-medium text-gray-900">{{ row.lot_code }}</td>
                  <td class="py-2 px-4">{{ row.vehicle }}</td>
                  <td class="py-2 px-4">{{ row.staff }}</td>
                  <td class="py-2 px-4">{{ row.route_area || '—' }}</td>
                  <td class="py-2 px-4 text-right">{{ row.jars_loaded }}</td>
                  <td class="py-2 px-4">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="lotStatusClass(row.status)">
                      {{ humanize(row.status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="py-16 text-center text-sm text-gray-500">No vehicle lots created today.</p>
        </div>

        <div class="space-y-6">
          <div class="rounded-lg bg-white p-6 shadow">
            <h3 class="mb-4 text-lg font-semibold text-gray-900">Jar Condition</h3>
            <div v-if="conditionEntries.length" class="space-y-2">
              <div v-for="[condition, count] in conditionEntries" :key="condition" class="flex items-center justify-between text-sm">
                <span class="capitalize text-gray-700">{{ condition.replace('_', ' ') }}</span>
                <span class="font-semibold text-gray-900">{{ fmt(count) }}</span>
              </div>
            </div>
            <p v-else class="py-8 text-center text-sm text-gray-500">No jars registered yet.</p>
          </div>

          <div class="rounded-lg bg-white p-6 shadow">
            <h3 class="mb-4 text-lg font-semibold text-gray-900">Today's Reconciliation</h3>
            <div v-if="reconciliationEntries.length" class="space-y-2">
              <div v-for="[status, count] in reconciliationEntries" :key="status" class="flex items-center justify-between text-sm">
                <span :class="reconciliationClass[status] || 'text-gray-700'">{{ reconciliationLabel[status] || humanize(status) }}</span>
                <span class="font-semibold text-gray-900">{{ fmt(count) }}</span>
              </div>
            </div>
            <p v-else class="py-8 text-center text-sm text-gray-500">No deliveries recorded today.</p>
          </div>
        </div>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <div class="mb-4 flex items-baseline justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Today's Production Batches</h3>
          <Link :href="route('jar.production.index')" class="text-xs font-semibold text-brand-700 hover:text-brand-900">Manage batches →</Link>
        </div>
        <div v-if="productionStatus.length" class="overflow-x-auto">
          <table class="w-full min-w-[480px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Batch</th>
                <th class="py-2 px-4 text-right">Jars Scanned</th>
                <th class="py-2 px-4 text-left">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="batch in productionStatus" :key="batch.id" class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-900">{{ batch.batch_code }}</td>
                <td class="py-2 px-4 text-right">{{ batch.items_count }}</td>
                <td class="py-2 px-4">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="batchStatusClass(batch.status)">
                    {{ humanize(batch.status) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-16 text-center text-sm text-gray-500">No batches created today.</p>
      </div>
    </div>
  </PanelLayout>
</template>
