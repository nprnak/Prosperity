<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
  application: Object,
});

const formatDate = (value) => {
  if (!value) return '-';
  return new Date(value).toLocaleString();
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
  <Head :title="`Application ${application.application_number}`" />

  <PanelLayout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Application Details</h1>
          <p class="text-sm text-gray-600">Application No: {{ application.application_number }}</p>
        </div>
        <Link :href="route('admin.applications')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Back to Applications
        </Link>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-lg font-semibold text-gray-900">Applicant</h2>
          <p class="text-sm text-gray-700"><span class="font-medium">Name:</span> {{ application.applicant?.full_name_en || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Email:</span> {{ application.applicant?.email || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Mobile:</span> {{ application.applicant?.mobile || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Citizenship No:</span> {{ application.applicant?.citizenship_number || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">BOID:</span> {{ application.applicant?.boid || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Bank:</span> {{ application.applicant?.bank_name || '-' }} {{ application.applicant?.bank_branch || '' }}</p>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-lg font-semibold text-gray-900">Application Summary</h2>
          <p class="text-sm text-gray-700"><span class="font-medium">Status:</span> {{ statusLabel(application.status) }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Issue Code:</span> {{ application.issue_code || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">ASBA Reference:</span> {{ application.asba_reference || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Shares Applied:</span> {{ application.shares_applied }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Amount Per Share:</span> {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ application.amount_per_share }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Total Declared:</span> {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ application.total_amount_declared }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Blocked Amount:</span> {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ application.blocked_amount || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Refunded Amount:</span> {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ application.refunded_amount || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Submitted At:</span> {{ formatDate(application.submitted_at) }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Reviewed By:</span> {{ application.reviewer?.name || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Reviewed At:</span> {{ formatDate(application.reviewed_at) }}</p>
          <p class="text-sm text-gray-700" v-if="application.rejection_reason"><span class="font-medium">Rejection Reason:</span> {{ application.rejection_reason }}</p>
        </section>
      </div>

      <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Lifecycle Events</h2>
        <div v-if="application.events?.length" class="space-y-3">
          <div v-for="event in application.events" :key="event.id" class="rounded-lg border border-gray-200 p-3">
            <p class="text-sm text-gray-900">
              {{ statusLabel(event.from_status || '-') }} -> {{ statusLabel(event.to_status) }}
            </p>
            <p class="text-xs text-gray-600 mt-1">By: {{ event.actor?.name || 'System' }} | {{ formatDate(event.created_at) }}</p>
            <p v-if="event.remarks" class="text-xs text-gray-600 mt-1">{{ event.remarks }}</p>
          </div>
        </div>
        <p v-else class="text-sm text-gray-500">No lifecycle events recorded yet.</p>
      </section>

      <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Transactions</h2>

        <div v-if="application.payment_transactions?.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Receipt No</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Verification</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voucher</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="payment in application.payment_transactions" :key="payment.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ payment.receipt_number }}</td>
                <td class="px-4 py-3">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ payment.amount }}</td>
                <td class="px-4 py-3 capitalize">{{ payment.payment_mode }}</td>
                <td class="px-4 py-3 capitalize">{{ payment.verification_status }}</td>
                <td class="px-4 py-3">{{ payment.voucher?.voucher_number || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-else class="text-sm text-gray-500">No payments recorded for this application.</p>
      </section>

      <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100" v-if="application.allotment">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Allotment</h2>
        <p class="text-sm text-gray-700"><span class="font-medium">Certificate No:</span> {{ application.allotment.certificate_number || '-' }}</p>
        <p class="text-sm text-gray-700"><span class="font-medium">Shares Allotted:</span> {{ application.allotment.shares_allotted || '-' }}</p>
        <p class="text-sm text-gray-700"><span class="font-medium">Allotment Date:</span> {{ formatDate(application.allotment.allotment_date) }}</p>
      </section>
    </div>
  </PanelLayout>
</template>
