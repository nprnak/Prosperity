<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import ApplicationsTable from '@/Components/ApplicationsTable.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    applications: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Dashboard" />

    <PanelLayout>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold text-gray-900">Welcome Back</h2>
            <p class="mt-1 text-sm text-gray-700">Use the panel to complete your profile and manage your share application.</p>
        </div>

        <div class="mt-6 bg-white p-6 shadow-sm sm:rounded-2xl ring-1 ring-gray-100">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">My Applications</h3>
                <Link :href="route('applications.wizard')" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
                    Apply for shares &rarr;
                </Link>
            </div>
            <ApplicationsTable :applications="applications" />
        </div>
    </PanelLayout>
</template>
