<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  draft: Object,
  profileCompleted: Boolean,
  profileStatus: { type: String, default: 'incomplete' },
  // Applications still with staff. Each locks only its own offering; a
  // settled one is finished business and locks nothing.
  activeApplications: { type: Array, default: () => [] },
  // Why the application came back, when it did.
  returnedReason: { type: String, default: null },
  offerings: { type: Array, default: () => [] },
  // {code, name} of whoever this application is currently credited to, seeded
  // from the applicant's default on a first draft.
  focalPerson: { type: Object, default: null },
});

// A deposit cannot have been made tomorrow.
const today = new Date().toISOString().slice(0, 10);

// `key` is a stable client-side handle for v-for; the server never sees it.
let nextVoucherKey = 0;

const blankVoucher = () => ({
  key: nextVoucherKey++,
  id: null,
  payment_type: '',
  deposited_bank: '',
  transaction_code: '',
  asba_reference: '',
  amount: '',
  payment_date: '',
  image: null,
  has_image: false,
  preview: null,
});

// Rebuilt from the server's rows rather than read once, so a save can refresh
// the form with the ids and slips the draft now actually holds.
const mapSavedVouchers = () => (props.draft?.vouchers || []).map((voucher) => ({
  ...blankVoucher(),
  id: voucher.id,
  payment_type: voucher.payment_type || '',
  deposited_bank: voucher.deposited_bank || '',
  transaction_code: voucher.transaction_code || '',
  asba_reference: voucher.asba_reference || '',
  amount: voucher.amount ?? '',
  payment_date: voucher.payment_date ?? '',
  has_image: Boolean(voucher.has_image),
}));

const savedVouchers = mapSavedVouchers();

const form = useForm({
  step: 2,
  payload: {
    investment_sources: (props.draft?.applicant?.sources_of_funds || []).map((source) => source.source_type),
    share_heir_name: props.draft?.applicant?.nominees?.[0]?.full_name || '',
    share_heir_relation: props.draft?.applicant?.nominees?.[0]?.relationship || '',
    share_heir_mobile: props.draft?.applicant?.nominees?.[0]?.mobile || '',
    share_offering_id: props.draft?.share_offering_id || props.offerings[0]?.id || null,
    focal_person_code: props.focalPerson?.code || '',
    vouchers: savedVouchers.length ? savedVouchers : [blankVoucher()],
    shares_applied: props.draft?.shares_applied || 1,
    declaration_accepted: Boolean(props.draft?.declaration_accepted),
  },
});

const investmentSourceOptions = [
  { value: 'salary', label: 'Salary' },
  { value: 'dividend', label: 'Dividend' },
  { value: 'property_sale', label: 'Property Sale' },
  { value: 'house_rent', label: 'House Rent' },
  { value: 'share_trading', label: 'Share Trading' },
];

const addVoucher = () => form.payload.vouchers.push(blankVoucher());

const removeVoucher = (index) => {
  const [removed] = form.payload.vouchers.splice(index, 1);

  if (removed?.preview) URL.revokeObjectURL(removed.preview);
};

const onVoucherChange = (event, index) => {
  const file = event.target.files[0] || null;
  const voucher = form.payload.vouchers[index];

  voucher.image = file;

  if (voucher.preview) URL.revokeObjectURL(voucher.preview);
  voucher.preview = file ? URL.createObjectURL(file) : null;
};

const hasDraft = computed(() => Boolean(props.draft?.id));
const profileReady = computed(() => props.profileStatus === 'approved');
// The three KYC sign-off stages all read as "under review" to the applicant.
const profileInReview = computed(() => ['submitted', 'verified', 'reviewed'].includes(props.profileStatus));
const profileReturned = computed(() => props.profileStatus === 'returned');

// An application already in the chain is not editable; show its progress
// instead of a fresh wizard. Scoped to the offering being applied for — a
// settled application used to lock the form for every future offering too,
// which made the real cap one application per applicant however high
// max_applications_per_user was set.
const activeApplication = computed(() => props.activeApplications.find(
  (application) => application.share_offering_id === form.payload.share_offering_id,
) || null);

const applicationInReview = computed(() => Boolean(activeApplication.value));

// A returned application is handed back to be corrected, so it comes with the
// form rather than instead of it.
const applicationReturned = computed(() => props.draft?.status === 'returned');

const selectedOffering = computed(
  () => props.offerings.find((offering) => offering.id === form.payload.share_offering_id) || null,
);

const estimatedTotal = computed(() => {
  if (!selectedOffering.value) return '0.00';
  const shares = Math.max(1, Number.parseInt(form.payload.shares_applied || 1, 10));

  return (shares * Number.parseFloat(selectedOffering.value.share_rate)).toFixed(2);
});

const sharesRemaining = computed(() => selectedOffering.value?.shares_remaining ?? null);

const maxApplicable = computed(() => {
  if (!selectedOffering.value) return null;
  if (sharesRemaining.value === null) return selectedOffering.value.max_shares;

  return Math.min(selectedOffering.value.max_shares, sharesRemaining.value);
});

const fullySubscribed = computed(() => sharesRemaining.value === 0);

watch(selectedOffering, (offering) => {
  if (offering && form.payload.shares_applied < offering.min_shares) {
    form.payload.shares_applied = offering.min_shares;
  }
}, { immediate: true });

watch([maxApplicable, () => form.payload.shares_applied], ([max, shares]) => {
  if (max !== null && max > 0 && Number(shares) > max) {
    form.payload.shares_applied = max;
  }
});

// Focal person is confirmed by code, never picked from a list: eligibility is
// "any KYC-approved user", so a dropdown here would publish their names to
// every applicant. This lookup is a courtesy check — the save is what actually
// validates the code.
const focalLookup = ref(props.focalPerson
  ? { state: 'found', name: props.focalPerson.name, message: '' }
  : { state: 'idle', name: '', message: '' });

let focalTimer = null;

const lookupFocalPerson = async (code) => {
  const trimmed = (code || '').trim();

  if (!trimmed) {
    focalLookup.value = { state: 'idle', name: '', message: '' };
    return;
  }

  focalLookup.value = { state: 'checking', name: '', message: '' };

  try {
    const { data } = await window.axios.get(route('focal-persons.lookup'), { params: { code: trimmed } });
    focalLookup.value = { state: 'found', name: data.name, message: '' };
  } catch (error) {
    focalLookup.value = {
      state: 'missing',
      name: '',
      message: error.response?.status === 429
        ? 'Too many attempts — wait a minute, then try again.'
        : 'No focal person found with that code.',
    };
  }
};

watch(() => form.payload.focal_person_code, (code) => {
  clearTimeout(focalTimer);
  focalTimer = setTimeout(() => lookupFocalPerson(code), 400);
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
    // Inertia keeps component state across a POST, so without this the rows
    // would still be carrying id: null after the server assigned them — and
    // the next save would read as a fresh set, taking the uploaded slips with
    // it. Re-seeding from the saved draft also clears the File objects that
    // have now been stored and revokes their previews.
    onSuccess: () => {
      form.payload.vouchers.forEach((voucher) => {
        if (voucher.preview) URL.revokeObjectURL(voucher.preview);
      });

      const saved = mapSavedVouchers();
      form.payload.vouchers = saved.length ? saved : [blankVoucher()];
    },
  });
};

const submitFinal = () => {
  const id = props.draft?.id;
  if (!id || !profileReady.value) return;
  useForm({
    declaration_accepted: form.payload.declaration_accepted,
  }).post(route('applications.submit', id));
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

      <div v-if="applicationInReview" class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-800 shadow-sm">
        <h4 class="text-lg font-semibold">Application Under Review</h4>
        <p class="mt-2 text-sm">
          Application {{ activeApplication.application_number }} is
          <span class="font-semibold">{{ activeApplication.status_label }}</span>
          and cannot be edited while the review team works through it.
          You will be notified if anything needs changing.
        </p>
      </div>

      <!-- Sits above the form, not instead of it: the applicant has been asked
           to correct this application, so they need something to correct it
           in. The form below is pre-filled with what they submitted. -->
      <div v-else-if="applicationReturned && profileReady" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-red-800 shadow-sm">
        <h4 class="text-lg font-semibold">Application Returned for Correction</h4>
        <p class="mt-2 text-sm">
          Application {{ draft.application_number }} was returned so you can correct it.
          <span v-if="returnedReason" class="font-semibold">
            Reason: {{ returnedReason }}
          </span>
          Make your changes below and submit again. It then goes back through all three review stages.
        </p>
      </div>

      <div v-if="!applicationInReview && !profileReady" class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-800 shadow-sm">
        <h4 class="text-lg font-semibold">
          {{ profileInReview ? 'Profile Under Review' : profileReturned ? 'Profile Returned for Correction' : 'Profile Approval Required' }}
        </h4>
        <p class="mt-2 text-sm">
          <template v-if="profileInReview">
            Your profile is with the review team and cannot be edited right now.
            You can apply for shares as soon as it is approved.
          </template>
          <template v-else-if="profileReturned">
            Your profile was returned so you can correct it. Update it and submit again — it then goes back through all three review stages.
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

      <!-- Stated explicitly rather than as a v-else: the returned banner now
           sits above this block instead of replacing it, so a dangling v-else
           would attach to the wrong branch. -->
      <div v-if="!applicationInReview && profileReady" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-6">
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
              <p v-if="fullySubscribed" class="mt-2 text-xs font-semibold text-red-600">
                Fully subscribed — no shares remaining, new applications cannot be submitted
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Shares Applied</label>
              <input
                v-model="form.payload.shares_applied"
                type="number"
                :min="selectedOffering?.min_shares || 1"
                :max="maxApplicable ?? selectedOffering?.max_shares"
                :disabled="fullySubscribed"
                :class="inputClass('shares_applied')"
              />
              <InputError :message="payloadError('shares_applied')" class="mt-1" />
              <p
                v-if="maxApplicable !== null && selectedOffering && maxApplicable > 0 && maxApplicable < selectedOffering.max_shares"
                class="mt-1 text-xs text-amber-700"
              >
                Only {{ maxApplicable.toLocaleString() }} shares can still be applied for.
              </p>
            </div>
            <!-- Spans the full row so the summary doesn't leave a dead cell. -->
            <dl class="md:col-span-3 grid grid-cols-2 divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white sm:grid-cols-3 sm:divide-x">
              <div class="px-4 py-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Rate per share</dt>
                <dd class="mt-0.5 text-sm font-semibold text-gray-900">
                  {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ selectedOffering?.share_rate || '—' }}
                </dd>
              </div>
              <div class="px-4 py-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Shares applied</dt>
                <dd class="mt-0.5 text-sm font-semibold text-gray-900">
                  {{ Number(form.payload.shares_applied || 0).toLocaleString() }}
                </dd>
              </div>
              <div class="col-span-2 border-t border-gray-200 bg-gray-50 px-4 py-3 sm:col-span-1 sm:border-t-0">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Total payable</dt>
                <dd class="mt-0.5 text-base font-bold text-gray-900">
                  {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ estimatedTotal }}
                </dd>
              </div>
            </dl>
          </div>

          <div class="mt-5 border-t border-gray-200 pt-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <h5 class="text-sm font-semibold text-gray-900">Bank Vouchers</h5>
              <p class="text-xs text-gray-500">
                Paid in more than one deposit? Add a voucher for each. Every slip needs its own transaction code.
              </p>
            </div>

            <div
              v-for="(voucher, index) in form.payload.vouchers"
              :key="voucher.key"
              class="mt-3 rounded-lg border border-gray-200 bg-white p-4"
            >
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Voucher {{ index + 1 }}</span>
                <button
                  v-if="form.payload.vouchers.length > 1"
                  type="button"
                  class="text-xs font-medium text-red-600 hover:text-red-800 hover:underline"
                  @click="removeVoucher(index)"
                >
                  Remove
                </button>
              </div>

              <!-- Six tracks so five fields still fill every row: 2+2+2, then 3+3. -->
              <div class="mt-3 grid gap-4 md:grid-cols-6">
                <div class="md:col-span-2">
                  <label class="mb-1 block text-sm font-medium text-gray-700">Payment Type</label>
                  <select v-model="voucher.payment_type" :class="inputClass(`vouchers.${index}.payment_type`)">
                    <option value="">Select payment type</option>
                    <option value="cheque">Cheque</option>
                    <option value="self_cheque_deposit">Self Cheque Deposit</option>
                    <option value="online_transfer">Online Transfer</option>
                    <option value="cash">Cash</option>
                    <option value="connect_ips">ConnectIPS</option>
                    <option value="mobile_banking">Mobile Banking</option>
                  </select>
                  <InputError :message="payloadError(`vouchers.${index}.payment_type`)" class="mt-1" />
                </div>
                <div class="md:col-span-2">
                  <label class="mb-1 block text-sm font-medium text-gray-700">Paying Bank</label>
                  <input v-model="voucher.deposited_bank" type="text" placeholder="e.g. Nepal Bank Limited" :class="inputClass(`vouchers.${index}.deposited_bank`)" />
                  <InputError :message="payloadError(`vouchers.${index}.deposited_bank`)" class="mt-1" />
                </div>
                <div class="md:col-span-2">
                  <label class="mb-1 block text-sm font-medium text-gray-700">Transaction Code / Cheque No</label>
                  <input v-model="voucher.transaction_code" type="text" placeholder="e.g. 1234567 or cheque number" :class="inputClass(`vouchers.${index}.transaction_code`)" />
                  <InputError :message="payloadError(`vouchers.${index}.transaction_code`)" class="mt-1" />
                </div>
                <div class="md:col-span-3">
                  <label class="mb-1 block text-sm font-medium text-gray-700">ASBA Reference</label>
                  <input v-model="voucher.asba_reference" type="text" placeholder="Reference from this bank, if available" :class="inputClass(`vouchers.${index}.asba_reference`)" />
                  <InputError :message="payloadError(`vouchers.${index}.asba_reference`)" class="mt-1" />
                </div>
                <div class="md:col-span-3">
                  <label class="mb-1 block text-sm font-medium text-gray-700">Amount Deposited</label>
                  <input v-model="voucher.amount" type="number" step="0.01" min="0" placeholder="Leave blank to split the total" :class="inputClass(`vouchers.${index}.amount`)" />
                  <InputError :message="payloadError(`vouchers.${index}.amount`)" class="mt-1" />
                </div>
                <div class="md:col-span-3">
                  <label class="mb-1 block text-sm font-medium text-gray-700">Date of Deposit</label>
                  <input v-model="voucher.payment_date" type="date" :max="today" :class="inputClass(`vouchers.${index}.payment_date`)" />
                  <InputError :message="payloadError(`vouchers.${index}.payment_date`)" class="mt-1" />
                  <p class="mt-1 text-xs text-gray-500">The day the money left your account, as shown on the slip.</p>
                </div>
                <div class="md:col-span-6">
                  <label class="mb-1 block text-sm font-medium text-gray-700">Bank Voucher Image</label>
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    @change="onVoucherChange($event, index)"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100"
                  />
                  <InputError :message="payloadError(`vouchers.${index}.image`)" class="mt-1" />
                  <p class="mt-1 text-xs text-gray-500">
                    Photo or screenshot of this deposit's voucher/receipt (JPG, PNG or WebP, max 5&nbsp;MB), then save the draft.
                  </p>
                  <div v-if="voucher.preview" class="mt-2">
                    <img :src="voucher.preview" alt="Bank voucher preview" class="max-h-48 rounded-lg border border-gray-200 object-contain" />
                  </div>
                  <p v-else-if="voucher.has_image" class="mt-2 text-sm text-emerald-700">
                    ✓ Voucher uploaded —
                    <a :href="route('applications.voucher-image', [draft.id, voucher.id])" target="_blank" class="font-medium underline hover:text-emerald-800">view current image</a>.
                    Choosing a new file replaces it on save.
                  </p>
                </div>
              </div>
            </div>

            <button
              type="button"
              class="mt-3 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:bg-gray-50"
              @click="addVoucher"
            >
              + Add another voucher
            </button>
          </div>
        </section>

        <section class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 sm:p-5">
          <h4 class="text-lg font-semibold text-gray-900">Investment and Heir Details</h4>
          <div class="mt-4 space-y-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Investment Source</label>
              <p class="mb-2 text-xs text-gray-500">Tick every source funding this investment.</p>
              <div class="grid gap-2 sm:grid-cols-3">
                <label v-for="option in investmentSourceOptions" :key="option.value" class="flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="form.payload.investment_sources" type="checkbox" :value="option.value" class="rounded border-gray-300 text-blue-600" />
                  {{ option.label }}
                </label>
              </div>
              <InputError :message="payloadError('investment_sources')" class="mt-1" />
            </div>

            <!-- Three heir fields fill a three-column row exactly. -->
            <div class="border-t border-rose-100 pt-4">
              <h5 class="mb-3 text-sm font-semibold text-gray-900">Share Heir</h5>
              <div class="grid gap-4 sm:grid-cols-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
                  <input v-model="form.payload.share_heir_name" placeholder="e.g. Sita Sharma" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Relation</label>
                  <input v-model="form.payload.share_heir_relation" placeholder="e.g. Daughter" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Mobile</label>
                  <input v-model="form.payload.share_heir_mobile" placeholder="e.g. 98XXXXXXXX" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-xl border border-sky-100 bg-sky-50/50 p-4 sm:p-5">
          <h4 class="text-lg font-semibold text-gray-900">Focal Person</h4>
          <p class="mt-1 max-w-[75ch] text-sm text-gray-700">
            If someone referred you, enter the focal person code they gave you and check that the
            name shown is theirs. Leave it blank if nobody referred you.
          </p>

          <div class="mt-4 max-w-sm">
            <label class="mb-1 block text-sm font-medium text-gray-700">Focal Person Code</label>
            <input
              v-model="form.payload.focal_person_code"
              type="text"
              placeholder="e.g. FP-0007"
              autocomplete="off"
              :class="inputClass('focal_person_code')"
            />
            <InputError :message="payloadError('focal_person_code')" class="mt-1" />

            <p v-if="focalLookup.state === 'checking'" class="mt-1 text-xs text-gray-500">Checking code…</p>
            <p v-else-if="focalLookup.state === 'found'" class="mt-1 text-sm font-medium text-emerald-700">
              ✓ {{ focalLookup.name }}
            </p>
            <p v-else-if="focalLookup.state === 'missing'" class="mt-1 text-sm text-red-600">
              {{ focalLookup.message }}
            </p>
          </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 sm:p-5">
          <label class="flex items-start gap-3 text-sm text-gray-700">
            <input v-model="form.payload.declaration_accepted" type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600" />
            <span>I confirm that the profile and share application information provided above is true and complete.</span>
          </label>
          <InputError :message="payloadError('declaration_accepted') || form.errors.declaration_accepted" class="mt-2" />
        </section>

        <div class="flex flex-wrap items-center justify-end gap-3">
          <!-- Preview needs a saved draft, since the form renders from stored data. -->
          <Link
            v-if="hasDraft"
            :href="route('applications.show', draft.id)"
            class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:bg-gray-50"
          >
            Preview Printed Form
          </Link>
          <button
            @click="saveDraft"
            class="rounded-lg bg-blue-600 px-5 py-2.5 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
            :disabled="form.processing || fullySubscribed"
          >
            Save Application Draft
          </button>
          <button
            @click="submitFinal"
            class="rounded-lg bg-emerald-600 px-5 py-2.5 text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
            :disabled="!hasDraft || !form.payload.declaration_accepted || fullySubscribed"
          >
            Submit Application
          </button>
        </div>
      </div>

    </div>
  </PanelLayout>
</template>
