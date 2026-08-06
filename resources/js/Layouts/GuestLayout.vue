<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ToastHub from '@/Components/ToastHub.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden grid grid-cols-1 lg:grid-cols-2">
            <div class="hidden lg:flex flex-col items-center justify-center p-8 bg-[#031226] text-white">
                <ApplicationLogo class="h-20 w-auto mb-6" />
                <h2 class="text-2xl font-bold">Welcome to Prosperity</h2>
                <p class="mt-2 text-sm text-blue-100 text-center">Secure access to the Prosperity MIS. Use your company credentials to access dashboards and management tools.</p>
            </div>

            <div class="p-8">
                <div class="flex items-center justify-center lg:justify-start mb-6">
                    <Link href="/">
                    </Link>
                </div>

                <div class="w-full">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
