<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ metrics: Object, capitalSeries: Array });
</script>

<template>
  <Head title="Admin Dashboard" />
  <PanelLayout>
    <div class="bg-white rounded-lg shadow p-6 space-y-6">
      <h2 class="text-3xl font-bold text-gray-900">Capital Raised Analytics</h2>
      <div class="grid grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded shadow p-4 border border-blue-200">
          <div class="text-gray-500 text-sm">Capital Raised</div>
          <div class="text-3xl font-bold text-blue-600 mt-2">Rs. {{ metrics?.capitalRaised || 0 }}</div>
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
  </PanelLayout>
</template>
