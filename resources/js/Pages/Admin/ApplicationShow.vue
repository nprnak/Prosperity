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
          <p class="text-sm text-gray-700"><span class="font-medium">Name:</span> {{ application.applicant?.full_name_english || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Email:</span> {{ application.applicant?.email || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Mobile:</span> {{ application.applicant?.mobile_number || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Citizenship No:</span> {{ application.applicant?.citizenship_number || '-' }}</p>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-lg font-semibold text-gray-900">Application Summary</h2>
          <p class="text-sm text-gray-700"><span class="font-medium">Status:</span> <span class="capitalize">{{ application.status }}</span></p>
          <p class="text-sm text-gray-700"><span class="font-medium">Shares Applied:</span> {{ application.shares_applied }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Amount Per Share:</span> Rs. {{ application.amount_per_share }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Total Declared:</span> Rs. {{ application.total_amount_declared }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Submitted At:</span> {{ formatDate(application.submitted_at) }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Reviewed By:</span> {{ application.reviewer?.name || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Reviewed At:</span> {{ formatDate(application.reviewed_at) }}</p>
          <p class="text-sm text-gray-700" v-if="application.rejection_reason"><span class="font-medium">Rejection Reason:</span> {{ application.rejection_reason }}</p>
        </section>
      </div>

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
                <td class="px-4 py-3">Rs. {{ payment.amount }}</td>
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
