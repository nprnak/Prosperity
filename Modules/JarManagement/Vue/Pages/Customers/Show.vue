<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  customer: { type: Object, required: true },
});

const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
  <Head :title="customer.name" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">{{ customer.name }}</h2>
        <p class="mt-1 text-sm text-gray-700">
          {{ customer.phone || 'No phone' }} · {{ humanize(customer.type) }} · {{ customer.route_area || 'No route' }}
        </p>
        <p class="mt-1 text-sm font-semibold text-amber-700">Outstanding jars: {{ customer.jars_outstanding }}</p>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Delivery history</h3>
        <div v-if="customer.deliveries?.length" class="overflow-x-auto">
          <table class="w-full min-w-[720px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Date</th>
                <th class="py-2 px-4 text-right">Delivered</th>
                <th class="py-2 px-4 text-right">Collected</th>
                <th class="py-2 px-4 text-left">Reconciliation</th>
                <th class="py-2 px-4 text-right">Amount</th>
                <th class="py-2 px-4 text-left">Payment</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in customer.deliveries" :key="d.id" class="border-b last:border-0">
                <td class="py-2 pr-4">{{ d.delivered_at }}</td>
                <td class="py-2 px-4 text-right">{{ d.filled_jars_delivered_count }}</td>
                <td class="py-2 px-4 text-right">{{ d.empty_jars_collected_count }}</td>
                <td class="py-2 px-4">{{ humanize(d.reconciliation_status) }}</td>
                <td class="py-2 px-4 text-right">{{ d.total_amount }}</td>
                <td class="py-2 px-4">{{ humanize(d.payment_status) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-12 text-center text-sm text-gray-500">No deliveries recorded yet.</p>
      </div>
    </div>
  </PanelLayout>
</template>
