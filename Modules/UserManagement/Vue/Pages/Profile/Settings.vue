<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdateSignatureDetailsForm from './Partials/UpdateSignatureDetailsForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    signatureDetails: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <Head title="Settings" />

    <PanelLayout>
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold text-gray-900">Settings</h2>
                <p class="mt-1 text-sm text-gray-700">
                    Manage your login credentials here. Update username, email, and password for account login.
                </p>
            </div>

            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <UpdateSignatureDetailsForm :signature-details="signatureDetails" class="max-w-xl" />
            </div>
        </div>
    </PanelLayout>
</template>
