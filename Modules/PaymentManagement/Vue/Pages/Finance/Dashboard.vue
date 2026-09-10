<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ applications: Array, status: String });

// A payment settles automatically once the application it belongs to is
// approved (ApproverController marks every transaction on it verified at
// that point), so this page is a read-only look at where money stands while
// an application is still moving through the review chain.
const deposits = (payment) => payment.deposits || [];

const statusClass = (status) => ({
  pending: 'bg-amber-100 text-amber-800',
  verified: 'bg-emerald-100 text-emerald-800',
  rejected: 'bg-red-100 text-red-800',
}[status] || 'bg-gray-100 text-gray-700');
</script>

<template>
  <Head title="Finance Dashboard" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Finance Dashboard</h2>
        <p class="mt-1 text-sm text-gray-700">
          Where money stands on applications still moving through the review chain. A payment is
          marked verified automatically once its application is approved.
        </p>
      </div>

      <div v-for="app in applications" :key="app.id" class="space-y-4 rounded-lg bg-white p-5 shadow">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <div class="font-medium text-gray-900">
            {{ app.application_number }} · {{ app.applicant?.full_name_en }}
          </div>
          <span class="text-sm text-gray-700">
            {{ app.status_label || app.status }} ·
            declared {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ app.total_amount_declared }}
          </span>
        </div>

        <div v-for="payment in app.payment_transactions || []" :key="payment.id" class="rounded-lg border border-gray-200 p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm text-gray-700">
              <span class="font-semibold">
                {{ payment.receipt_number ? `Receipt ${payment.receipt_number}` : 'Receipt not yet issued' }}
              </span>
              · {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ payment.amount }}
              <span :class="statusClass(payment.verification_status)" class="ml-2 rounded-full px-2 py-0.5 text-xs font-semibold capitalize">
                {{ payment.verification_status }}
              </span>
            </div>
            <div v-if="payment.verifier?.name" class="text-xs text-gray-600">
              verified by {{ payment.verifier.name }}
            </div>
          </div>

          <table class="mt-3 w-full text-sm">
            <thead class="border-b text-left text-xs uppercase text-gray-500">
              <tr>
                <th class="py-1">Reference</th>
                <th class="py-1">Bank</th>
                <th class="py-1">Amount</th>
                <th class="py-1">Deposited</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="deposit in deposits(payment)" :key="deposit.id">
                <td class="py-2 font-medium text-gray-900">{{ deposit.cheque_no || deposit.reference_no || '-' }}</td>
                <td class="py-2 text-gray-700">{{ deposit.bank_name || '-' }}</td>
                <td class="py-2 text-gray-900">{{ deposit.amount }}</td>
                <td class="py-2 text-gray-700">{{ deposit.payment_date || '-' }}</td>
              </tr>
              <tr v-if="!deposits(payment).length">
                <td colspan="4" class="py-3 text-gray-500">No deposits recorded against this receipt.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p v-if="!applications?.length" class="rounded-lg bg-white p-6 text-center text-gray-500 shadow">
        Nothing waiting on the review chain right now.
      </p>
    </div>
  </PanelLayout>
</template>
