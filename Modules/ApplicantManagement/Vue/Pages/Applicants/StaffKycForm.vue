<script setup>
import UpdateApplicantProfileForm from '../../../../UserManagement/Vue/Pages/Profile/Partials/UpdateApplicantProfileForm.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    applicant: { type: Object, required: true },
    profile: { type: Object, default: null },
    completionPercent: { type: Number, default: 0 },
});

const canSubmit = computed(() => ['incomplete', 'returned'].includes(props.profile?.profile_status ?? 'incomplete'));

const submitForm = useForm({ remarks: '' });

const submitAndVerify = () => {
    submitForm.post(route('applicants.add.kyc.submit', { applicant: props.applicant.id }));
};
</script>

<template>
    <Head :title="`KYC — ${applicant.name}`" />

    <PanelLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold text-gray-900">{{ applicant.name }}</h2>
                        <p class="mt-1 text-sm text-gray-700">{{ applicant.email }}</p>
                        <div class="mt-4 max-w-sm">
                            <div class="flex items-center justify-between text-sm font-medium text-gray-700">
                                <span>KYC Completion</span>
                                <span>{{ completionPercent }}%</span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-brand transition-all" :style="{ width: `${completionPercent}%` }" />
                            </div>
                        </div>
                    </div>
                    <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 ring-1 ring-gray-200">
                        {{ profile?.profile_status ?? 'incomplete' }}
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-6">
                <UpdateApplicantProfileForm
                    :applicant-user="applicant"
                    submit-route-name="applicants.add.kyc.update"
                    :submit-route-params="{ applicant: applicant.id }"
                    document-route-name="applicants.add.kyc.documents.show"
                    :document-route-params="{ applicant: applicant.id }"
                />
            </div>

            <div v-if="canSubmit" class="bg-white p-4 shadow sm:rounded-lg sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900">Forward to review</h3>
                <p class="mt-1 text-sm text-gray-700">
                    Once everything above is saved and complete, forward this KYC to the Reviewer queue. Your own
                    verification of it is recorded automatically — it will not sit in your own Verifications queue.
                </p>

                <label class="mt-4 block text-sm font-medium text-gray-700">Verification remarks</label>
                <textarea
                    v-model="submitForm.remarks"
                    rows="2"
                    placeholder="e.g. Entered from paper KYC form submitted at the branch counter, documents checked against originals."
                    class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                ></textarea>
                <p v-if="submitForm.errors.remarks" class="mt-1 text-sm text-red-600">{{ submitForm.errors.remarks }}</p>
                <p v-if="submitForm.errors.profile" class="mt-1 text-sm text-red-600">{{ submitForm.errors.profile }}</p>

                <div class="mt-4 flex justify-end">
                    <button
                        type="button"
                        class="rounded bg-brand px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="submitForm.processing || !submitForm.remarks.trim()"
                        @click="submitAndVerify"
                    >
                        Submit &amp; Forward to Review
                    </button>
                </div>
            </div>
        </div>
    </PanelLayout>
</template>
