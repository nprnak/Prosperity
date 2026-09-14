<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
  pendingLots: { type: Array, default: () => [] },
  recentReceipts: { type: Object, default: () => ({ data: [] }) },
});

const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const openReceipt = (lot) => {
  router.post(route('jar.receipts.open', lot.id));
};
</script>

<template>
  <Head title="Factory Receipt" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Factory Receipt</h2>
        <p class="mt-1 text-sm text-gray-700">Scan returned jars against each vehicle's recorded returns — accept or quarantine.</p>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Vehicles out or back at the factory</h3>
        <div v-if="pendingLots.length" class="overflow-x-auto">
          <table class="w-full min-w-[640px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Lot</th>
                <th class="py-2 px-4 text-left">Vehicle</th>
                <th class="py-2 px-4 text-left">Staff</th>
                <th class="py-2 px-4 text-left">Status</th>
                <th class="py-2 px-4 text-right"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lot in pendingLots" :key="lot.id" class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-900">{{ lot.lot_code }}</td>
                <td class="py-2 px-4">{{ lot.vehicle?.vehicle_number }}</td>
                <td class="py-2 px-4">{{ lot.assigned_staff?.name }}</td>
                <td class="py-2 px-4">{{ humanize(lot.status) }}</td>
                <td class="py-2 px-4 text-right">
                  <Link v-if="lot.factory_receipt" :href="route('jar.receipts.show', lot.factory_receipt.id)" class="text-xs font-semibold text-brand-700 hover:text-brand-900">
                    Continue receipt →
                  </Link>
                  <PrimaryButton v-else @click="openReceipt(lot)">Start Receipt</PrimaryButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-12 text-center text-sm text-gray-500">No vehicles are currently dispatched.</p>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Recent receipts</h3>
        <div v-if="recentReceipts.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[640px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Lot</th>
                <th class="py-2 px-4 text-right">Expected</th>
                <th class="py-2 px-4 text-right">Scanned</th>
                <th class="py-2 px-4 text-right">Accepted</th>
                <th class="py-2 px-4 text-right">Quarantined</th>
                <th class="py-2 px-4 text-right"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in recentReceipts.data" :key="r.id" class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-900">{{ r.lot?.lot_code }}</td>
                <td class="py-2 px-4 text-right">{{ r.total_expected }}</td>
                <td class="py-2 px-4 text-right">{{ r.total_scanned }}</td>
                <td class="py-2 px-4 text-right">{{ r.total_accepted }}</td>
                <td class="py-2 px-4 text-right">{{ r.total_quarantined }}</td>
                <td class="py-2 px-4 text-right">
                  <Link :href="route('jar.receipts.show', r.id)" class="text-xs font-semibold text-brand-700 hover:text-brand-900">Open →</Link>
                </td>
              </tr>
            </tbody>
          </table>
          <Pagination :meta="recentReceipts" label="receipts" />
        </div>
        <p v-else class="py-8 text-center text-sm text-gray-500">No receipts recorded yet.</p>
      </div>
    </div>
  </PanelLayout>
</template>
