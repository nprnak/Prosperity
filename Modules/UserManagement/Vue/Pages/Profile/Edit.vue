<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import UpdateApplicantProfileForm from './Partials/UpdateApplicantProfileForm.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
});

const profileStatus = computed(() => props.profile?.profile_status ?? 'incomplete');
const canSubmitForReview = computed(() => ['incomplete', 'rejected'].includes(profileStatus.value));

const submitForm = useForm({});
const submitForReview = () => submitForm.post(route('profile.submit'), { preserveScroll: true });

const flashSuccess = computed(() => usePage().props.flash?.success);
const profileError = computed(() => usePage().props.errors?.profile);
</script>

<template>
    <Head title="Profile" />

    <PanelLayout>
        <div class="space-y-6">
            <div class="rounded-xl bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-700 p-5 text-white shadow">
                <h2 class="text-2xl font-semibold">Complete Your Profile</h2>
                <p class="mt-1 text-sm text-blue-100">
                    Please provide the following information to apply for shares.
                </p>
                <div class="mt-4">
                    <div class="flex items-center justify-between text-sm font-medium text-blue-100">
                        <span>Profile Completion</span>
                        <span>{{ completionPercent }}%</span>
                    </div>
                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/25">
                        <div class="h-full rounded-full bg-white transition-all" :style="{ width: `${completionPercent}%` }" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border p-4 flex items-start justify-between gap-4"
                :class="{
                    'border-gray-300 bg-gray-50': profileStatus === 'incomplete',
                    'border-amber-300 bg-amber-50': profileStatus === 'submitted',
                    'border-green-300 bg-green-50': profileStatus === 'approved',
                    'border-red-300 bg-red-50': profileStatus === 'rejected',
                }">
                <div class="text-sm">
                    <p class="font-semibold">
                        KYC review status:
                        <span class="uppercase">{{ profileStatus }}</span>
                    </p>
                    <p v-if="profileStatus === 'incomplete'" class="mt-1 text-gray-600">
                        Complete all required fields below, then submit your profile for review. You can apply for shares once it is approved.
                    </p>
                    <p v-else-if="profileStatus === 'submitted'" class="mt-1 text-amber-700">
                        Your profile is being reviewed. You will be notified by email once a decision is made.
                    </p>
                    <p v-else-if="profileStatus === 'approved'" class="mt-1 text-green-700">
                        Your profile is approved. You can now apply for shares.
                    </p>
                    <p v-else-if="profileStatus === 'rejected'" class="mt-1 text-red-700">
                        Your profile needs changes: {{ profile?.profile_rejection_reason || 'see the email we sent you' }}.
                        Update it below and submit again.
                    </p>
                    <p v-if="profileError" class="mt-1 font-medium text-red-700">{{ profileError }}</p>
                    <p v-if="flashSuccess" class="mt-1 font-medium text-green-700">{{ flashSuccess }}</p>
                </div>
                <button
                    v-if="canSubmitForReview"
                    class="shrink-0 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    :disabled="submitForm.processing"
                    @click="submitForReview"
                >
                    Submit for review
                </button>
            </div>

            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <UpdateApplicantProfileForm />
            </div>
        </div>
    </PanelLayout>
</template>
