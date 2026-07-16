<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
  draft: Object,
  applications: Array,
  profileCompleted: Boolean,
  profileStatus: { type: String, default: 'incomplete' },
  offerings: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
});

const form = useForm({
  step: 2,
  payload: {
    investment_source: props.draft?.applicant?.sources_of_funds?.[0]?.source_type || 'salary',
    investment_source_other: props.draft?.applicant?.sources_of_funds?.[0]?.description || '',
    share_heir_name: props.draft?.applicant?.nominees?.[0]?.full_name || '',
    share_heir_relation: props.draft?.applicant?.nominees?.[0]?.relationship || '',
    share_heir_mobile: props.draft?.applicant?.nominees?.[0]?.mobile || '',
    share_offering_id: props.draft?.share_offering_id || props.offerings[0]?.id || null,
    asba_reference: props.draft?.asba_reference || '',
    shares_applied: props.draft?.shares_applied || 1,
    declaration_accepted: false,
  },
});

const hasDraft = computed(() => Boolean(props.draft?.id));
const profileReady = computed(() => props.profileStatus === 'approved');

const selectedOffering = computed(
  () => props.offerings.find((offering) => offering.id === form.payload.share_offering_id) || null,
);

const estimatedTotal = computed(() => {
  if (!selectedOffering.value) return '0.00';
  const shares = Math.max(1, Number.parseInt(form.payload.shares_applied || 1, 10));

  return (shares * Number.parseFloat(selectedOffering.value.share_rate)).toFixed(2);
});

watch(selectedOffering, (offering) => {
  if (offering && form.payload.shares_applied < offering.min_shares) {
    form.payload.shares_applied = offering.min_shares;
  }
});

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
        <h4 class="text-lg font-semibold">
          {{ profileStatus === 'submitted' ? 'Profile Under Review' : 'Profile Approval Required' }}
        </h4>
        <p class="mt-2 text-sm">
          <template v-if="profileStatus === 'submitted'">
            Your profile has been submitted and is being reviewed. You can apply for shares as soon as it is approved.
          </template>
          <template v-else-if="!profileCompleted">
            Your share application is locked until your profile is complete with all required information, education, address, and documents, and has been approved.
          </template>
          <template v-else>
            Your profile is complete but not yet approved. Go to your profile and submit it for review.
          </template>
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

          <p v-if="!offerings.length" class="mt-4 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600">
            There are no share offerings open for applications right now. Please check back later.
          </p>

          <div v-else class="mt-4 grid gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-gray-700">Share Offering *</label>
              <select v-model="form.payload.share_offering_id" :class="inputClass('share_offering_id')">
                <option v-for="offering in offerings" :key="offering.id" :value="offering.id">
                  {{ offering.company?.name }} · {{ offering.title }} ({{ offering.fiscal_year }}) · {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ offering.share_rate }}/share
                </option>
              </select>
              <InputError :message="payloadError('share_offering_id')" class="mt-1" />
              <p v-if="selectedOffering" class="mt-1 text-xs text-gray-500">
                {{ selectedOffering.min_shares }}–{{ selectedOffering.max_shares }} shares per applicant<span v-if="selectedOffering.closes_at">, closes {{ selectedOffering.closes_at.slice(0, 10) }}</span>
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Shares Applied</label>
              <input
                v-model="form.payload.shares_applied"
                type="number"
                :min="selectedOffering?.min_shares || 1"
                :max="selectedOffering?.max_shares"
                :class="inputClass('shares_applied')"
              />
              <InputError :message="payloadError('shares_applied')" class="mt-1" />
            </div>
            <div class="md:col-span-2 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm">
              <span class="text-gray-600">Rate: <strong>{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ selectedOffering?.share_rate || '—' }}</strong> per share</span>
              <span class="mx-2 text-gray-300">|</span>
              <span class="text-gray-600">Total payable: <strong class="text-gray-900">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ estimatedTotal }}</strong></span>
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

      <div v-if="profileReady && paymentMethods.length" class="bg-white p-6 rounded-2xl shadow-sm ring-1 ring-gray-100">
        <h3 class="font-semibold mb-1 text-gray-900">How to Pay</h3>
        <p class="text-sm text-gray-500 mb-4">Pay the total amount using any of the methods below, then keep your reference/voucher number — finance staff will verify it against your application.</p>
        <div class="grid gap-4 md:grid-cols-2">
          <div v-for="method in paymentMethods" :key="method.id" class="rounded-xl border border-gray-200 p-4 flex gap-4">
            <img
              v-if="method.qr_image_path"
              :src="route('payment-methods.qr', method.id)"
              :alt="`${method.name} QR code`"
              class="h-24 w-24 rounded object-contain border border-gray-100"
            />
            <div class="text-sm">
              <div class="font-semibold text-gray-900">{{ method.name }}</div>
              <div v-if="method.bank_name" class="text-gray-600">{{ method.bank_name }}</div>
              <div v-if="method.account_number" class="text-gray-600">
                {{ method.account_name ? method.account_name + ' · ' : '' }}A/C {{ method.account_number }}
              </div>
              <p v-if="method.instructions" class="mt-1 text-gray-500">{{ method.instructions }}</p>
            </div>
          </div>
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
              <td class="py-2">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ app.total_amount_declared }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </PanelLayout>
</template>
