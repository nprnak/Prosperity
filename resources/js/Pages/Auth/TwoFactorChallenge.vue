<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ code: '' });

const submit = () => {
    form.post(route('two-factor.verify'), {
        onError: () => form.reset('code'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Two-Factor Authentication" />

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            This account requires a second authentication step. Enter the
            verification code sent to your email address.
        </div>

        <form @submit.prevent="submit">
            <label for="tfa-code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Authentication Code
            </label>
            <input
                id="tfa-code"
                v-model="form.code"
                inputmode="numeric"
                autocomplete="one-time-code"
                autofocus
                class="w-40 rounded-md border-gray-300 text-center text-lg tracking-[0.4em] shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            />
            <InputError class="mt-2" :message="form.errors.code" />

            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing || !form.code"
                >
                    Verify
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                    >Log Out</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
