<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import UpdateApplicantProfileForm from './Partials/UpdateApplicantProfileForm.vue';
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
    <Head title="Profile" />

    <PanelLayout>
        <div class="space-y-6">
            <div class="rounded-xl bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-700 p-5 text-white shadow">
                <h2 class="text-xl font-semibold">Profile</h2>
                <p class="mt-1 text-sm text-blue-100">
                    Complete required personal, education, address, and document information for share application.
                </p>
            </div>

            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <UpdateApplicantProfileForm />
            </div>
        </div>
    </PanelLayout>
</template>
