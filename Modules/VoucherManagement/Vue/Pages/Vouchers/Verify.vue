<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  code: String,
  result: Object,
});

const input = ref(props.code || '');

const lookup = () => {
  const code = input.value.trim();
  if (!code) return;
  router.get(route('vouchers.verify'), { code }, { preserveState: true });
};
</script>

<template>
  <Head title="Verify Voucher" />
  <div class="min-h-screen bg-slate-100 flex items-center justify-center px-4">
    <div class="w-full max-w-lg space-y-6">
      <div class="text-center">
        <h1 class="text-2xl font-bold text-gray-900">{{ $page.props.settings?.org_name || 'Prosperity' }}</h1>
        <p class="text-sm text-gray-600 mt-1">Voucher Verification</p>
      </div>

      <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <p class="text-sm text-gray-600">
          Enter the verification code printed on the voucher, or scan its QR code.
        </p>
        <div class="flex gap-2">
          <input
            v-model="input"
            placeholder="e.g. A1B2C3D4E5F6"
            class="flex-1 rounded-lg border border-gray-300 px-4 py-2 uppercase tracking-widest"
            @keyup.enter="lookup"
          />
          <button class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white" @click="lookup">Verify</button>
        </div>
      </div>

      <div v-if="result" class="rounded-lg shadow p-6"
          :class="result.valid ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
        <template v-if="result.valid">
          <p class="font-semibold text-green-800">✔ This voucher is genuine.</p>
          <dl class="mt-4 space-y-2 text-sm text-gray-700">
            <div class="flex justify-between"><dt class="font-medium">Voucher No</dt><dd>{{ result.voucher_number }}</dd></div>
            <div class="flex justify-between"><dt class="font-medium">Application No</dt><dd>{{ result.application_number || '—' }}</dd></div>
            <div class="flex justify-between"><dt class="font-medium">Amount</dt><dd>{{ $page.props.settings?.currency_code || 'NPR' }} {{ result.amount }}</dd></div>
            <div class="flex justify-between"><dt class="font-medium">Payment Date</dt><dd>{{ result.payment_date || '—' }}</dd></div>
            <div class="flex justify-between"><dt class="font-medium">Issued On</dt><dd>{{ result.generated_at || '—' }}</dd></div>
          </dl>
        </template>
        <template v-else>
          <p class="font-semibold text-red-800">✘ No voucher matches this code.</p>
          <p class="text-sm text-red-700 mt-2">Check the code and try again. If the problem persists, contact
            {{ $page.props.settings?.contact_email || 'the issuing office' }}.</p>
        </template>
      </div>
    </div>
  </div>
</template>
