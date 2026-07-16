<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

defineProps({
  applications: Array,
});

const statusClass = (status) => {
  if (['submitted', 'sent_to_bank', 'bank_accepted', 'blocked', 'payment_pending'].includes(status)) {
    return 'bg-yellow-100 text-yellow-700';
  }

  if (['payment_verified', 'reviewed', 'verified', 'approved', 'allotted', 'demat_credited'].includes(status)) {
    return 'bg-green-100 text-green-700';
  }

  if (['partially_allotted', 'refund_initiated', 'refund_completed'].includes(status)) {
    return 'bg-blue-100 text-blue-700';
  }

  if (['rejected', 'not_allotted'].includes(status)) {
    return 'bg-red-100 text-red-700';
  }

  return 'bg-gray-100 text-gray-700';
};

const statusLabel = (status) => {
  const labels = {
    draft: 'Draft',
    submitted: 'Submitted',
    sent_to_bank: 'Sent To Bank',
    bank_accepted: 'Bank Accepted',
    blocked: 'Blocked',
    payment_pending: 'Payment Pending',
    payment_verified: 'Payment Verified',
    reviewed: 'Reviewed',
    verified: 'Verified',
    approved: 'Approved',
    allotted: 'Allotted',
    partially_allotted: 'Partially Allotted',
    not_allotted: 'Not Allotted',
    refund_initiated: 'Refund Initiated',
    refund_completed: 'Refund Completed',
    demat_credited: 'Demat Credited',
    rejected: 'Rejected',
  };

  return labels[status] || status;
};
</script>

<template>
  <Head title="Admin - Applications Management" />
  <PanelLayout>
    <div class="bg-white rounded-lg shadow p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Applications Management</h2>
        <div class="space-x-2">
          <input type="text" placeholder="Search applications..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-600" />
          <button class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Filter</button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application No</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shares</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="app in applications" :key="app.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ app.application_number }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ app.applicant?.full_name_en }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ app.applicant?.email }}</td>
              <td class="px-6 py-4 text-sm text-gray-900">{{ app.shares_applied }}</td>
              <td class="px-6 py-4 text-sm text-gray-900">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ app.total_amount_declared }}</td>
              <td class="px-6 py-4 text-sm">
                <span :class="statusClass(app.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                  {{ statusLabel(app.status) }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ app.submitted_at }}</td>
              <td class="px-6 py-4 text-sm space-x-2">
                <Link :href="route('admin.applications.show', app.id)" class="text-indigo-600 hover:text-indigo-900 font-semibold">View</Link>
                <Link :href="route('admin.applications.show', app.id)" class="text-blue-600 hover:text-blue-900 font-semibold">Details</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!applications || applications.length === 0" class="text-center py-12">
        <p class="text-gray-500">No applications found</p>
      </div>
    </div>
  </PanelLayout>
</template>
