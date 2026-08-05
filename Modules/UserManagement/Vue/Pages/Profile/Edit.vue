<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import UpdateApplicantProfileForm from './Partials/UpdateApplicantProfileForm.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, reactive } from 'vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    profile: {
        type: Object,
        default: null,
    },
    completionPercent: {
        type: Number,
        default: 0,
    },
    reviewRemarks: {
        type: String,
        default: null,
    },
});

const profileStatus = computed(() => props.profile?.profile_status ?? 'incomplete');
const canSubmitForReview = computed(() => ['incomplete', 'rejected', 'returned'].includes(profileStatus.value));
// The three sign-off stages all read as 'in review' to the applicant.
const inReview = computed(() => ['submitted', 'verified', 'reviewed'].includes(profileStatus.value));

const submitForm = useForm({});
const submitForReview = () => submitForm.post(route('profile.submit'), { preserveScroll: true });

const profileError = computed(() => usePage().props.errors?.profile);

const activeTab = ref('overview');
const editMode = ref(false);

const locked = computed(() => inReview.value || profileStatus.value === 'approved');

// Some backends expect kebab-case in the route; normalize underscores to hyphens so preview links work
const toKebab = (s) => String(s).replace(/_/g, '-');
const toUnderscore = (s) => String(s).replace(/-/g, '_');
const previewLink = (routeType) => route('profile.documents.show', { type: routeType, mode: 'preview' });
const downloadLink = (routeType) => route('profile.documents.show', { type: routeType, mode: 'download' });

// Server-side preview attempt with kebab/underscore fallback
const serverPreviews = reactive({});
const serverPreviewFailed = reactive({});

const tryPreviewUrls = (routeType) => [previewLink(toKebab(routeType)), previewLink(toUnderscore(routeType))];

const serverPreviewFor = (routeType) => {
    if (serverPreviews[routeType] === undefined) {
        const [first] = tryPreviewUrls(routeType);
        serverPreviews[routeType] = first;
        serverPreviewFailed[routeType] = false;
    }
    return serverPreviews[routeType];
};

const onServerImageError = (routeType) => {
    const [first, second] = tryPreviewUrls(routeType);
    const current = serverPreviews[routeType];
    if (current === first && second && second !== first) {
        serverPreviews[routeType] = second;
    } else {
        serverPreviews[routeType] = null;
        serverPreviewFailed[routeType] = true;
    }
};

const modalVisible = ref(false);
const modalSrc = ref('');
const modalTitle = ref('');
const modalIsPdf = ref(false);
const isPdfUrl = (u) => typeof u === 'string' && /\.pdf(\?|$)/i.test(u);
const openModal = (src, title) => { modalSrc.value = src; modalTitle.value = title || ''; modalIsPdf.value = isPdfUrl(src); modalVisible.value = true; };
const closeModal = () => { modalVisible.value = false; modalSrc.value = ''; modalTitle.value = ''; modalIsPdf.value = false; };

const startEditing = () => { editMode.value = true; };
const stopEditing = () => { editMode.value = false; };

</script>

<template>
    <Head title="Profile" />

    <PanelLayout>
        <div class="space-y-6">
            <!-- Top summary card -->
            <div class="rounded-lg border p-4 flex items-start justify-between gap-4 bg-white">
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-slate-900">Complete Your Profile</h2>
                    <p class="mt-1 text-sm text-slate-600">Please provide the required information to apply for shares.</p>
                    <div class="mt-4 max-w-sm">
                        <div class="flex items-center justify-between text-sm font-medium text-slate-600">
                            <span>Profile Completion</span>
                            <span>{{ completionPercent }}%</span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-[#031226] transition-all" :style="{ width: `${completionPercent}%` }" />
                        </div>
                    </div>
                </div>

                <div class="ms-4 flex flex-col gap-2">
                    <button v-if="canSubmitForReview" class="rounded border border-[#031226] text-[#031226] px-3 py-2 text-sm font-semibold" :disabled="submitForm.processing" @click="submitForReview">Submit for review</button>
                    <div class="text-sm text-slate-600">Status: <span class="font-semibold uppercase">{{ profileStatus }}</span></div>
                </div>
            </div>

            <!-- Merged profile card (single view) -->
            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Profile</h3>
                        <p class="text-sm text-slate-600 mt-1">All saved profile data. You can edit until you submit for review.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button v-if="canSubmitForReview" class="rounded border border-[#031226] text-[#031226] px-3 py-2 text-sm font-semibold" :disabled="submitForm.processing" @click="submitForReview">Submit for review</button>
                        <button v-if="!editMode && !locked" @click="startEditing" class="rounded bg-[#031226] text-white px-3 py-2 text-sm">Edit profile</button>
                        <button v-else-if="editMode" @click="stopEditing" class="rounded border border-gray-200 px-3 py-2 text-sm">Cancel</button>
                    </div>
                </div>

                <div class="mt-6">
                    <div v-if="!editMode && props.profile" class="space-y-4">
                        <!-- Personal & contact -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-xs text-slate-500">Full name (English)</p>
                                <p class="font-medium text-slate-800">{{ props.profile.full_name_en || props.auth?.user?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Full name (Nepali)</p>
                                <p class="font-medium text-slate-800">{{ props.profile.full_name_np || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Date of birth</p>
                                <p class="font-medium text-slate-800">{{ props.profile.date_of_birth || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Age</p>
                                <p class="font-medium text-slate-800">{{ props.profile.age || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Gender</p>
                                <p class="font-medium text-slate-800">{{ props.profile.gender || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Mobile</p>
                                <p class="font-medium text-slate-800">{{ props.profile.mobile || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Email</p>
                                <p class="font-medium text-slate-800">{{ props.auth?.user?.email || '-' }}</p>
                            </div>
                        </div>

                        <!-- Addresses -->
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-xs text-slate-500">Permanent Address</p>
                                <p class="font-medium text-slate-800">{{ [props.profile.permanent?.province, props.profile.permanent?.district, props.profile.permanent?.local_level, props.profile.permanent?.tole, props.profile.permanent?.ward_no].filter(Boolean).join(', ') || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Temporary Address</p>
                                <p class="font-medium text-slate-800">{{ props.profile.temporary_same_as_permanent ? 'Same as permanent' : [props.profile.temporary?.province, props.profile.temporary?.district, props.profile.temporary?.local_level, props.profile.temporary?.tole, props.profile.temporary?.ward_no].filter(Boolean).join(', ') || '-' }}</p>
                            </div>
                        </div>

                        <!-- Identity, nominee & bank -->
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-xs text-slate-500">Citizenship / NID</p>
                                <p class="font-medium text-slate-800">{{ props.profile.citizenship_number || props.profile.national_id_number || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">PAN / BOID</p>
                                <p class="font-medium text-slate-800">{{ props.profile.pan_number || props.profile.boid || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Bank</p>
                                <p class="font-medium text-slate-800">{{ props.profile.bank_name || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Bank account</p>
                                <p class="font-medium text-slate-800">{{ props.profile.bank_account_number || '-' }}</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h4 class="text-sm font-semibold text-slate-800">Nominee</h4>
                            <div class="mt-2 grid gap-4 md:grid-cols-2">
                                <div>
                                    <p class="text-xs text-slate-500">Name</p>
                                    <p class="font-medium text-slate-800">{{ props.profile.nominee?.full_name || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500">Relationship</p>
                                    <p class="font-medium text-slate-800">{{ props.profile.nominee?.relationship || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500">Mobile</p>
                                    <p class="font-medium text-slate-800">{{ props.profile.nominee?.mobile || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500">Address</p>
                                    <p class="font-medium text-slate-800">{{ props.profile.nominee?.address || '-' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Uploaded documents with thumbnails -->
                        <div class="mt-4">
                            <h4 class="text-sm font-semibold text-slate-800">Uploaded documents</h4>
                            <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div v-for="doc in props.profile?.documents || []" :key="doc.id" class="border rounded p-2 bg-white flex flex-col items-center gap-2">
                                    <div>
                                        <template v-if="serverPreviewFor(doc.document_type) && !serverPreviewFailed[doc.document_type]">
                                            <img :src="serverPreviewFor(doc.document_type)" alt="doc" class="h-24 w-24 object-cover rounded cursor-pointer" @error="onServerImageError(doc.document_type)" @click="openModal(serverPreviewFor(doc.document_type), doc.original_name || doc.document_type)" />
                                        </template>
                                        <template v-else>
                                            <div class="h-24 w-24 flex items-center justify-center bg-gray-100 rounded text-sm text-gray-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h6l2 2h4v8a2 2 0 01-2 2H7a2 2 0 01-2-2V7z" />
                                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7V5a2 2 0 012-2h2" />
                                                </svg>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="text-sm text-center">
                                        <div class="font-medium text-slate-800">{{ doc.original_name || doc.document_type }}</div>
                                        <div class="text-xs text-slate-500">{{ doc.document_type }}</div>
                                        <div class="mt-1 flex gap-2 justify-center">
                                            <a :href="previewLink(doc.document_type)" target="_blank" class="text-xs text-[#031226] hover:underline">Preview</a>
                                            <a :href="downloadLink(doc.document_type)" class="text-xs text-gray-600 hover:underline">Download</a>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="!(props.profile?.documents || []).length" class="text-sm text-gray-500">No documents uploaded.</div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <UpdateApplicantProfileForm @saved="stopEditing" />
                    </div>
                </div>
            </div>

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
    </PanelLayout>
</template>
