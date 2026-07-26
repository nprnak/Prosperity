<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ applications: Array, status: String });

// Each slip is checked against its own bank record, so verification happens a
// deposit at a time. The receipt beneath them is signed off separately, and
// only once they have all been settled.
const verifyDeposit = (depositId, status) => {
  useForm({ status, notes: '' }).post(route('finance.deposits.verify', depositId), { preserveScroll: true });
};

const verifyPayment = (paymentId, status) => {
  useForm({ status, notes: '' }).post(route('finance.payments.verify', paymentId), { preserveScroll: true });
};

const deposits = (payment) => payment.deposits || [];

const allVerified = (payment) => {
  const rows = deposits(payment);
  return rows.length > 0 && rows.every((deposit) => deposit.verification_status === 'verified');
};

const outstanding = (payment) =>
  deposits(payment).filter((deposit) => deposit.verification_status !== 'verified').length;

const statusClass = (status) => ({
  pending: 'bg-amber-100 text-amber-800',
  verified: 'bg-emerald-100 text-emerald-800',
  rejected: 'bg-red-100 text-red-800',
}[status] || 'bg-gray-100 text-gray-700');
</script>

<template>
  <Head title="Finance Dashboard" />
  <PanelLayout>
    <template #header><h2 class="font-semibold text-xl">Finance Dashboard</h2></template>

    <div class="mx-auto max-w-6xl space-y-6 py-8">
      <div v-for="app in applications" :key="app.id" class="space-y-4 rounded-lg bg-white p-5 shadow">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <div class="font-medium text-gray-900">
            {{ app.application_number }} · {{ app.applicant?.full_name_en }}
          </div>
          <span class="text-sm text-gray-600">
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
            <div v-if="payment.checker?.name" class="text-xs text-gray-500">
              checked by {{ payment.checker.name }}
              <template v-if="payment.verifier?.name">, re-verified by {{ payment.verifier.name }}</template>
            </div>
          </div>

          <table class="mt-3 w-full text-sm">
            <thead class="border-b text-left text-xs uppercase text-gray-500">
              <tr>
                <th class="py-1">Reference</th>
                <th class="py-1">Bank</th>
                <th class="py-1">Amount</th>
                <th class="py-1">Deposited</th>
                <th class="py-1">Status</th>
                <th class="py-1"></th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="deposit in deposits(payment)" :key="deposit.id">
                <td class="py-2 font-medium text-gray-900">{{ deposit.cheque_no || deposit.reference_no || '-' }}</td>
                <td class="py-2 text-gray-600">{{ deposit.bank_name || '-' }}</td>
                <td class="py-2 text-gray-900">{{ deposit.amount }}</td>
                <td class="py-2 text-gray-600">{{ deposit.payment_date || '-' }}</td>
                <td class="py-2">
                  <span :class="statusClass(deposit.verification_status)" class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize">
                    {{ deposit.verification_status }}
                  </span>
                </td>
                <td class="py-2 text-right">
                  <template v-if="deposit.verification_status === 'pending'">
                    <button class="text-emerald-700 hover:underline" @click="verifyDeposit(deposit.id, 'verified')">Verify</button>
                    <button class="ml-3 text-red-700 hover:underline" @click="verifyDeposit(deposit.id, 'rejected')">Reject</button>
                  </template>
                </td>
              </tr>
              <tr v-if="!deposits(payment).length">
                <td colspan="6" class="py-3 text-gray-500">No deposits recorded against this receipt.</td>
              </tr>
            </tbody>
          </table>

          <div v-if="payment.verification_status === 'pending'" class="mt-3 border-t pt-3">
            <template v-if="allVerified(payment)">
              <p class="mb-2 text-xs text-gray-600">
                All deposits verified. Two different finance officers must sign this receipt off.
              </p>
              <button class="text-emerald-700 hover:underline" @click="verifyPayment(payment.id, 'verified')">
                {{ payment.checked_by ? 'Re-verify receipt' : 'Check receipt' }}
              </button>
              <button class="ml-3 text-red-700 hover:underline" @click="verifyPayment(payment.id, 'rejected')">Reject receipt</button>
            </template>
            <p v-else class="text-xs text-gray-600">
              {{ outstanding(payment) }} deposit(s) still to settle before this receipt can be signed off.
            </p>
          </div>
        </div>
      </div>

      <p v-if="!applications?.length" class="rounded-lg bg-white p-6 text-center text-gray-500 shadow">
        Nothing waiting on finance right now.
      </p>
    </div>
  </PanelLayout>
</template>
