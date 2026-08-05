<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
  metrics: Object,
  capitalSeries: Array,
  // Top focal persons by shares credited; empty for staff without report.view.
  focalPersons: { type: Array, default: () => [] },
});
</script>

<template>
  <Head title="Admin Dashboard" />
  <PanelLayout>
    <div class="bg-white rounded-lg shadow p-6 space-y-6">
      <h2 class="text-3xl font-bold text-gray-900">Capital Raised Analytics</h2>
      <div class="grid grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded shadow p-4 border border-blue-200">
          <div class="text-gray-500 text-sm">Capital Raised</div>
          <div class="text-3xl font-bold text-blue-600 mt-2">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ metrics?.capitalRaised || 0 }}</div>
        </div>
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded shadow p-4 border border-orange-200">
          <div class="text-gray-500 text-sm">Pending Applications</div>
          <div class="text-3xl font-bold text-orange-600 mt-2">{{ metrics?.pendingApplications || 0 }}</div>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded shadow p-4 border border-purple-200">
          <div class="text-gray-500 text-sm">Pending Payment Verification</div>
          <div class="text-3xl font-bold text-purple-600 mt-2">{{ metrics?.pendingPaymentVerification || 0 }}</div>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded shadow p-4 border border-green-200">
          <div class="text-gray-500 text-sm">Shares Allotted</div>
          <div class="text-3xl font-bold text-green-600 mt-2">{{ metrics?.totalSharesAllotted || 0 }}</div>
        </div>
      </div>
    </div>

    <div v-if="focalPersons.length" class="mt-6 bg-white rounded-lg shadow p-6">
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-xl font-semibold text-gray-900">Top Focal Persons</h3>
        <Link :href="route('admin.reports.show', 'focal-persons')" class="text-xs font-semibold text-blue-700 hover:text-blue-900">
          Full report →
        </Link>
      </div>

      <table class="mt-4 w-full text-sm">
        <thead>
          <tr class="border-b text-xs uppercase text-gray-500">
            <th class="py-2 text-left">Focal Person</th>
            <th class="py-2 text-left">Code</th>
            <th class="py-2 text-right">Shares</th>
            <th class="py-2 text-right">Deposit</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="person in focalPersons" :key="person.name">
            <td class="py-2 text-gray-900">{{ person.name }}</td>
            <td class="py-2 font-mono text-gray-600">{{ person.code }}</td>
            <td class="py-2 text-right text-gray-900">{{ person.shares }}</td>
            <td class="py-2 text-right text-gray-900">
              {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ person.amount }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </PanelLayout>
</template>
