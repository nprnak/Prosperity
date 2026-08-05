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
            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.94 6.94a9.97 9.97 0 0114.12 0 9.97 9.97 0 01-14.12 0z" opacity=".2"/><path d="M10 4c3.866 0 7.09 2.69 8.483 6.363-.265.74-.63 1.44-1.073 2.07C15.09 14.31 11.866 17 8 17c-3.866 0-7.09-2.69-8.483-6.363C.782 9.897 1.147 9.197 1.412 8.456 2.91 5.21 6.045 4 10 4z"/><path d="M10 8a2 2 0 100 4 2 2 0 000-4z"/></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 010 1.414l-1 1A9.97 9.97 0 001.417 9.636C2.91 12.79 6.045 14 10 14c.92 0 1.807-.12 2.647-.344l1.873 1.873a1 1 0 001.414-1.414l-12-12a1 1 0 00-1.414 0z" clip-rule="evenodd"/></svg>
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
