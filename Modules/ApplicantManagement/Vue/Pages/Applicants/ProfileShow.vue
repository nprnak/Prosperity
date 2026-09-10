<script setup>
import StageActions from '@/Components/StageActions.vue';
import WorkflowTimeline from '@/Components/WorkflowTimeline.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * The record a KYC stage signs off on. Everything the applicant submitted is
 * here — scalars, addresses, nominees and the uploaded identity documents —
 * because the queue deliberately carries no action buttons any more.
 */
const props = defineProps({
    applicant: { type: Object, required: true },
    completionChecks: { type: Object, default: () => ({}) },
    completionPercent: { type: Number, default: 0 },
    // False when this user lacks the pending stage, or already acted at
    // another stage this cycle. The page stays readable either way.
    canAct: { type: Boolean, default: false },
    documentTypes: { type: Array, default: () => [] },
    // Only super_admin holds focal-person.manage; everyone else sees the
    // attribution read-only.
    canManageFocalPerson: { type: Boolean, default: false },
    focalPersons: { type: Array, default: () => [] },
});

const dash = '—';
const show = (value) => (value === null || value === undefined || value === '' ? dash : value);

// 10 digits displayed as 000-111-111-1, matching the KYC form's input.
const showNid = (value) => {
    if (!value) return dash;
    const parts = [value.slice(0, 3), value.slice(3, 6), value.slice(6, 9), value.slice(9, 10)];
    return parts.filter(Boolean).join('-');
};

const formatDate = (value) => (value
    ? new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' })
    : dash);

const formatWhen = (value) => (value
    ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
    : dash);

const yesNo = (value) => (value ? 'Yes' : 'No');

const a = props.applicant;

const address = (record) => (record
    ? [record.province, record.district, record.local_level, record.ward_no ? `Ward ${record.ward_no}` : null,
        record.tole || record.street].filter(Boolean).join(', ')
    : dash);

// Rendered as label/value pairs so every section keeps the same shape.
const identity = computed(() => [
    ['Full name (EN)', show(a.full_name_en)],
    ['Full name (NP)', show(a.full_name_np)],
    ['Date of birth', `${formatDate(a.date_of_birth)}${a.age ? ` (age ${a.age})` : ''}`],
    ['Gender', show(a.gender)],
    ['Nationality', show(a.nationality)],
    ['Marital status', show(a.marital_status)],
    ['Father (EN)', show(a.father_name_en)],
    ['Father (NP)', show(a.father_name_np)],
    ['Mother (EN)', show(a.mother_name_en)],
    ['Mother (NP)', show(a.mother_name_np)],
    ['Grandfather (EN)', show(a.grandfather_name_en)],
    ['Grandfather (NP)', show(a.grandfather_name_np)],
    ['Spouse (EN)', show(a.spouse_name_en)],
    ['Spouse (NP)', show(a.spouse_name_np)],
    ['Education', show(a.education)],
    ['Occupation', show(a.occupation)],
]);

const contact = computed(() => [
    ['Mobile', show(a.mobile)],
    ['Email', show(a.email || a.user?.email)],
    ['Permanent address', address(a.permanent_address)],
    ['Temporary address', address(a.temporary_address)],
]);

const documentNumbers = computed(() => [
    ['Citizenship no.', show(a.citizenship_number)],
    ['Issued district', show(a.citizenship_issued_district)],
    ['Issued date', formatDate(a.citizenship_issued_date)],
    ['National ID no.', showNid(a.national_id_number)],
    ['PAN', show(a.pan_number)],
]);

const banking = computed(() => [
    ['BOID', show(a.boid)],
    ['Bank', show(a.bank_name)],
    ['Branch', show(a.bank_branch)],
    ['Account no.', show(a.bank_account_number)],
    ['Account holder', show(a.account_holder_name)],
    ['ASBA consent', yesNo(a.asba_consent)],
    ['Declaration accepted', yesNo(a.declaration_accepted)],
]);

const documentLabels = {
    photo: 'Photograph',
    citizenship_front: 'Citizenship — front',
    citizenship_back: 'Citizenship — back',
    national_id_front: 'National ID — front',
    national_id_back: 'National ID — back',
    pan: 'PAN certificate',
    signature: 'Signature',
};

const slugByType = {
    photo: 'photo',
    citizenship_front: 'citizenship-front',
    citizenship_back: 'citizenship-back',
    national_id_front: 'national-id-front',
    national_id_back: 'national-id-back',
    pan: 'pan',
    signature: 'signature',
};

const documentFor = (type) => (a.documents || []).find((doc) => doc.document_type === type) || null;

const isPdf = (doc) => (doc?.file_path || '').toLowerCase().endsWith('.pdf');

const documentUrl = (type, mode) =>
    route('applicants.profile.documents.show', { applicant: a.id, type: slugByType[type], mode });

// Every required upload, present or not — a missing one is itself a finding.
const documents = computed(() => props.documentTypes.map((type) => {
    const doc = documentFor(type);

    return {
        type,
        label: documentLabels[type] || type,
        present: doc !== null,
        pdf: isPdf(doc),
        previewUrl: doc ? documentUrl(type, 'preview') : null,
        downloadUrl: doc ? documentUrl(type, 'download') : null,
    };
}));

// Anything the applicant left unsatisfied. Should normally be empty — a
// profile cannot be submitted otherwise — so a non-empty list is a red flag.
const unmet = computed(() => Object.entries(props.completionChecks)
    .filter(([, satisfied]) => !satisfied)
    .map(([field]) => field.replace(/_/g, ' ')));

// Mirrors Modules\ApplicantManagement\Enums\SourceOfFunds.
const sourceLabels = {
    salary: 'Salary / Remuneration',
    dividend: 'Dividend',
    share_trading: 'Share Trading',
    property_sale: 'Sale of Property',
    house_rent: 'House Rent',
    foreign_employment: 'Foreign Employment',
    loan_or_borrowing: 'Loan or Borrowing',
    ancestral_property: 'Ancestral Property',
    business: 'Business / Trade',
    other: 'Other',
    savings: 'Savings',
};

const sources = computed(() => (a.sources_of_funds || [])
    .map((source) => {
        const label = sourceLabels[source.source_type] || source.source_type;
        return source.source_type === 'other' && source.description
            ? `${label} (${source.description})`
            : label;
    })
    .filter(Boolean));

// The applicant's default focal person. Applications keep their own copy, so
// changing this credits future applications only — it never rewrites figures
// already reported against an offering.
const focalPersonForm = useForm({ focal_person_id: props.applicant.focal_person_id ?? '' });

const saveFocalPerson = () => {
    focalPersonForm
        .transform((data) => ({ focal_person_id: data.focal_person_id === '' ? null : data.focal_person_id }))
        .patch(route('applicants.focal-person.update', props.applicant.id), { preserveScroll: true });
};

const focalPersonLabel = computed(() => (props.applicant.focal_person
    ? `${props.applicant.focal_person.name} (${props.applicant.focal_person.focal_person_code})`
    : 'Not assigned'));

// Correcting contact and banking details on a profile the chain has already
// approved. Identity is not here on purpose: those fields are what the three
// stages signed off on, so changing them means going round again.
const amendable = ['mobile', 'email', 'bank_name', 'bank_branch', 'bank_account_number', 'account_holder_name', 'boid'];

const amendForm = useForm({
    ...Object.fromEntries(amendable.map((field) => [field, props.applicant[field] ?? ''])),
    remarks: '',
});

const amending = ref(false);

const saveAmendment = () => {
    amendForm.patch(route('applicants.profile.amend', props.applicant.id), {
        preserveScroll: true,
        onSuccess: () => {
            amending.value = false;
            amendForm.remarks = '';
        },
    });
};
</script>

<template>
    <Head :title="`KYC — ${applicant.full_name_en || 'Applicant'}`" />

    <PanelLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-2xl font-bold text-gray-900">
                    {{ applicant.full_name_en || 'Applicant' }}
                </h2>
                <Link
                    :href="route('applicants.review')"
                    class="text-sm font-semibold text-brand-700 hover:text-brand-900"
                >
                    ← Back to queue
                </Link>
            </div>

            <!-- Status strip -->
            <section class="flex flex-wrap items-center gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-900 ring-1 ring-blue-200">
                    {{ applicant.profile_status_label }}
                </span>
                <span
                    v-if="applicant.pending_stage_label"
                    class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-800 ring-1 ring-gray-300"
                >
                    Awaiting {{ applicant.pending_stage_label }}
                </span>
                <span
                    v-if="applicant.workflow_cycle > 1"
                    class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200"
                >
                    Corrected · submission {{ applicant.workflow_cycle }}
                </span>
                <span class="ml-auto text-xs text-gray-600">
                    Submitted {{ formatWhen(applicant.profile_submitted_at) }} · {{ completionPercent }}% complete
                </span>
            </section>

            <p
                v-if="unmet.length"
                class="rounded-xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-red-200"
            >
                <span class="font-semibold">Incomplete:</span> {{ unmet.join(', ') }}
            </p>

            <!-- Attribution, not part of the KYC decision: saving it leaves the
                 review status exactly where it was. -->
            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Focal person</h3>

                <template v-if="canManageFocalPerson">
                    <p class="mt-1 max-w-[70ch] text-sm text-gray-700">
                        The default this applicant's future applications are credited to. They can quote a
                        different code on an individual application, and applications already submitted keep
                        whoever they were credited to.
                    </p>

                    <div class="mt-3 flex flex-wrap items-start gap-3">
                        <div class="min-w-[18rem] flex-1">
                            <select
                                v-model="focalPersonForm.focal_person_id"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="">— None —</option>
                                <option
                                    v-for="person in focalPersons"
                                    :key="person.id"
                                    :value="person.id"
                                >
                                    {{ person.name }} · {{ person.focal_person_code }}
                                </option>
                            </select>
                            <p v-if="focalPersonForm.errors.focal_person_id" class="mt-1 text-sm text-red-600">
                                {{ focalPersonForm.errors.focal_person_id }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-blue-300"
                            :disabled="focalPersonForm.processing"
                            @click="saveFocalPerson"
                        >
                            Save
                        </button>
                    </div>

                    <p v-if="!focalPersons.length" class="mt-2 text-sm text-amber-700">
                        No focal persons have been designated yet — do that from the Focal Persons screen first.
                    </p>
                </template>

                <p v-else class="mt-2 text-sm text-gray-900">{{ focalPersonLabel }}</p>
            </section>

            <!-- Documents first: they are what the review is actually checking. -->
            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Identity documents</h3>
                <p class="mt-1 text-sm text-gray-700">
                    Check each scan against the numbers recorded below.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <figure
                        v-for="doc in documents"
                        :key="doc.type"
                        class="overflow-hidden rounded-lg ring-1 ring-gray-200"
                    >
                        <div class="flex h-44 items-center justify-center bg-gray-50">
                            <a v-if="doc.present && !doc.pdf" :href="doc.previewUrl" target="_blank" rel="noopener">
                                <img
                                    :src="doc.previewUrl"
                                    :alt="doc.label"
                                    class="max-h-44 w-full object-contain"
                                />
                            </a>
                            <a
                                v-else-if="doc.present"
                                :href="doc.previewUrl"
                                target="_blank"
                                rel="noopener"
                                class="text-sm font-semibold text-[#031226] hover:text-[#063045]"
                            >
                                Open PDF
                            </a>
                            <span v-else class="text-sm font-medium text-red-700">Not uploaded</span>
                        </div>

                        <figcaption class="flex items-center justify-between gap-2 border-t border-gray-100 px-3 py-2">
                            <span class="text-sm font-medium text-slate-800">{{ doc.label }}</span>
                            <a
                                v-if="doc.present"
                                :href="doc.downloadUrl"
                                target="_blank"
                                rel="noopener"
                                class="text-xs font-semibold text-emerald-700 hover:text-emerald-900"
                            >
                                Download
                            </a>
                        </figcaption>
                    </figure>
                </div>
            </section>

            <!-- Detail sections, ordered as the applicant filled them in. -->
            <section
                v-for="group in [
                    { title: 'Document numbers', rows: documentNumbers },
                    { title: 'Personal details', rows: identity },
                    { title: 'Contact and address', rows: contact },
                    { title: 'Bank and MeroShare', rows: banking },
                ]"
                :key="group.title"
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200"
            >
                <h3 class="text-lg font-semibold text-gray-900">{{ group.title }}</h3>
                <dl class="mt-3 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div v-for="[label, value] in group.rows" :key="label">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</dt>
                        <dd class="mt-0.5 text-sm text-slate-800">{{ value }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Sources of funds</h3>
                <p class="mt-2 text-sm text-gray-900">{{ sources.length ? sources.join(', ') : dash }}</p>
            </section>

            <section v-if="applicant.nominees?.length" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Nominee</h3>
                <dl
                    v-for="nominee in applicant.nominees"
                    :key="nominee.id"
                    class="mt-3 grid gap-x-6 gap-y-3 sm:grid-cols-2"
                >
                    <div v-for="[label, value] in [
                        ['Name', show(nominee.full_name)],
                        ['Relation', show(nominee.relationship)],
                        ['Mobile', show(nominee.mobile)],
                        ['Address', show(nominee.address)],
                    ]" :key="label">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-600">{{ label }}</dt>
                        <dd class="mt-0.5 text-sm text-gray-900">{{ value }}</dd>
                    </div>
                </dl>
            </section>

            <section v-if="applicant.experiences?.length" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Experience</h3>
                <ul class="mt-3 space-y-2">
                    <li v-for="experience in applicant.experiences" :key="experience.id" class="text-sm text-gray-900">
                        {{ show(experience.position) }} · {{ show(experience.organization_name) }}
                        <span class="text-gray-600">({{ show(experience.years) }})</span>
                    </li>
                </ul>
            </section>

            <!-- The decision, at the bottom of the evidence it rests on. -->
            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Your decision</h3>

                <StageActions
                    v-if="canAct"
                    class="mt-3"
                    :action-url="route('applicants.profile.act', applicant.id)"
                    :can-send-back="applicant.can_send_back"
                />

                <p v-else class="mt-2 max-w-[70ch] text-sm text-gray-700">
                    You cannot act on this profile — either it is not waiting on a stage you hold, or
                    you already acted at another stage of this submission. A separate person must take
                    the remaining stages.
                </p>
            </section>

            <!-- Contact and banking details go stale after approval, and an
                 approved profile is otherwise frozen against every one of
                 them. Identity is deliberately absent: that is what the three
                 stages signed off on. -->
            <section
                v-if="applicant.profile_status === 'approved' && $page.props.auth?.permissions?.includes('profile.approve')"
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Amend approved details</h3>
                        <p class="mt-1 max-w-[70ch] text-sm text-gray-700">
                            Correct contact or banking details without sending this profile back through
                            the chain. Every change is recorded against you in the activity log.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="amending = !amending"
                    >
                        {{ amending ? 'Cancel' : 'Amend' }}
                    </button>
                </div>

                <form v-if="amending" class="mt-4 space-y-4" @submit.prevent="saveAmendment">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label v-for="field in amendable" :key="field" class="block">
                            <span class="text-sm font-medium text-gray-700 capitalize">{{ field.replace(/_/g, ' ') }}</span>
                            <input
                                v-model="amendForm[field]"
                                type="text"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            />
                            <span v-if="amendForm.errors[field]" class="mt-1 block text-sm text-red-600">
                                {{ amendForm.errors[field] }}
                            </span>
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Reason for the amendment</span>
                        <textarea
                            v-model="amendForm.remarks"
                            rows="2"
                            placeholder="e.g. Bank account corrected from the updated cheque copy."
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        ></textarea>
                    </label>

                    <button
                        type="submit"
                        :disabled="amendForm.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                    >
                        Save amendment
                    </button>
                </form>
            </section>

            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Review trail</h3>
                <div class="mt-3">
                    <WorkflowTimeline :events="applicant.workflow_events ?? []" />
                </div>
            </section>
        </div>
    </PanelLayout>
</template>
