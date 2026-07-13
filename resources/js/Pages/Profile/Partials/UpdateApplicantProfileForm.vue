<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const applicant = usePage().props.applicant || {};
const user = usePage().props.auth.user;

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

        <form @submit.prevent="form.patch(route('profile.applicant.update'), { forceFormData: true })" class="mt-6 space-y-6">
            <p v-if="$page.props.errors.documents" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $page.props.errors.documents }}
            </p>

            <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-4">
                <h3 class="text-sm font-semibold text-blue-800">Linked Login Details</h3>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Full Name (English)</label>
                        <TextInput :model-value="user.name" type="text" class="mt-1 block w-full bg-gray-100" readonly />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Email</label>
                        <TextInput :model-value="user.email" type="email" class="mt-1 block w-full bg-gray-100" readonly />
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

            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                <h3 class="text-sm font-semibold text-gray-800">Permanent Address (Required)</h3>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
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

            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                <h3 class="text-sm font-semibold text-gray-800">Additional Information</h3>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
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
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                <h3 class="text-sm font-semibold text-amber-800">Required Documents</h3>
                <p class="mt-1 text-sm text-amber-700">Upload all required documents before applying for shares.</p>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Recent Photo *</label>
                        <input type="file" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2" @change="form.photo = $event.target.files[0]" />
                        <div v-if="hasDocument('photo_path')" class="mt-2 flex gap-3 text-xs">
                            <a :href="previewLink('photo')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('photo')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <InputError class="mt-1" :message="form.errors.photo" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Citizenship Document *</label>
                        <input type="file" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2" @change="form.citizenship_doc = $event.target.files[0]" />
                        <div v-if="hasDocument('citizenship_doc_path')" class="mt-2 flex gap-3 text-xs">
                            <a :href="previewLink('citizenship')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('citizenship')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <InputError class="mt-1" :message="form.errors.citizenship_doc" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">National ID Document *</label>
                        <input type="file" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2" @change="form.national_id_doc = $event.target.files[0]" />
                        <div v-if="hasDocument('national_id_doc_path')" class="mt-2 flex gap-3 text-xs">
                            <a :href="previewLink('national-id')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('national-id')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <InputError class="mt-1" :message="form.errors.national_id_doc" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">PAN Document *</label>
                        <input type="file" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2" @change="form.pan_doc = $event.target.files[0]" />
                        <div v-if="hasDocument('pan_doc_path')" class="mt-2 flex gap-3 text-xs">
                            <a :href="previewLink('pan')" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink('pan')" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                        <InputError class="mt-1" :message="form.errors.pan_doc" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save Profile</PrimaryButton>
                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Profile saved.</p>
            </div>
        </form>
    </section>
</template>
