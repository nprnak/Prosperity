<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const resendForm = useForm({});
const otpForm = useForm({ code: '' });

const resend = () => {
    resendForm.post(route('verification.send'));
};

const submitOtp = () => {
    otpForm.post(route('verification.otp'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            Thanks for signing up! We emailed you a 6-digit verification code
            and a verification link. Enter the code below, or click the link in
            the email. If you didn't receive it, we will gladly send another.
        </div>

        <div
            class="mb-4 text-sm font-medium text-green-600 dark:text-green-400"
            v-if="verificationLinkSent"
        >
            A new verification code has been sent to the email address you
            provided during registration.
        </div>

        <form @submit.prevent="submitOtp" class="mb-6">
            <label for="otp-code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Verification Code
            </label>
            <div class="flex gap-2">
                <input
                    id="otp-code"
                    v-model="otpForm.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    placeholder="123456"
                    class="w-40 rounded-md border-gray-300 text-center text-lg tracking-[0.4em] shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                />
                <PrimaryButton
                    :class="{ 'opacity-25': otpForm.processing }"
                    :disabled="otpForm.processing || otpForm.code.length !== 6"
                >
                    Verify
                </PrimaryButton>
            </div>
            <InputError class="mt-2" :message="otpForm.errors.code" />
        </form>

        <form @submit.prevent="resend">
            <div class="mt-4 flex items-center justify-between">
                <button
                    type="submit"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                    :disabled="resendForm.processing"
                >
                    Resend Verification Email
                </button>

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
