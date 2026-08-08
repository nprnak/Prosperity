<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const form = useForm({ code: '' });
const digits = ref(['', '', '', '', '', '']);
const inputs = ref(Array.from({ length: digits.value.length }).map(() => null));
const countdown = ref(30);
let timer = null;

const startTimer = () => {
  countdown.value = 30;
  if (timer) clearInterval(timer);
  timer = setInterval(() => {
    if (countdown.value > 0) countdown.value -= 1;
    else clearInterval(timer);
  }, 1000);
};

onMounted(() => startTimer());

import { onBeforeUnmount } from 'vue';
onBeforeUnmount(() => { if (timer) clearInterval(timer); });

const setInputRef = (el, i) => {
  // guard against render timing issues
  if (!inputs.value) inputs.value = Array.from({ length: digits.value.length }).map(() => null);
  inputs.value[i] = el || null;
};

const focusNext = (i, ev) => {
  const val = ev.target.value;
  if (/^[0-9]$/.test(val)) {
    digits.value[i] = val;
    if (i < inputs.value.length - 1 && inputs.value[i + 1]) inputs.value[i + 1].focus();
  } else if (val.length > 1) {
    // handle paste
    const seq = val.replace(/\s+/g, '').slice(0, digits.value.length).split('');
    for (let j = 0; j < seq.length; j++) {
      digits.value[j] = seq[j];
      if (inputs.value[j]) inputs.value[j].value = seq[j];
    }
  }
  updateFormCode();
};

const backspace = (i, ev) => {
  if (ev.key === 'Backspace') {
    if (digits.value[i]) {
      digits.value[i] = '';
    } else if (i > 0) {
      inputs.value[i - 1].focus();
      digits.value[i - 1] = '';
    }
    updateFormCode();
  }
};

const updateFormCode = () => {
  form.code = digits.value.join('');
};

const submit = () => {
  updateFormCode();
  if (!form.code || form.code.length < digits.value.length) return;
  form.post(route('two-factor.verify'), {
    onError: () => {
      digits.value = Array.from({ length: digits.value.length }).map(() => '');
      updateFormCode();
    },
  });
};

const resend = () => {
  // Best-effort resend: call two-factor.resend if available. Send email if stored from login flow.
  const email = localStorage.getItem('last_auth_email');
  const headers = { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' };
  let options = { method: 'POST', headers };
  if (email) {
    headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify({ email });
  }
  fetch(route('two-factor.resend'), options)
    .then(() => startTimer())
    .catch(() => startTimer());
};
</script>

<template>
  <GuestLayout>
    <Head title="Two-Factor Authentication" />

    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">This account requires a second authentication step. Enter the verification code sent to your email address.</div>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Authentication Code</label>
        <div class="flex gap-2">
          <input v-for="(d, i) in digits" :key="i" type="tel" inputmode="numeric" maxlength="1" :aria-label="'Digit ' + (i+1)" class="w-14 h-14 text-center rounded-md border-gray-300 bg-white shadow-sm text-lg focus:outline-none focus:ring-2 focus:ring-[#031226] transform transition duration-150 focus:scale-105 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" @input="(e) => focusNext(i, e)" @keydown="(e) => backspace(i, e)" :ref="(el) => setInputRef(el, i)" />
        </div>
        <InputError class="mt-2" :message="form.errors.code" />
      </div>

      <div class="flex items-center justify-between">
        <PrimaryButton :disabled="form.processing || !form.code || form.code.length < digits.length" :class="{ 'opacity-25': form.processing }">Verify</PrimaryButton>

        <div class="text-sm">
          <button type="button" @click="resend" :disabled="countdown > 0" class="text-[#031226] hover:underline disabled:opacity-50">Resend code</button>
          <span v-if="countdown > 0" class="text-gray-500 ms-2">({{ countdown }})</span>
        </div>
      </div>

      <div class="text-sm">
        <Link :href="route('logout')" method="post" as="button" class="text-gray-600 underline">Cancel</Link>
      </div>
    </form>
  </GuestLayout>
</template>
