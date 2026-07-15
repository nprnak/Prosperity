<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
  draft: Object,
  applications: Array,
  profileCompleted: Boolean,
});

const money = (value) => Number.parseFloat(value || 0);

const form = useForm({
  step: 2,
  payload: {
    investment_source: props.draft?.applicant?.investment_source || 'salary',
    investment_source_other: props.draft?.applicant?.investment_source_other || '',
    share_heir_name: props.draft?.applicant?.share_heir_name || '',
    share_heir_relation: props.draft?.applicant?.share_heir_relation || '',
    share_heir_mobile: props.draft?.applicant?.share_heir_mobile || '',
    issue_code: props.draft?.issue_code || '',
    asba_reference: props.draft?.asba_reference || '',
    shares_applied: props.draft?.shares_applied || 1,
    amount_per_share: props.draft?.amount_per_share || 100,
    total_amount_declared: props.draft?.total_amount_declared || 100,
    declaration_accepted: false,
  },
});

const hasDraft = computed(() => Boolean(props.draft?.id));
const profileReady = computed(() => Boolean(props.profileCompleted));

const estimatedTotal = computed(() => {
  const shares = Math.max(1, Number.parseInt(form.payload.shares_applied || 1, 10));
  const amount = Math.max(0, money(form.payload.amount_per_share));

  return (shares * amount).toFixed(2);
});

watch(
  () => [form.payload.shares_applied, form.payload.amount_per_share],
  () => {
    form.payload.total_amount_declared = estimatedTotal.value;
  },
  { immediate: true },
);

const payloadError = (field) => form.errors[`payload.${field}`];

const inputClass = (field) => {
  const base = 'w-full rounded-lg border px-3 py-2';
  return payloadError(field) ? `${base} border-red-400 focus:border-red-500 focus:ring-red-500` : `${base} border-gray-300`;
};

const saveDraft = () => {
  form.post(route('applications.draft'), {
    forceFormData: true,
    preserveScroll: true,
  });
};

const submitFinal = () => {
  const id = props.draft?.id;
  if (!id || !profileReady.value) return;
  useForm({
    declaration_accepted: form.payload.declaration_accepted,
    asba_reference: form.payload.asba_reference,
  }).post(route('applications.submit', id));
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
  <Head title="Share Application" />
  <PanelLayout>
    <div class="py-8 max-w-6xl mx-auto space-y-6 px-4 sm:px-6">
      <div class="rounded-2xl bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-700 p-6 text-white shadow-lg">
        <h3 class="text-2xl font-semibold">Share Application Portal</h3>
        <p class="mt-2 text-sm text-blue-100">
          Register first, login, complete your full profile, then apply for shares from the same account.
        </p>
      </div>

      <div v-if="!profileReady" class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-800 shadow-sm">
        <h4 class="text-lg font-semibold">Complete Profile First</h4>
        <p class="mt-2 text-sm">
          Your share application is locked until your profile is complete with all required information, education, address, and documents.
        </p>
        <div class="mt-4">
          <Link :href="route('profile.edit')" class="inline-flex rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
            Go To Profile
          </Link>
        </div>
      </div>

      <div v-else class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-6">
        <p v-if="$page.props.errors.profile" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ $page.props.errors.profile }}
        </p>

        <section class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 sm:p-5">
          <h4 class="text-lg font-semibold text-gray-900">Share Details</h4>
          <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Issue Code *</label>
              <input v-model="form.payload.issue_code" type="text" placeholder="e.g. NABILPO2026" :class="inputClass('issue_code')" />
              <InputError :message="payloadError('issue_code')" class="mt-1" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Shares Applied</label>
              <input v-model="form.payload.shares_applied" type="number" min="1" placeholder="e.g. 50" :class="inputClass('shares_applied')" />
              <InputError :message="payloadError('shares_applied')" class="mt-1" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Amount per Share</label>
              <input v-model="form.payload.amount_per_share" type="number" min="0" step="0.01" placeholder="e.g. 100" :class="inputClass('amount_per_share')" />
              <InputError :message="payloadError('amount_per_share')" class="mt-1" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Total Declared Amount</label>
              <input v-model="form.payload.total_amount_declared" type="number" min="0" step="0.01" :class="inputClass('total_amount_declared')" />
              <InputError :message="payloadError('total_amount_declared')" class="mt-1" />
              <p class="mt-1 text-xs text-gray-500">Suggested total: Rs. {{ estimatedTotal }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">ASBA Reference</label>
              <input v-model="form.payload.asba_reference" type="text" placeholder="Enter bank reference if available" :class="inputClass('asba_reference')" />
              <InputError :message="payloadError('asba_reference')" class="mt-1" />
            </div>
          </div>
        </section>

        <section class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 sm:p-5">
          <h4 class="text-lg font-semibold text-gray-900">Investment and Heir Details</h4>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Investment Source</label>
              <select v-model="form.payload.investment_source" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                <option value="salary">Salary</option>
                <option value="dividend">Dividend</option>
                <option value="property_sale">Property Sale</option>
                <option value="house_rent">House Rent</option>
                <option value="share_trading">Share Trading</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Other Investment Source</label>
              <input v-model="form.payload.investment_source_other" placeholder="Only fill if source is Other" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Share Heir Name</label>
              <input v-model="form.payload.share_heir_name" placeholder="e.g. Sita Sharma" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Heir Relation</label>
              <input v-model="form.payload.share_heir_relation" placeholder="e.g. Daughter" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Heir Mobile</label>
              <input v-model="form.payload.share_heir_mobile" placeholder="e.g. 98XXXXXXXX" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            </div>
          </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 sm:p-5">
          <label class="flex items-start gap-3 text-sm text-gray-700">
            <input v-model="form.payload.declaration_accepted" type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600" />
            <span>I confirm that the profile and share application information provided above is true and complete.</span>
          </label>
          <InputError :message="payloadError('declaration_accepted') || form.errors.declaration_accepted" class="mt-2" />
        </section>

        <div class="flex flex-wrap justify-end gap-3">
          <button @click="saveDraft" class="rounded-lg bg-blue-600 px-5 py-2.5 text-white hover:bg-blue-700" :disabled="form.processing">
            Save Application Draft
          </button>
          <button
            @click="submitFinal"
            class="rounded-lg bg-emerald-600 px-5 py-2.5 text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
            :disabled="!hasDraft || !form.payload.declaration_accepted"
          >
            Submit Application
          </button>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl shadow-sm ring-1 ring-gray-100">
        <h3 class="font-semibold mb-3 text-gray-900">My Applications</h3>
        <table class="w-full text-sm">
          <thead><tr class="text-left text-gray-600"><th>No</th><th>Issue</th><th>Status</th><th>Shares</th><th>Total</th></tr></thead>
          <tbody>
            <tr v-for="app in applications" :key="app.id" class="border-t">
              <td class="py-2">{{ app.application_number }}</td>
              <td class="py-2">{{ app.issue_code || '-' }}</td>
              <td class="py-2">{{ statusLabel(app.status) }}</td>
              <td class="py-2">{{ app.shares_applied }}</td>
              <td class="py-2">Rs. {{ app.total_amount_declared }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </PanelLayout>
</template>
