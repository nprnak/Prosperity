<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  allotments: Array,
  stats: Object,
});
</script>

<template>
  <Head title="Admin - Allotments Management" />
  <PanelLayout>
    <div class="space-y-6">
      <!-- Allotments Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-6 border border-blue-200">
          <h3 class="text-gray-600 text-sm font-medium">Total Allotted</h3>
          <p class="text-4xl font-bold text-blue-600 mt-2">{{ stats?.totalAllotted || 0 }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-6 border border-green-200">
          <h3 class="text-gray-600 text-sm font-medium">Total Applicants</h3>
          <p class="text-4xl font-bold text-green-600 mt-2">{{ stats?.totalApplicants || 0 }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-6 border border-purple-200">
          <h3 class="text-gray-600 text-sm font-medium">Average Per Applicant</h3>
          <p class="text-4xl font-bold text-purple-600 mt-2">{{ stats?.averagePerApplicant || 0 }}</p>
        </div>
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg p-6 border border-orange-200">
          <h3 class="text-gray-600 text-sm font-medium">Total Raised</h3>
          <p class="text-4xl font-bold text-orange-600 mt-2">Rs. {{ stats?.totalRaised || 0 }}</p>
        </div>
      </div>

      <!-- Allotments Table -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-900">Shares Allotments</h2>
          <button class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Export Report</button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Allotment No</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application No</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shares Applied</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shares Allotted</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cost per Share</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Allotted Date</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="allot in allotments" :key="allot.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm font-medium text-gray-900">ALLOT-{{ allot.id }}</td>
                <td class="px-6 py-4 text-sm text-gray-600">{{ allot.share_application?.applicant?.full_name_english }}</td>
                <td class="px-6 py-4 text-sm text-gray-600">{{ allot.share_application?.application_number }}</td>
                <td class="px-6 py-4 text-sm text-gray-900">{{ allot.share_application?.shares_applied }}</td>
                <td class="px-6 py-4 text-sm font-semibold text-green-600">{{ allot.shares_allotted }}</td>
                <td class="px-6 py-4 text-sm text-gray-900">Rs. {{ allot.cost_per_share || 'N/A' }}</td>
                <td class="px-6 py-4 text-sm font-semibold text-gray-900">Rs. {{ (allot.shares_allotted * (allot.cost_per_share || 0)) | 0 }}</td>
                <td class="px-6 py-4 text-sm">
                  <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Allotted</span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">{{ allot.created_at }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="!allotments || allotments.length === 0" class="text-center py-12">
          <p class="text-gray-500">No allotments found</p>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
