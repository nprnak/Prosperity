<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <PanelLayout>
        <div class="rounded-2xl bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-700 p-6 text-white shadow-lg">
            <h2 class="text-2xl font-semibold">Welcome Back</h2>
            <p class="mt-2 text-sm text-blue-100">Use the panel to complete your profile and manage your share application.</p>
        </div>

        <div class="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg ring-1 ring-gray-100">
            <div class="p-6 text-gray-900">
                Your account is ready. Start from Share Application in the left panel.
            </div>
        </div>
    </PanelLayout>
</template>
