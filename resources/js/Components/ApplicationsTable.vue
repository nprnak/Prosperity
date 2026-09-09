<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { DocumentTextIcon, ReceiptPercentIcon } from '@heroicons/vue/24/outline';

defineProps({
  applications: { type: Array, default: () => [] },
});

const page = usePage();
const currency = page.props.settings?.currency_symbol || 'Rs.';

const receiptOf = (app) => {
  const payment = (app.payment_transactions || [])[0];
  if (!payment?.receipt_number || !payment?.voucher?.id) {
    return null;
  }

  return {
    number: payment.receipt_number,
    voucherId: payment.voucher.id,
  };
};

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
            <!-- One link: the form is always shown before printing, so a
                 separate Print link that fired the dialog on load is gone. -->
            <Link
              :href="route('applications.show', app.id)"
              class="inline-flex items-center gap-1 font-medium text-blue-600 hover:text-blue-800 hover:underline"
            >
              <DocumentTextIcon class="h-4 w-4" /> View &amp; Print
            </Link>
            <template v-if="receiptOf(app)">
              <span class="mx-2 text-gray-300">|</span>
              <a
                :href="route('vouchers.download', receiptOf(app).voucherId)"
                class="inline-flex items-center gap-1 font-medium text-emerald-700 hover:text-emerald-900 hover:underline"
              >
                <ReceiptPercentIcon class="h-4 w-4" /> Download Receipt {{ receiptOf(app).number }}
              </a>
            </template>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
