<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CapitalRaisedChart from '@/Components/Charts/CapitalRaisedChart.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ metrics: Object, capitalSeries: Array });
</script>

<template>
  <Head title="Admin Dashboard" />
  <AuthenticatedLayout>
    <template #header><h2 class="font-semibold text-xl">Admin Dashboard</h2></template>
    <div class="py-8 max-w-6xl mx-auto space-y-4">
      <div class="grid grid-cols-4 gap-4">
        <div class="bg-white rounded shadow p-4"><div class="text-gray-500 text-sm">Capital Raised</div><div class="text-xl">{{ metrics.capitalRaised }}</div></div>
        <div class="bg-white rounded shadow p-4"><div class="text-gray-500 text-sm">Pending Applications</div><div class="text-xl">{{ metrics.pendingApplications }}</div></div>
        <div class="bg-white rounded shadow p-4"><div class="text-gray-500 text-sm">Pending Payment Verification</div><div class="text-xl">{{ metrics.pendingPaymentVerification }}</div></div>
        <div class="bg-white rounded shadow p-4"><div class="text-gray-500 text-sm">Shares Allotted</div><div class="text-xl">{{ metrics.totalSharesAllotted }}</div></div>
      </div>
      <div class="bg-white rounded shadow p-4">
        <h3 class="font-medium mb-2">Capital raised over time</h3>
        <CapitalRaisedChart :points="capitalSeries" />
      </div>
    </div>
  </AuthenticatedLayout>
</template>
