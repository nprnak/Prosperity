<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import WorkflowTimeline from '@/Components/WorkflowTimeline.vue';

const props = defineProps({
  application: Object,
});

const page = usePage();
const can = (permission) => (page.props.auth?.permissions || []).includes(permission);

// Payment can be recorded while the application sits in a pre-verification state.
const canRecordPayment = computed(() =>
  can('payment.record')
  && ['submitted', 'sent_to_bank', 'bank_accepted', 'blocked', 'payment_pending'].includes(props.application.status),
);

const modeFromPaymentType = { connect_ips: 'ips', mobile_banking: 'mobile_banking', cheque: 'cheque' };
const isCheque = props.application.payment_type === 'cheque';

// Prefilled from what the applicant declared; finance can adjust before recording.
const paymentForm = useForm({
  amount: props.application.total_amount_declared || '',
  payment_mode: modeFromPaymentType[props.application.payment_type] || 'cash',
  payment_method_id: null,
  payment_date: new Date().toISOString().slice(0, 10),
  bank_name: props.application.payment_deposited_bank || '',
  payment_reference_no: isCheque ? '' : (props.application.payment_deposited_ref_no || ''),
  cheque_no: isCheque ? (props.application.payment_deposited_ref_no || '') : '',
  holding_id_no: '',
  id_type: 'citizenship',
  notes: '',
});

const recordPayment = () =>
  paymentForm.post(route('finance.payments.store', props.application.id), { preserveScroll: true });

const verifyPayment = (paymentId, status) =>
  useForm({ status, notes: '' }).post(route('finance.payments.verify', paymentId), { preserveScroll: true });

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
          <p class="text-sm text-gray-700"><span class="font-medium">Declared Payment Type:</span> <span class="capitalize">{{ (application.payment_type || '-').replace('_', ' ') }}</span></p>
          <p class="text-sm text-gray-700"><span class="font-medium">Declared Paying Bank:</span> {{ application.payment_deposited_bank || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Declared Transaction Code / Cheque No:</span> {{ application.payment_deposited_ref_no || '-' }}</p>
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
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Uploaded Bank Voucher</h2>
        <template v-if="application.has_bank_voucher_image">
          <a :href="route('applications.voucher-image', application.id)" target="_blank" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
            Open in new tab
          </a>
          <img
            :src="route('applications.voucher-image', application.id)"
            alt="Uploaded bank voucher"
            class="mt-3 max-h-[600px] max-w-full rounded-lg border border-gray-200 object-contain"
          />
        </template>
        <p v-else class="text-sm text-gray-500">The applicant has not uploaded a voucher image.</p>
      </section>

      <section v-if="canRecordPayment" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Record Payment</h2>
        <p class="text-sm text-gray-500 mb-4">Prefilled from the applicant's declaration — adjust anything that differs from the actual payment, then record it.</p>
        <div class="grid gap-3 md:grid-cols-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Amount</label>
            <input v-model="paymentForm.amount" type="number" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Mode</label>
            <select v-model="paymentForm.payment_mode" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option>cash</option><option>cheque</option><option>online_transfer</option><option>self_cheque_deposit</option><option>ips</option><option>mobile_banking</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Payment Date</label>
            <input v-model="paymentForm.payment_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Paying Bank</label>
            <input v-model="paymentForm.bank_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div v-if="paymentForm.payment_mode === 'cheque'">
            <label class="mb-1 block text-xs font-medium text-gray-700">Cheque No</label>
            <input v-model="paymentForm.cheque_no" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div v-else>
            <label class="mb-1 block text-xs font-medium text-gray-700">Transaction Code / Reference</label>
            <input v-model="paymentForm.payment_reference_no" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Holding ID No</label>
            <input v-model="paymentForm.holding_id_no" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">ID Type</label>
            <select v-model="paymentForm.id_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option value="citizenship">Citizenship</option>
              <option value="national_id">National ID</option>
              <option value="pan">PAN</option>
            </select>
          </div>
          <div class="flex items-end">
            <button
              class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:bg-indigo-300"
              :disabled="paymentForm.processing"
              @click="recordPayment"
            >
              Record Payment
            </button>
          </div>
        </div>
      </section>

      <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-lg font-semibold text-gray-900">Review Trail</h2>
        <p class="mt-1 mb-4 text-sm text-gray-600">
          Every sign-off, return and send-back, with the remarks given at the time.
        </p>
        <WorkflowTimeline :events="application.workflow_events ?? []" order="asc" />
      </section>

      <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-lg font-semibold text-gray-900">Payment &amp; Lifecycle Events</h2>
        <p class="mt-1 mb-4 text-sm text-gray-600">
          Status changes driven outside the review chain, such as payment verification.
        </p>
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
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sign-offs</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voucher</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="payment in application.payment_transactions" :key="payment.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ payment.receipt_number }}</td>
                <td class="px-4 py-3">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ payment.amount }}</td>
                <td class="px-4 py-3 capitalize">{{ payment.payment_mode }}</td>
                <td class="px-4 py-3 capitalize">{{ payment.verification_status }}</td>
                <td class="px-4 py-3 text-xs text-gray-600">
                  <div>Checked By: {{ payment.checker?.name || '—' }}</div>
                  <div>Reviewed By: {{ payment.verifier?.name || '—' }}</div>
                  <div>Approved By: {{ payment.approver?.name || '—' }}</div>
                </td>
                <td class="px-4 py-3">
                  <a
                    v-if="payment.voucher"
                    :href="route('vouchers.download', payment.voucher.id)"
                    class="text-blue-700 underline"
                  >{{ payment.voucher.voucher_number }} — Print Receipt</a>
                  <span v-else>-</span>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <template v-if="can('payment.verify') && payment.verification_status === 'pending'">
                    <button
                      v-if="!payment.checked_by"
                      class="text-xs font-semibold text-green-700 underline mr-3"
                      @click="verifyPayment(payment.id, 'verified')"
                    >Verify</button>
                    <button
                      v-else-if="payment.checked_by !== $page.props.auth.user.id"
                      class="text-xs font-semibold text-green-700 underline mr-3"
                      @click="verifyPayment(payment.id, 'verified')"
                    >Re-verify</button>
                    <span v-else class="text-xs text-gray-500 mr-3">Awaiting re-verification by another officer</span>
                    <button class="text-xs font-semibold text-red-700 underline" @click="verifyPayment(payment.id, 'rejected')">Reject</button>
                  </template>
                </td>
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
