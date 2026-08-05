<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

defineProps({ status: { type: String } });

const form = useForm({ email: '' });
const clientError = ref('');

watch(() => form.email, (val) => {
  clientError.value = val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) ? 'Enter a valid email address' : '';
});

const submit = () => {
  if (clientError.value) return;
  form.post(route('password.email'));
};
</script>

<template>
  <GuestLayout>
    <Head title="Forgot Password" />

    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
      Forgot your password? No problem. Enter your email and a reset link will be sent.
    </div>

    <div v-if="status" class="mb-4 text-sm font-medium text-green-600 dark:text-green-400">{{ status }}</div>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <InputLabel for="email" value="Email" :required="true" />
        <TextInput id="email" type="email" placeholder="you@company.com" class="mt-1 block w-full" v-model="form.email" required autofocus autocomplete="username" />
        <InputError class="mt-2" :message="form.errors.email || clientError" />
      </div>

      <div class="flex items-center justify-end">
        <PrimaryButton :disabled="form.processing || clientError" :class="{ 'opacity-25': form.processing }">Email Password Reset Link</PrimaryButton>
      </div>
    </form>
  </GuestLayout>
</template>
