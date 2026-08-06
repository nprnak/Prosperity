<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

defineProps({
  canResetPassword: { type: Boolean },
  status: { type: String },
});

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);
const clientErrors = ref({});

const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

watch(() => form.email, (val) => {
  clientErrors.value.email = val && !emailRx.test(val) ? 'Enter a valid email address' : '';
});
watch(() => form.password, (val) => {
  clientErrors.value.password = val && val.length < 6 ? 'Password must be at least 6 characters' : '';
});

const hasErrors = computed(() => Object.keys(form.errors).length || Object.values(clientErrors.value).some(Boolean));

const submit = () => {
  if (clientErrors.value.email || clientErrors.value.password) return;
  if (form.email) localStorage.setItem('last_auth_email', form.email);
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Log in" />

    <div class="mb-4">
      <h1 class="text-lg font-semibold">Sign in to your account</h1>
      <p class="text-sm text-gray-500">Enter your credentials to continue. If you don't have an account, register below.</p>
    </div>

    <div v-if="status" class="mb-4 text-sm font-medium text-green-600">{{ status }}</div>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <InputLabel for="email" value="Email" :required="true" />
        <TextInput id="email" type="email" placeholder="you@company.com" class="mt-1 block w-full" v-model="form.email" required autofocus autocomplete="username" />
        <InputError class="mt-2" :message="form.errors.email || clientErrors.email" />
      </div>

      <div>
        <InputLabel for="password" value="Password" :required="true" />
        <div class="relative mt-1">
          <TextInput :type="showPassword ? 'text' : 'password'" id="password" placeholder="Enter your password" class="block w-full pr-10" v-model="form.password" required autocomplete="current-password" />
          <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 end-0 px-3 flex items-center text-gray-500" :aria-pressed="showPassword">
            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0112 20c-7 0-11-8-11-8a21.85 21.85 0 015-7"/><path d="M1 1l22 22"/></svg>
          </button>
        </div>
        <InputError class="mt-2" :message="form.errors.password || clientErrors.password" />
      </div>

      <div class="flex items-center justify-between">
        <label class="flex items-center">
          <Checkbox name="remember" v-model:checked="form.remember" />
          <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Remember me</span>
        </label>

        <div class="text-sm">
          <Link v-if="canResetPassword" :href="route('password.request')" class="text-[#031226] hover:underline">Forgot password?</Link>
        </div>
      </div>

      <div>
        <PrimaryButton :disabled="form.processing || hasErrors" :class="{ 'opacity-25': form.processing }">Log in</PrimaryButton>
      </div>

      <div class="pt-4 border-t border-gray-100 text-center">
        <p class="text-sm">Don't have an account? <Link :href="route('register')" class="text-[#031226] hover:underline">Sign up</Link></p>
      </div>
    </form>
  </GuestLayout>
</template>
