<script setup>
import InputError from '@/Components/InputError.vue';
import NepaliDatePicker from '@/Components/NepaliDatePicker.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch, ref, reactive } from 'vue';

// Defaults reproduce the applicant's own self-service form exactly. A KYC
// Verifier entering someone else's paper KYC (Applicants/StaffKycForm.vue)
// overrides all four so the form saves to, and previews documents for, the
// applicant it was opened for rather than the logged-in staff member.
const props = defineProps({
    applicantUser: { type: Object, default: null },
    submitRouteName: { type: String, default: 'profile.applicant.update' },
    submitRouteParams: { type: [Object, Array], default: () => ({}) },
    documentRouteName: { type: String, default: 'profile.documents.show' },
    documentRouteParams: { type: Object, default: () => ({}) },
});

const page = usePage();
const profile = page.props.profile || {};
const user = props.applicantUser || page.props.auth.user;
const geography = page.props.geography || { provinces: [], districts: [], localLevels: [] };
// Enum-backed option lists, shared from ProfileController::edit.
const options = page.props.options || {
    titles: [], genders: [], maritalStatuses: [], educationLevels: [], sourcesOfFunds: [],
};

const permanent = profile.permanent_address || {};
const temporary = profile.temporary_address || {};
const savedSources = (profile.sources_of_funds || []).map((source) => source.source_type);
const savedSourceOtherDetail = (profile.sources_of_funds || []).find((source) => source.source_type === 'other')?.description || '';
const savedNominee = (profile.nominees || [])[0] || {};
const savedExperiences = (profile.experiences || []).map((experience) => ({
    organization_name: experience.organization_name || '',
    address: experience.address || '',
    position: experience.position || '',
    years: experience.years ?? '',
}));

const addressFields = ['province', 'district', 'local_level', 'ward_no', 'tole'];
const temporaryMatchesPermanent = temporary.id
    ? addressFields.every((field) => (temporary[field] || '') === (permanent[field] || ''))
    : true;

const form = useForm({
    // 1. Personal information
    title: profile.title || '',
    full_name_np: profile.full_name_np || '',
    date_of_birth: profile.date_of_birth || '',
    gender: profile.gender || '',
    nationality: profile.nationality || 'Nepali',
    marital_status: profile.marital_status || 'single',
    father_name_en: profile.father_name_en || '',
    father_name_np: profile.father_name_np || '',
    mother_name_en: profile.mother_name_en || '',
    mother_name_np: profile.mother_name_np || '',
    grandfather_name_en: profile.grandfather_name_en || '',
    grandfather_name_np: profile.grandfather_name_np || '',
    spouse_name_en: profile.spouse_name_en || '',
    spouse_name_np: profile.spouse_name_np || '',
    occupation: profile.occupation || '',
    education: profile.education || '',

    // 2. Contact
    mobile: profile.mobile || '',

    // 3 & 4. Addresses
    permanent: {
        province: permanent.province || '',
        district: permanent.district || '',
        local_level: permanent.local_level || '',
        ward_no: permanent.ward_no || '',
        tole: permanent.tole || '',
    },
    temporary_same_as_permanent: temporaryMatchesPermanent,
    temporary: {
        province: temporary.province || '',
        district: temporary.district || '',
        local_level: temporary.local_level || '',
        ward_no: temporary.ward_no || '',
        tole: temporary.tole || '',
    },

    // 5. Identity
    citizenship_number: profile.citizenship_number || '',
    citizenship_number_np: profile.citizenship_number_np || '',
    citizenship_issued_district: profile.citizenship_issued_district || '',
    citizenship_issued_date: profile.citizenship_issued_date || '',
    national_id_number: profile.national_id_number || '',
    pan_number: profile.pan_number || '',

    // 6. Documents
    photo: null,
    citizenship_front: null,
    citizenship_back: null,
    national_id_front: null,
    national_id_back: null,
    pan_doc: null,
    signature: null,

    // 7. Source of investment — optional; "Other" carries a free-text detail.
    sources: savedSources,
    source_other_detail: savedSourceOtherDetail,

    // 8. Nominee
    nominee: {
        full_name: savedNominee.full_name || '',
        relationship: savedNominee.relationship || '',
        mobile: savedNominee.mobile || '',
        address: savedNominee.address || '',
    },

    // 9. Professional experience
    experiences: savedExperiences,

    // 10. Declaration — one combined statement rather than three separate ticks.
    declarations: {
        accepted: Boolean(profile.declaration_accepted),
    },

    // MeroShare / C-ASBA
    boid: profile.boid || '',
    bank_name: profile.bank_name || '',
    bank_branch: profile.bank_branch || '',
    bank_account_number: profile.bank_account_number || '',
    account_holder_name: profile.account_holder_name || '',
    asba_consent: Boolean(profile.asba_consent),
});

// Displayed as 000-111-111-1; stored as the plain 10 digits the server
// validates (digits:10), so this is purely a display/typing convenience.
const nationalIdDisplay = computed({
    get() {
        const digits = (form.national_id_number || '').replace(/\D/g, '');
        const parts = [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6, 9), digits.slice(9, 10)];
        return parts.filter(Boolean).join('-');
    },
    set(value) {
        form.national_id_number = value.replace(/\D/g, '').slice(0, 10);
    },
});

// Age is derived from the date of birth, never typed by the applicant.
const age = computed(() => {
    if (!form.date_of_birth) return '';
    const dob = new Date(form.date_of_birth);
    if (Number.isNaN(dob.getTime())) return '';
    const today = new Date();
    let years = today.getFullYear() - dob.getFullYear();
    const beforeBirthday =
        today.getMonth() < dob.getMonth() ||
        (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate());
    if (beforeBirthday) years -= 1;
    return years >= 0 ? years : '';
});

// Cascading geography dropdowns (province → district → local level).
const provinceIdByName = (name) => geography.provinces.find((province) => province.name_en === name)?.id;
const districtIdByName = (name) => geography.districts.find((district) => district.name_en === name)?.id;
const districtsFor = (provinceName) =>
    geography.districts.filter((district) => district.province_id === provinceIdByName(provinceName));
const localLevelsFor = (districtName) =>
    geography.localLevels.filter((localLevel) => localLevel.district_id === districtIdByName(districtName));

const resetCascade = (address, changed) => {
    if (changed === 'province') {
        address.district = '';
        address.local_level = '';
    } else if (changed === 'district') {
        address.local_level = '';
    }
};

watch(() => form.permanent.province, () => resetCascade(form.permanent, 'province'));
watch(() => form.permanent.district, () => resetCascade(form.permanent, 'district'));
watch(() => form.temporary.province, () => resetCascade(form.temporary, 'province'));
watch(() => form.temporary.district, () => resetCascade(form.temporary, 'district'));

const sourceOptions = options.sourcesOfFunds;

const documentSlots = [
    { input: 'citizenship_front', label: 'Citizenship Front', routeType: 'citizenship-front' },
    { input: 'citizenship_back', label: 'Citizenship Back', routeType: 'citizenship-back' },
    { input: 'national_id_front', label: 'National ID Front', routeType: 'national-id-front' },
    { input: 'national_id_back', label: 'National ID Back', routeType: 'national-id-back' },
    { input: 'pan_doc', label: 'PAN Certificate', routeType: 'pan' },
    { input: 'photo', label: 'Recent Photograph', routeType: 'photo' },
    { input: 'signature', label: 'Signature Image', routeType: 'signature' },
];

// Client-side previews for selected files
const previews = reactive({});
const serverPreviews = reactive({});
const serverPreviewFailed = reactive({});
const modalVisible = ref(false);
const modalSrc = ref('');
const modalTitle = ref('');

const isImageFile = (file) => file && file.type && file.type.startsWith('image/');

const handleFileChange = (inputName, ev) => {
    const file = ev.target.files && ev.target.files[0] ? ev.target.files[0] : null;
    form[inputName] = file;
    // generate preview for images
    if (file && isImageFile(file)) {
        const reader = new FileReader();
        reader.onload = (e) => {
            previews[inputName] = e.target.result;
        };
        reader.readAsDataURL(file);
    } else if (file) {
        // non-image: show filename as preview text
        previews[inputName] = null;
    } else {
        previews[inputName] = null;
    }
};

const toKebab = (s) => String(s).replace(/_/g, '-');
const toUnderscore = (s) => String(s).replace(/-/g, '_');
const tryServerPreviewUrls = (routeType) => [previewLink(toKebab(routeType)), previewLink(toUnderscore(routeType))];

const serverPreviewFor = (routeType) => {
    if (serverPreviews[routeType] === undefined) {
        const [first] = tryServerPreviewUrls(routeType);
        serverPreviews[routeType] = first;
        serverPreviewFailed[routeType] = false;
    }
    return serverPreviews[routeType];
};

const onServerImageError = (routeType) => {
    const [first, second] = tryServerPreviewUrls(routeType);
    const current = serverPreviews[routeType];
    if (current === first && second && second !== first) {
        serverPreviews[routeType] = second;
    } else {
        serverPreviews[routeType] = null;
        serverPreviewFailed[routeType] = true;
    }
};

const modalIsPdf = ref(false);
const isPdfUrl = (u) => typeof u === 'string' && /\.pdf(\?|$)/i.test(u);

const openModal = (src, title) => {
    modalSrc.value = src;
    modalTitle.value = title || '';
    modalIsPdf.value = isPdfUrl(src);
    modalVisible.value = true;
};
const closeModal = () => {
    modalVisible.value = false;
    modalSrc.value = '';
    modalTitle.value = '';
    modalIsPdf.value = false;
};

const documentTypeByInput = {
    citizenship_front: 'citizenship_front',
    citizenship_back: 'citizenship_back',
    national_id_front: 'national_id_front',
    national_id_back: 'national_id_back',
    pan_doc: 'pan',
    photo: 'photo',
    signature: 'signature',
};

const hasDocument = (input) =>
    (profile.documents || []).some((document) => document.document_type === documentTypeByInput[input]);

const previewLink = (routeType) => route(props.documentRouteName, { ...props.documentRouteParams, type: routeType, mode: 'preview' });
const downloadLink = (routeType) => route(props.documentRouteName, { ...props.documentRouteParams, type: routeType, mode: 'download' });

const addExperience = () =>
    form.experiences.push({ organization_name: '', address: '', position: '', years: '' });
const removeExperience = (index) => form.experiences.splice(index, 1);

const fieldClass = (field) => {
    const base = 'mt-1 block w-full rounded-lg border px-3 py-2';
    return form.errors[field]
        ? `${base} border-red-400 focus:border-red-500 focus:ring-red-500`
        : `${base} border-gray-300`;
};

// Once a profile enters the review chain it belongs to the reviewers, not the
// applicant — the server refuses edits too, this just stops them being offered.
const locked = computed(() => !['incomplete', 'returned'].includes(profile.profile_status ?? 'incomplete'));

const statusLabel = computed(() => ({
    submitted: 'awaiting verification',
    verified: 'awaiting review',
    reviewed: 'awaiting final approval',
    approved: 'approved',
}[profile.profile_status] ?? 'under review'));

const emit = defineEmits(['saved']);

const submit = () => {
    if (locked.value) return;
    form.patch(route(props.submitRouteName, props.submitRouteParams), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            // Notify parent that the profile was saved so it can exit edit mode.
            emit('saved');
        },
    });
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <p
            v-if="locked"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
        >
            <span class="font-semibold">This profile is {{ statusLabel }}.</span>
            It is locked while the review team works through it. You will be notified if anything needs changing.
        </p>

        <fieldset :disabled="locked" class="space-y-6 disabled:opacity-70">
        <p
            v-if="page.props.status === 'applicant-profile-updated'"
            class="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700"
        >
            Profile saved successfully.
        </p>

        <!-- 1. Personal Information -->
        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4">
            <h3 class="text-base font-semibold text-gray-800">1. Personal Information</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700">Title</label>
                    <select v-model="form.title" :class="fieldClass('title')">
                        <option value="">—</option>
                        <option v-for="option in options.titles" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Full Name (English)</label>
                    <TextInput :model-value="user.name" type="text" class="mt-1 block w-full bg-gray-100" readonly />
                    <p class="mt-1 text-xs text-gray-500">Tied to your login account.</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Full Name (Nepali) *</label>
                    <TextInput v-model="form.full_name_np" type="text" placeholder="e.g. प्रणय शर्मा" :class="fieldClass('full_name_np')" />
                    <InputError class="mt-1" :message="form.errors.full_name_np" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Date of Birth (B.S.) *</label>
                    <NepaliDatePicker v-model="form.date_of_birth" required />
                    <InputError class="mt-1" :message="form.errors.date_of_birth" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Age</label>
                    <TextInput :model-value="age" type="text" class="mt-1 block w-full bg-gray-100" readonly placeholder="Auto-calculated" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Gender *</label>
                    <select v-model="form.gender" :class="fieldClass('gender')">
                        <option value="" disabled>Select gender</option>
                        <option v-for="option in options.genders" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.gender" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Nationality *</label>
                    <TextInput v-model="form.nationality" type="text" placeholder="e.g. Nepali" :class="fieldClass('nationality')" />
                    <InputError class="mt-1" :message="form.errors.nationality" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Marital Status</label>
                    <select v-model="form.marital_status" :class="fieldClass('marital_status')">
                        <option v-for="option in options.maritalStatuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Father's Name (English) *</label>
                    <TextInput v-model="form.father_name_en" type="text" placeholder="e.g. Krishna Prasad Sharma" :class="fieldClass('father_name_en')" />
                    <InputError class="mt-1" :message="form.errors.father_name_en" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Father's Name (Nepali) *</label>
                    <TextInput v-model="form.father_name_np" type="text" placeholder="e.g. कृष्ण प्रसाद शर्मा" :class="fieldClass('father_name_np')" />
                    <InputError class="mt-1" :message="form.errors.father_name_np" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Mother's Name (English)</label>
                    <TextInput v-model="form.mother_name_en" type="text" placeholder="e.g. Sita Devi Sharma" :class="fieldClass('mother_name_en')" />
                    <InputError class="mt-1" :message="form.errors.mother_name_en" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Mother's Name (Nepali)</label>
                    <TextInput v-model="form.mother_name_np" type="text" placeholder="e.g. सीता देवी शर्मा" :class="fieldClass('mother_name_np')" />
                    <InputError class="mt-1" :message="form.errors.mother_name_np" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Grandfather's Name (English) *</label>
                    <TextInput v-model="form.grandfather_name_en" type="text" placeholder="e.g. Tej Bahadur Sharma" :class="fieldClass('grandfather_name_en')" />
                    <InputError class="mt-1" :message="form.errors.grandfather_name_en" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Grandfather's Name (Nepali) *</label>
                    <TextInput v-model="form.grandfather_name_np" type="text" placeholder="e.g. तेज बहादुर शर्मा" :class="fieldClass('grandfather_name_np')" />
                    <InputError class="mt-1" :message="form.errors.grandfather_name_np" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Spouse's Name (English)</label>
                    <TextInput v-model="form.spouse_name_en" type="text" placeholder="Optional" :class="fieldClass('spouse_name_en')" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Spouse's Name (Nepali)</label>
                    <TextInput v-model="form.spouse_name_np" type="text" placeholder="Optional" :class="fieldClass('spouse_name_np')" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Occupation</label>
                    <TextInput v-model="form.occupation" type="text" placeholder="e.g. Engineer" :class="fieldClass('occupation')" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Education *</label>
                    <select v-model="form.education" :class="fieldClass('education')">
                        <option value="" disabled>Select education level</option>
                        <option v-for="option in options.educationLevels" :key="option.value" :value="option.value">
                            {{ option.label }} ({{ option.label_np }})
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.education" />
                </div>
            </div>
        </section>

        <!-- 2. Contact Information -->
        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4">
            <h3 class="text-base font-semibold text-gray-800">2. Contact Information</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700">Mobile Number *</label>
                    <TextInput v-model="form.mobile" type="text" placeholder="e.g. 98XXXXXXXX" :class="fieldClass('mobile')" />
                    <InputError class="mt-1" :message="form.errors.mobile" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Email</label>
                    <TextInput :model-value="user.email" type="email" class="mt-1 block w-full bg-gray-100" readonly />
                    <p class="mt-1 text-xs text-gray-500">Tied to your login account.</p>
                </div>
            </div>
        </section>

        <!-- 3. Permanent Address -->
        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4">
            <h3 class="text-base font-semibold text-gray-800">3. Permanent Address <span class="font-normal text-gray-500">(as per NID)</span></h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700">Province *</label>
                    <select v-model="form.permanent.province" :class="fieldClass('permanent.province')">
                        <option value="" disabled>Select province</option>
                        <option v-for="province in geography.provinces" :key="province.id" :value="province.name_en">
                            {{ province.name_en }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors['permanent.province']" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">District *</label>
                    <select v-model="form.permanent.district" :disabled="!form.permanent.province" :class="fieldClass('permanent.district')">
                        <option value="" disabled>Select district</option>
                        <option v-for="district in districtsFor(form.permanent.province)" :key="district.id" :value="district.name_en">
                            {{ district.name_en }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors['permanent.district']" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Local Level *</label>
                    <select v-model="form.permanent.local_level" :disabled="!form.permanent.district" :class="fieldClass('permanent.local_level')">
                        <option value="" disabled>Select local level</option>
                        <option v-for="localLevel in localLevelsFor(form.permanent.district)" :key="localLevel.id" :value="localLevel.name_en">
                            {{ localLevel.name_en }} ({{ localLevel.type }})
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors['permanent.local_level']" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Ward No. *</label>
                    <TextInput v-model="form.permanent.ward_no" type="text" placeholder="e.g. 05" :class="fieldClass('permanent.ward_no')" />
                    <InputError class="mt-1" :message="form.errors['permanent.ward_no']" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-gray-700">Tole / Street</label>
                    <TextInput v-model="form.permanent.tole" type="text" placeholder="e.g. Baluwatar" :class="fieldClass('permanent.tole')" />
                </div>
            </div>
        </section>

        <!-- 4. Temporary Address -->
        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4">
            <h3 class="text-base font-semibold text-gray-800">4. Temporary Address</h3>
            <label class="mt-3 flex items-center gap-3 text-sm text-gray-700">
                <input v-model="form.temporary_same_as_permanent" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                <span>Same as Permanent Address</span>
            </label>
            <div v-if="!form.temporary_same_as_permanent" class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700">Province</label>
                    <select v-model="form.temporary.province" :class="fieldClass('temporary.province')">
                        <option value="" disabled>Select province</option>
                        <option v-for="province in geography.provinces" :key="province.id" :value="province.name_en">
                            {{ province.name_en }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors['temporary.province']" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">District</label>
                    <select v-model="form.temporary.district" :disabled="!form.temporary.province" :class="fieldClass('temporary.district')">
                        <option value="" disabled>Select district</option>
                        <option v-for="district in districtsFor(form.temporary.province)" :key="district.id" :value="district.name_en">
                            {{ district.name_en }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors['temporary.district']" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Local Level</label>
                    <select v-model="form.temporary.local_level" :disabled="!form.temporary.district" :class="fieldClass('temporary.local_level')">
                        <option value="" disabled>Select local level</option>
                        <option v-for="localLevel in localLevelsFor(form.temporary.district)" :key="localLevel.id" :value="localLevel.name_en">
                            {{ localLevel.name_en }} ({{ localLevel.type }})
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors['temporary.local_level']" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Ward No.</label>
                    <TextInput v-model="form.temporary.ward_no" type="text" placeholder="e.g. 05" :class="fieldClass('temporary.ward_no')" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-gray-700">Tole / Street</label>
                    <TextInput v-model="form.temporary.tole" type="text" placeholder="e.g. Baluwatar" :class="fieldClass('temporary.tole')" />
                </div>
            </div>
        </section>

        <!-- 5. Identity Information -->
        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4">
            <h3 class="text-base font-semibold text-gray-800">5. Identity Information</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700">Citizenship No. *</label>
                    <TextInput v-model="form.citizenship_number" type="text" :class="fieldClass('citizenship_number')" />
                    <InputError class="mt-1" :message="form.errors.citizenship_number" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Citizenship No. (Nepali) — नागरिकता नं.</label>
                    <TextInput v-model="form.citizenship_number_np" type="text" placeholder="e.g. २७-०१-०६६-०३५२५४" :class="fieldClass('citizenship_number_np')" />
                    <InputError class="mt-1" :message="form.errors.citizenship_number_np" />
                    <p class="mt-1 text-xs text-gray-500">Optional — as printed on the certificate, used on Nepali-language reports.</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Citizenship Issued District</label>
                    <select v-model="form.citizenship_issued_district" :class="fieldClass('citizenship_issued_district')">
                        <option value="">—</option>
                        <option v-for="district in geography.districts" :key="district.id" :value="district.name_en">
                            {{ district.name_en }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Citizenship Issued Date (B.S.)</label>
                    <NepaliDatePicker v-model="form.citizenship_issued_date" />
                    <InputError class="mt-1" :message="form.errors.citizenship_issued_date" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">National ID No. *</label>
                    <TextInput v-model="nationalIdDisplay" type="text" placeholder="e.g. 000-111-111-1" maxlength="13" :class="fieldClass('national_id_number')" />
                    <InputError class="mt-1" :message="form.errors.national_id_number" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">PAN</label>
                    <TextInput v-model="form.pan_number" type="text" placeholder="Optional" :class="fieldClass('pan_number')" />
                    <InputError class="mt-1" :message="form.errors.pan_number" />
                </div>
            </div>
        </section>

        <!-- 6. Documents -->
        <section class="rounded-xl border border-amber-100 bg-amber-50 p-4">
            <h3 class="text-base font-semibold text-amber-800">6. Documents</h3>
            <p class="mt-1 text-sm text-amber-700">All documents are required before your profile can be submitted for review.</p>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div v-for="slot in documentSlots" :key="slot.input">
                    <label class="text-sm font-medium text-gray-700">{{ slot.label }} *</label>
                    <input
                        type="file"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2"
                        @change="(e) => handleFileChange(slot.input, e)"
                    />

                    <!-- Client-side preview for newly selected file -->
                    <div v-if="previews[slot.input]" class="mt-2">
                        <img :src="previews[slot.input]" alt="preview" class="h-24 w-24 object-cover rounded border cursor-pointer" @click="openModal(previews[slot.input], slot.label)" />
                    </div>
                    <div v-else-if="form[slot.input]" class="mt-2 text-sm text-gray-600"> 
                        Selected: {{ form[slot.input].name }}
                    </div>

                    <!-- Server-side existing upload preview (attempt image, fallback to placeholder) -->
                    <div v-if="hasDocument(slot.input)" class="mt-2">
                        <div v-if="serverPreviewFor(slot.routeType) && !serverPreviewFailed[slot.routeType]" class="inline-block">
                            <img :src="serverPreviewFor(slot.routeType)" alt="server-preview" class="h-24 w-24 object-cover rounded border cursor-pointer" @error="onServerImageError(slot.routeType)" @click.prevent="openModal(serverPreviewFor(slot.routeType), slot.label)" />
                        </div>
                        <div v-else class="h-24 w-24 flex items-center justify-center bg-gray-100 rounded border text-sm text-gray-600">No preview</div>

                        <div class="mt-2 flex gap-3 text-xs">
                            <span class="font-semibold text-emerald-700">Uploaded</span>
                            <a :href="previewLink(slot.routeType)" target="_blank" class="font-semibold text-blue-700 hover:text-blue-900">Preview</a>
                            <a :href="downloadLink(slot.routeType)" target="_blank" class="font-semibold text-emerald-700 hover:text-emerald-900">Download</a>
                        </div>
                    </div>
                    <InputError class="mt-1" :message="form.errors[slot.input]" />
                </div>
            </div>
        </section>

        <!-- 7. Source of Investment -->
        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4">
            <h3 class="text-base font-semibold text-gray-800">7. Source of Investment</h3>
            <p class="mt-1 text-sm text-gray-500">Optional — select any that apply.</p>
            <div class="mt-3 grid gap-2 md:grid-cols-2">
                <label v-for="option in sourceOptions" :key="option.value" class="flex items-center gap-3 text-sm text-gray-700">
                    <input v-model="form.sources" type="checkbox" :value="option.value" class="rounded border-gray-300 text-blue-600" />
                    <span>{{ option.label }}</span>
                </label>
            </div>
            <InputError class="mt-1" :message="form.errors.sources" />

            <div v-if="form.sources.includes('other')" class="mt-3">
                <label class="text-sm font-medium text-gray-700">Please specify</label>
                <TextInput v-model="form.source_other_detail" type="text" placeholder="e.g. Insurance claim" :class="fieldClass('source_other_detail')" />
                <InputError class="mt-1" :message="form.errors.source_other_detail" />
            </div>
        </section>

        <!-- 8. Nominee / Share Beneficiary -->
        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4">
            <h3 class="text-base font-semibold text-gray-800">8. Nominee / Share Beneficiary</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700">Full Name</label>
                    <TextInput v-model="form.nominee.full_name" type="text" placeholder="e.g. Sita Sharma" :class="fieldClass('nominee.full_name')" />
                    <InputError class="mt-1" :message="form.errors['nominee.full_name']" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Relationship</label>
                    <TextInput v-model="form.nominee.relationship" type="text" placeholder="e.g. Daughter" :class="fieldClass('nominee.relationship')" />
                    <InputError class="mt-1" :message="form.errors['nominee.relationship']" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Mobile Number</label>
                    <TextInput v-model="form.nominee.mobile" type="text" placeholder="e.g. 98XXXXXXXX" :class="fieldClass('nominee.mobile')" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Address</label>
                    <TextInput v-model="form.nominee.address" type="text" placeholder="Optional" :class="fieldClass('nominee.address')" />
                </div>
            </div>
        </section>

        <!-- 9. Professional Experience -->
        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4">
            <h3 class="text-base font-semibold text-gray-800">9. Professional Experience</h3>
            <p v-if="!form.experiences.length" class="mt-2 text-sm text-gray-500">No experience added yet. This section is optional.</p>
            <div v-for="(experience, index) in form.experiences" :key="index" class="mt-3 rounded-lg border border-gray-200 bg-white p-3">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Organization *</label>
                        <TextInput v-model="experience.organization_name" type="text" :class="fieldClass(`experiences.${index}.organization_name`)" />
                        <InputError class="mt-1" :message="form.errors[`experiences.${index}.organization_name`]" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Address</label>
                        <TextInput v-model="experience.address" type="text" :class="fieldClass(`experiences.${index}.address`)" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Position</label>
                        <TextInput v-model="experience.position" type="text" :class="fieldClass(`experiences.${index}.position`)" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Years</label>
                        <TextInput v-model="experience.years" type="number" min="0" max="99" step="0.5" :class="fieldClass(`experiences.${index}.years`)" />
                        <InputError class="mt-1" :message="form.errors[`experiences.${index}.years`]" />
                    </div>
                </div>
                <button type="button" class="mt-3 text-sm font-medium text-red-600 hover:text-red-800" @click="removeExperience(index)">
                    Remove
                </button>
            </div>
            <button
                type="button"
                class="mt-3 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                @click="addExperience"
            >
                + Add Another Experience
            </button>
        </section>

        <!-- MeroShare / C-ASBA -->
        <section class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
            <h3 class="text-base font-semibold text-emerald-800">MeroShare / C-ASBA Details</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700">BOID (16 digits) *</label>
                    <TextInput v-model="form.boid" type="text" placeholder="e.g. 1301234567890123" :class="fieldClass('boid')" />
                    <InputError class="mt-1" :message="form.errors.boid" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Bank Name *</label>
                    <TextInput v-model="form.bank_name" type="text" placeholder="e.g. Nepal Investment Mega Bank" :class="fieldClass('bank_name')" />
                    <InputError class="mt-1" :message="form.errors.bank_name" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Bank Branch *</label>
                    <TextInput v-model="form.bank_branch" type="text" placeholder="e.g. New Road" :class="fieldClass('bank_branch')" />
                    <InputError class="mt-1" :message="form.errors.bank_branch" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Bank Account Number *</label>
                    <TextInput v-model="form.bank_account_number" type="text" :class="fieldClass('bank_account_number')" />
                    <InputError class="mt-1" :message="form.errors.bank_account_number" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Account Holder Name *</label>
                    <TextInput v-model="form.account_holder_name" type="text" placeholder="As per bank account" :class="fieldClass('account_holder_name')" />
                    <InputError class="mt-1" :message="form.errors.account_holder_name" />
                </div>
            </div>
            <label class="mt-4 flex items-start gap-3 text-sm text-gray-700">
                <input v-model="form.asba_consent" type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600" />
                <span>I authorize ASBA-style amount blocking and reconciliation using the details provided above. *</span>
            </label>
            <InputError class="mt-1" :message="form.errors.asba_consent" />
        </section>

        <!-- 10. Declaration -->
        <section class="rounded-xl border border-blue-100 bg-blue-50 p-4">
            <h3 class="text-base font-semibold text-blue-800">10. Declaration</h3>
            <div class="mt-3">
                <label class="flex items-start gap-3 text-sm text-gray-700">
                    <input v-model="form.declarations.accepted" type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600" />
                    <span>
                        I confirm that the information provided is true, that the source of funds is legal
                        and I am not blacklisted, and I agree to the terms and conditions, accepting the
                        investment risk myself. *
                    </span>
                </label>
            </div>
            <InputError class="mt-1" :message="form.errors['declarations.accepted']" />
        </section>

        </fieldset>

        <div v-if="!locked" class="flex items-center gap-4">
            <PrimaryButton :disabled="form.processing">Save Profile</PrimaryButton>
            <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Profile saved.</p>
        </div>

        <!-- Image modal / lightbox -->
        <div v-if="modalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60">
            <div class="relative max-w-3xl w-full mx-4">
                <button class="absolute top-2 end-2 text-white bg-black bg-opacity-30 rounded-full p-2" @click="closeModal">✕</button>
                <div class="bg-white rounded shadow p-4">
                    <h4 class="text-sm font-semibold mb-2">{{ modalTitle }}</h4>
                    <template v-if="modalIsPdf">
                        <iframe :src="modalSrc" class="w-full h-[70vh]" frameborder="0"></iframe>
                    </template>
                    <template v-else>
                        <img v-if="modalSrc" :src="modalSrc" alt="preview" class="max-h-[70vh] w-full object-contain" />
                    </template>
                </div>
            </div>
        </div>
    </form>
</template>
