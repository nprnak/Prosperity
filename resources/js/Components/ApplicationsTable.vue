<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
  applications: { type: Array, default: () => [] },
});

const page = usePage();
const currency = page.props.settings?.currency_symbol || 'Rs.';

const statusLabel = (status) => {
  const labels = {
    draft: 'Draft',
    submitted: 'Submitted',
    sent_to_bank: 'Sent To Bank',
    bank_accepted: 'Bank Accepted',
    blocked: 'Amount Blocked',
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

const statusClass = (status) => {
  if (['approved', 'allotted', 'demat_credited', 'payment_verified', 'verified'].includes(status)) {
    return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
  }
  if (status === 'rejected' || status === 'not_allotted') {
    return 'bg-red-50 text-red-700 ring-red-200';
  }
  if (status === 'draft') {
    return 'bg-gray-100 text-gray-600 ring-gray-200';
  }

  return 'bg-amber-50 text-amber-700 ring-amber-200';
};
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-gray-600">
          <th class="py-2 pr-3 font-medium">No</th>
          <th class="py-2 pr-3 font-medium">Issue</th>
          <th class="py-2 pr-3 font-medium">Status</th>
          <th class="py-2 pr-3 font-medium">Shares</th>
          <th class="py-2 pr-3 font-medium">Total</th>
          <th class="py-2 font-medium text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!applications.length">
          <td colspan="6" class="py-4 text-center text-gray-500">No applications yet.</td>
        </tr>
        <tr v-for="app in applications" :key="app.id" class="border-t">
          <td class="py-2 pr-3">{{ app.application_number }}</td>
          <td class="py-2 pr-3">{{ app.issue_code || '-' }}</td>
          <td class="py-2 pr-3">
            <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1', statusClass(app.status)]">
              {{ statusLabel(app.status) }}
            </span>
          </td>
          <td class="py-2 pr-3">{{ app.shares_applied }}</td>
          <td class="py-2 pr-3">{{ currency }} {{ app.total_amount_declared }}</td>
          <td class="py-2 text-right whitespace-nowrap">
            <Link :href="route('applications.show', app.id)" class="font-medium text-blue-600 hover:text-blue-800 hover:underline">
              View
            </Link>
            <span class="mx-1 text-gray-300">|</span>
            <Link :href="route('applications.show', { application: app.id, print: 1 })" class="font-medium text-blue-600 hover:text-blue-800 hover:underline">
              Print
            </Link>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
