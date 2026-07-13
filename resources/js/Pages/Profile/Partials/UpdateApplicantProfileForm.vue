<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const applicant = page.props.applicant || {};
const user = page.props.auth.user;

const form = useForm({
    full_name_nepali: applicant.full_name_nepali || '',
    date_of_birth: applicant.date_of_birth || '',
    age: applicant.age || '',
    nationality: applicant.nationality || 'Nepali',
    father_name: applicant.father_name || '',
    grandfather_name: applicant.grandfather_name || '',
    marital_status: applicant.marital_status || 'single',
    spouse_name: applicant.spouse_name || '',
    education: applicant.education || '',
    occupation: applicant.occupation || '',
    mobile_number: applicant.mobile_number || '',
    permanent_district: applicant.permanent_district || '',
    permanent_municipality: applicant.permanent_municipality || '',
    permanent_ward: applicant.permanent_ward || '',
    permanent_tole: applicant.permanent_tole || '',
    temporary_district: applicant.temporary_district || '',
    temporary_municipality: applicant.temporary_municipality || '',
    temporary_ward: applicant.temporary_ward || '',
    temporary_tole: applicant.temporary_tole || '',
    citizenship_number: applicant.citizenship_number || '',
    citizenship_issue_district: applicant.citizenship_issue_district || '',
    citizenship_issue_date: applicant.citizenship_issue_date || '',
    national_id_number: applicant.national_id_number || '',
    pan_number: applicant.pan_number || '',
    boid: applicant.boid || '',
    crn_number: applicant.crn_number || '',
    bank_name: applicant.bank_name || '',
    bank_code: applicant.bank_code || '',
    bank_branch: applicant.bank_branch || '',
    bank_account_number: applicant.bank_account_number || '',
    account_holder_name: applicant.account_holder_name || '',
    asba_consent: Boolean(applicant.asba_consent),
    photo: null,
    citizenship_doc: null,
    national_id_doc: null,
    pan_doc: null,
});

const fieldClass = (field) => {
    const base = 'mt-1 block w-full rounded-lg border px-3 py-2';
    return form.errors[field]
        ? `${base} border-red-400 focus:border-red-500 focus:ring-red-500`
        : `${base} border-gray-300`;
};

const hasDocument = (field) => Boolean(applicant[field]);

const previewLink = (type) => route('profile.documents.show', { type, mode: 'preview' });
const downloadLink = (type) => route('profile.documents.show', { type, mode: 'download' });

const hasSavedProfile = computed(() => Boolean(applicant.id));
const status = computed(() => page.props.status);
const isEditing = ref(!hasSavedProfile.value);

watch(
    () => form.recentlySuccessful,
    (saved) => {
        if (saved) {
            isEditing.value = false;
        }
    },
);

const displayValue = (value, fallback = '-') => {
    if (value === null || value === undefined) {
        return fallback;
    }

    const normalized = String(value).trim();
    return normalized === '' ? fallback : normalized;
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-gray-900">
                Share Application Profile
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Fill all required profile information here. This data is used for your share application.
            </p>
        </header>

        <div v-if="hasSavedProfile && !isEditing" class="p-4 mt-6 space-y-4 border rounded-xl border-emerald-100 bg-emerald-50/50 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Submitted Profile View</h3>
                    <p class="mt-1 text-sm text-gray-600">Review your submitted details below. Click edit anytime if something needs correction.</p>
                </div>
                <button
                    type="button"
                    class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                    @click="isEditing = true"
                >
                    Edit Profile
                </button>
            </div>

            <p
                v-if="status === 'applicant-profile-updated'"
                class="px-4 py-2 text-sm text-green-700 border border-green-200 rounded-lg bg-green-50"
            >
                Profile saved successfully. You can review the details here and edit again when needed.
            </p>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Full Name (English)</p>
                    <p class="mt-1 text-sm text-gray-900">{{ displayValue(applicant.full_name_english || user.name) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Full Name (Nepali)</p>
                    <p class="mt-1 text-sm text-gray-900">{{ displayValue(applicant.full_name_nepali) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Date of Birth</p>
                    <p class="mt-1 text-sm text-gray-900">{{ displayValue(applicant.date_of_birth) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Age</p>
                    <p class="mt-1 text-sm text-gray-900">{{ displayValue(applicant.age) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Mobile Number</p>
                    <p class="mt-1 text-sm text-gray-900">{{ displayValue(applicant.mobile_number) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Marital Status</p>
                    <p class="mt-1 text-sm text-gray-900 capitalize">{{ displayValue(applicant.marital_status) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Permanent Address</p>
                    <p class="mt-1 text-sm text-gray-900">
                        {{ displayValue(applicant.permanent_district) }},
                        {{ displayValue(applicant.permanent_municipality) }}-{{ displayValue(applicant.permanent_ward) }}
                        {{ displayValue(applicant.permanent_tole, '') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Education / Occupation</p>
                    <p class="mt-1 text-sm text-gray-900">{{ displayValue(applicant.education) }} / {{ displayValue(applicant.occupation) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">BOID / CRN</p>
                    <p class="mt-1 text-sm text-gray-900">{{ displayValue(applicant.boid) }} / {{ displayValue(applicant.crn_number) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Bank</p>
                    <p class="mt-1 text-sm text-gray-900">{{ displayValue(applicant.bank_name) }} {{ displayValue(applicant.bank_branch, '') }}</p>
                </div>
            </div>

            <div class="p-4 bg-white border border-gray-200 rounded-lg">
                <h4 class="text-sm font-semibold text-gray-800">Uploaded Documents</h4>
                <div class="grid gap-3 mt-3 md:grid-cols-2">
                    <div class="p-3 text-sm border border-gray-100 rounded-md">
                        <p class="font-medium text-gray-800">Photo</p>
                        <div v-if="hasDocument('photo_path')" class="flex gap-3 mt-2 text-xs">
                            <a :href="previewLink('photo')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('photo')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <p v-else class="mt-1 text-xs text-red-600">Not uploaded</p>
                    </div>
                    <div class="p-3 text-sm border border-gray-100 rounded-md">
                        <p class="font-medium text-gray-800">Citizenship</p>
                        <div v-if="hasDocument('citizenship_doc_path')" class="flex gap-3 mt-2 text-xs">
                            <a :href="previewLink('citizenship')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('citizenship')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <p v-else class="mt-1 text-xs text-red-600">Not uploaded</p>
                    </div>
                    <div class="p-3 text-sm border border-gray-100 rounded-md">
                        <p class="font-medium text-gray-800">National ID</p>
                        <div v-if="hasDocument('national_id_doc_path')" class="flex gap-3 mt-2 text-xs">
                            <a :href="previewLink('national-id')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('national-id')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <p v-else class="mt-1 text-xs text-red-600">Not uploaded</p>
                    </div>
                    <div class="p-3 text-sm border border-gray-100 rounded-md">
                        <p class="font-medium text-gray-800">PAN</p>
                        <div v-if="hasDocument('pan_doc_path')" class="flex gap-3 mt-2 text-xs">
                            <a :href="previewLink('pan')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('pan')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <p v-else class="mt-1 text-xs text-red-600">Not uploaded</p>
                    </div>
                </div>
            </div>
        </div>

        <form v-if="isEditing || !hasSavedProfile" @submit.prevent="form.patch(route('profile.applicant.update'), { forceFormData: true })" class="mt-6 space-y-6">
            <p v-if="$page.props.errors.documents" class="px-4 py-3 text-sm text-red-700 border border-red-200 rounded-lg bg-red-50">
                {{ $page.props.errors.documents }}
            </p>

            <div v-if="hasSavedProfile" class="px-4 py-3 text-sm text-blue-800 border border-blue-100 rounded-lg bg-blue-50">
                Editing mode is enabled. Update any fields and save to refresh your submitted profile view.
            </div>

            <div class="p-4 border border-blue-100 rounded-xl bg-blue-50/40">
                <h3 class="text-sm font-semibold text-blue-800">Linked Login Details</h3>
                <div class="grid gap-4 mt-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Full Name (English)</label>
                        <TextInput :model-value="user.name" type="text" class="block w-full mt-1 bg-gray-100" readonly />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Email</label>
                        <TextInput :model-value="user.email" type="email" class="block w-full mt-1 bg-gray-100" readonly />
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700">Full Name (Nepali) *</label>
                    <TextInput v-model="form.full_name_nepali" type="text" placeholder="e.g. प्रणय शर्मा" :class="fieldClass('full_name_nepali')" />
                    <InputError class="mt-1" :message="form.errors.full_name_nepali" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Date of Birth *</label>
                    <TextInput v-model="form.date_of_birth" type="date" :class="fieldClass('date_of_birth')" />
                    <InputError class="mt-1" :message="form.errors.date_of_birth" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Age *</label>
                    <TextInput v-model="form.age" type="number" min="0" placeholder="e.g. 28" :class="fieldClass('age')" />
                    <InputError class="mt-1" :message="form.errors.age" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Nationality</label>
                    <TextInput v-model="form.nationality" type="text" placeholder="e.g. Nepali" :class="fieldClass('nationality')" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Father's Name *</label>
                    <TextInput v-model="form.father_name" type="text" placeholder="e.g. Krishna Prasad Sharma" :class="fieldClass('father_name')" />
                    <InputError class="mt-1" :message="form.errors.father_name" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Grandfather's Name *</label>
                    <TextInput v-model="form.grandfather_name" type="text" placeholder="e.g. Tej Bahadur Sharma" :class="fieldClass('grandfather_name')" />
                    <InputError class="mt-1" :message="form.errors.grandfather_name" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Mobile Number *</label>
                    <TextInput v-model="form.mobile_number" type="text" placeholder="e.g. 98XXXXXXXX" :class="fieldClass('mobile_number')" />
                    <InputError class="mt-1" :message="form.errors.mobile_number" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Marital Status</label>
                    <select v-model="form.marital_status" :class="fieldClass('marital_status')">
                        <option value="single">Single</option>
                        <option value="married">Married</option>
                        <option value="divorced">Divorced</option>
                        <option value="widowed">Widowed</option>
                    </select>
                </div>
            </div>

            <div class="p-4 border border-gray-100 rounded-xl bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-800">Permanent Address (Required)</h3>
                <div class="grid gap-4 mt-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">District *</label>
                        <TextInput v-model="form.permanent_district" type="text" placeholder="e.g. Kathmandu" :class="fieldClass('permanent_district')" />
                        <InputError class="mt-1" :message="form.errors.permanent_district" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Municipality *</label>
                        <TextInput v-model="form.permanent_municipality" type="text" placeholder="e.g. Kathmandu Metropolitan" :class="fieldClass('permanent_municipality')" />
                        <InputError class="mt-1" :message="form.errors.permanent_municipality" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Ward *</label>
                        <TextInput v-model="form.permanent_ward" type="text" placeholder="e.g. 05" :class="fieldClass('permanent_ward')" />
                        <InputError class="mt-1" :message="form.errors.permanent_ward" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Tole</label>
                        <TextInput v-model="form.permanent_tole" type="text" placeholder="e.g. Baluwatar" :class="fieldClass('permanent_tole')" />
                    </div>
                </div>
            </div>

            <div class="p-4 border border-gray-100 rounded-xl bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-800">Additional Information</h3>
                <div class="grid gap-4 mt-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Spouse Name</label>
                        <TextInput v-model="form.spouse_name" type="text" placeholder="Optional" :class="fieldClass('spouse_name')" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Education</label>
                        <TextInput v-model="form.education" type="text" placeholder="e.g. Bachelor's Degree" :class="fieldClass('education')" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Occupation</label>
                        <TextInput v-model="form.occupation" type="text" placeholder="e.g. Engineer" :class="fieldClass('occupation')" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Citizenship Number</label>
                        <TextInput v-model="form.citizenship_number" type="text" placeholder="Optional" :class="fieldClass('citizenship_number')" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">National ID Number</label>
                        <TextInput v-model="form.national_id_number" type="text" placeholder="Optional" :class="fieldClass('national_id_number')" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">PAN Number</label>
                        <TextInput v-model="form.pan_number" type="text" placeholder="Optional" :class="fieldClass('pan_number')" />
                        <InputError class="mt-1" :message="form.errors.pan_number" />
                    </div>
                </div>
            </div>

            <div class="p-4 border rounded-xl border-emerald-100 bg-emerald-50">
                <h3 class="text-sm font-semibold text-emerald-800">MeroShare / C-ASBA Details (Required)</h3>
                <div class="grid gap-4 mt-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">BOID (16 digits) *</label>
                        <TextInput v-model="form.boid" type="text" placeholder="e.g. 1301234567890123" :class="fieldClass('boid')" />
                        <InputError class="mt-1" :message="form.errors.boid" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">CRN Number *</label>
                        <TextInput v-model="form.crn_number" type="text" placeholder="e.g. 1234567890" :class="fieldClass('crn_number')" />
                        <InputError class="mt-1" :message="form.errors.crn_number" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Bank Name *</label>
                        <TextInput v-model="form.bank_name" type="text" placeholder="e.g. Nepal Investment Mega Bank" :class="fieldClass('bank_name')" />
                        <InputError class="mt-1" :message="form.errors.bank_name" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Bank Code</label>
                        <TextInput v-model="form.bank_code" type="text" placeholder="Optional" :class="fieldClass('bank_code')" />
                        <InputError class="mt-1" :message="form.errors.bank_code" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Bank Branch *</label>
                        <TextInput v-model="form.bank_branch" type="text" placeholder="e.g. New Road" :class="fieldClass('bank_branch')" />
                        <InputError class="mt-1" :message="form.errors.bank_branch" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Bank Account Number *</label>
                        <TextInput v-model="form.bank_account_number" type="text" placeholder="e.g. 00100123456789" :class="fieldClass('bank_account_number')" />
                        <InputError class="mt-1" :message="form.errors.bank_account_number" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-gray-700">Account Holder Name *</label>
                        <TextInput v-model="form.account_holder_name" type="text" placeholder="As per bank account" :class="fieldClass('account_holder_name')" />
                        <InputError class="mt-1" :message="form.errors.account_holder_name" />
                    </div>
                </div>
                <label class="flex items-start gap-3 mt-4 text-sm text-gray-700">
                    <input v-model="form.asba_consent" type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600" />
                    <span>I authorize ASBA-style amount blocking and reconciliation using the details provided above.</span>
                </label>
                <InputError class="mt-1" :message="form.errors.asba_consent" />
            </div>

            <div class="p-4 border rounded-xl border-amber-100 bg-amber-50">
                <h3 class="text-sm font-semibold text-amber-800">Required Documents</h3>
                <p class="mt-1 text-sm text-amber-700">Upload all required documents before applying for shares.</p>
                <div class="grid gap-4 mt-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Recent Photo *</label>
                        <input type="file" class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-lg" @change="form.photo = $event.target.files[0]" />
                        <div v-if="hasDocument('photo_path')" class="flex gap-3 mt-2 text-xs">
                            <a :href="previewLink('photo')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('photo')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <InputError class="mt-1" :message="form.errors.photo" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Citizenship Document *</label>
                        <input type="file" class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-lg" @change="form.citizenship_doc = $event.target.files[0]" />
                        <div v-if="hasDocument('citizenship_doc_path')" class="flex gap-3 mt-2 text-xs">
                            <a :href="previewLink('citizenship')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('citizenship')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <InputError class="mt-1" :message="form.errors.citizenship_doc" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">National ID Document *</label>
                        <input type="file" class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-lg" @change="form.national_id_doc = $event.target.files[0]" />
                        <div v-if="hasDocument('national_id_doc_path')" class="flex gap-3 mt-2 text-xs">
                            <a :href="previewLink('national-id')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('national-id')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <InputError class="mt-1" :message="form.errors.national_id_doc" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">PAN Document *</label>
                        <input type="file" class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-lg" @change="form.pan_doc = $event.target.files[0]" />
                        <div v-if="hasDocument('pan_doc_path')" class="flex gap-3 mt-2 text-xs">
                            <a :href="previewLink('pan')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('pan')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <InputError class="mt-1" :message="form.errors.pan_doc" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">{{ hasSavedProfile ? 'Update Profile' : 'Save Profile' }}</PrimaryButton>
                <button
                    v-if="hasSavedProfile"
                    type="button"
                    class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
                    @click="isEditing = false"
                >
                    Cancel
                </button>
                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Profile saved.</p>
            </div>
        </form>
    </section>
</template>
