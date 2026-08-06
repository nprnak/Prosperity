<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '', accept_terms: false });
const passwordStrength = ref(0);
const showPassword = ref(false);

watch(() => form.password, (val) => {
  let score = 0;
  if (!val) score = 0;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  passwordStrength.value = score;
});

const passwordMatch = computed(() => form.password && form.password === form.password_confirmation);

const strengthLabel = computed(() => {
  const s = passwordStrength.value;
  if (s === 0) return 'Too short';
  if (s === 1) return 'Weak';
  if (s === 2) return 'Fair';
  if (s === 3) return 'Strong';
  return 'Very strong';
});

const canSubmit = computed(() => form.name && form.email && form.password && passwordMatch.value && form.accept_terms && passwordStrength.value >= 2);

const submit = () => {
  form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Register" />

    <div class="mb-4">
      <h1 class="text-lg font-semibold">Create your account</h1>
      <p class="text-sm text-gray-500">Register to access the Prosperity MIS. Use a corporate email where possible.</p>
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <InputLabel for="name" value="Full name" :required="true" />
        <TextInput id="name" type="text" placeholder="John Doe" class="mt-1 block w-full" v-model="form.name" required autofocus autocomplete="name" />
        <InputError class="mt-2" :message="form.errors.name" />
      </div>

      <div>
        <InputLabel for="email" value="Email" :required="true" />
        <TextInput id="email" type="email" placeholder="you@company.com" class="mt-1 block w-full" v-model="form.email" required autocomplete="username" />
        <InputError class="mt-2" :message="form.errors.email" />
      </div>

      <div>
        <InputLabel for="password" value="Password" :required="true" />
        <div class="relative mt-1">
          <TextInput :type="showPassword ? 'text' : 'password'" id="password" placeholder="At least 8 characters" class="block w-full pr-10" v-model="form.password" required autocomplete="new-password" />
          <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 end-0 px-3 flex items-center text-gray-500" :aria-pressed="showPassword">
            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0112 20c-7 0-11-8-11-8a21.85 21.85 0 015-7"/><path d="M1 1l22 22"/></svg>
          </button>
        </div>
        <div class="mt-2">
          <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
            <div :class="['h-full transition-all', passwordStrength >= 3 ? 'bg-green-500' : passwordStrength >= 2 ? 'bg-yellow-400' : 'bg-red-400']" :style="{ width: (passwordStrength/4*100) + '%' }"></div>
          </div>
          <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">Use at least 8 characters, include uppercase, numbers, or symbols for a stronger password.</p>
          <p class="text-xs mt-1" :class="passwordStrength >=3 ? 'text-green-600' : passwordStrength >=2 ? 'text-yellow-500' : 'text-red-500'">{{ strengthLabel }}</p>
        </div>
        <InputError class="mt-2" :message="form.errors.password" />
      </div>

      <div>
        <InputLabel for="password_confirmation" value="Confirm Password" :required="true" />
        <TextInput id="password_confirmation" type="password" placeholder="Repeat your password" class="mt-1 block w-full" v-model="form.password_confirmation" required autocomplete="new-password" />
        <p v-if="form.password_confirmation && !passwordMatch" class="text-xs text-red-600 mt-1">Passwords do not match</p>
        <InputError class="mt-2" :message="form.errors.password_confirmation" />
      </div>

      <div class="flex items-start gap-2">
        <input id="terms" type="checkbox" v-model="form.accept_terms" class="mt-1" />
        <label for="terms" class="text-sm text-gray-600">I agree to the <a href="#" class="text-[#031226] hover:underline">terms and privacy policy</a></label>
      </div>

      <div class="flex items-center justify-end">
        <Link :href="route('login')" class="text-sm text-gray-600 underline mr-4">Already registered?</Link>
        <PrimaryButton :disabled="form.processing || !canSubmit" :class="{ 'opacity-25': form.processing }">Register</PrimaryButton>
      </div>
    </form>
  </GuestLayout>
</template>
