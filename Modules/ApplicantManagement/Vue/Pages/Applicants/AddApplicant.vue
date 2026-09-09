<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', email: '' });

const submit = () => {
    form.post(route('applicants.add.store'));
};
</script>

<template>
    <Head title="Add Applicant" />

    <PanelLayout>
        <div class="mx-auto max-w-2xl space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold text-gray-900">Paper-based KYC Entry</h2>
                <p class="mt-1 text-sm text-gray-700">
                    For a walk-in applicant who filled a physical form instead of registering online.
                    First create their account, then enter their KYC details on the next page. Once you
                    submit it for review, it goes straight to the Reviewer queue — your own verification
                    is recorded automatically.
                </p>
            </div>

            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-6">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="name" value="Full name" :required="true" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="As on citizenship / ID"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email address" :required="true" />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full"
                            placeholder="applicant@example.com"
                            required
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            A password-setup link is emailed here so the applicant can log in themselves later.
                        </p>
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">
                            Create Account &amp; Continue to KYC
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </PanelLayout>
</template>
