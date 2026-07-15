<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Settings" />

    <PanelLayout>
        <div class="space-y-6">
            <div class="rounded-xl bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-700 p-5 text-white shadow">
                <h2 class="text-xl font-semibold">Settings</h2>
                <p class="mt-1 text-sm text-blue-100">
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
        </div>
    </PanelLayout>
</template>
