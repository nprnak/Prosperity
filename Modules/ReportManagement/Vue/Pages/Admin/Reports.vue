<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  reports: Object,
});
</script>

<template>
  <Head title="Admin - Reports" />
  <PanelLayout>
    <div class="space-y-6">
      <h2 class="text-2xl font-bold text-gray-900">Reports & Analytics</h2>

      <!-- Report Cards -->
      <div class="grid grid-cols-1 gap-6">
        <!-- Applications Report -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-4">Applications Report</h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Total Submitted</span>
              <span class="text-2xl font-bold text-indigo-600">248</span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Approved</span>
              <span class="text-2xl font-bold text-green-600">156</span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Pending</span>
              <span class="text-2xl font-bold text-orange-600">45</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-gray-600">Rejected</span>
              <span class="text-2xl font-bold text-red-600">47</span>
            </div>
          </div>
          <button class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Download Report</button>
        </div>

        <!-- Financial Report -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-4">Financial Report</h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Total Amount Raised</span>
              <span class="text-2xl font-bold text-green-600">Rs. 45,000,000</span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Verified Payments</span>
              <span class="text-2xl font-bold text-indigo-600">892</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-gray-600">Pending Verification</span>
              <span class="text-2xl font-bold text-orange-600">23</span>
            </div>
          </div>
          <button class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Download Report</button>
        </div>

        <!-- Shares Report -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-4">Shares Allotment Report</h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Total Shares Allotted</span>
              <span class="text-2xl font-bold text-indigo-600">150,000</span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Average Per Application</span>
              <span class="text-2xl font-bold text-blue-600">603</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-gray-600">Remaining Shares</span>
              <span class="text-2xl font-bold text-orange-600">50,000</span>
            </div>
          </div>
          <button class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Download Report</button>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
