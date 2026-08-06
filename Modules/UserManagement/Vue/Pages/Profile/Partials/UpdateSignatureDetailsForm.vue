<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  signatureDetails: {
    type: Object,
    default: () => ({ signature_name: '', signature_designation: '', has_signature: false }),
  },
});

const form = useForm({
  signature_name: props.signatureDetails?.signature_name || '',
  signature_designation: props.signatureDetails?.signature_designation || '',
  signature_file: null,
});

const submit = () => {
  form.post(route('settings.signature.update'), {
    preserveScroll: true,
    forceFormData: true,
    _method: 'patch',
  });
};
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900">Receipt Signature Details</h2>
      <p class="mt-1 text-sm text-gray-600">
        These details are used when your signature is printed on receipts.
      </p>
    </header>

    <form class="mt-6 space-y-4" @submit.prevent="submit">
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Display Name for Signature</label>
        <input
          v-model="form.signature_name"
          type="text"
          class="w-full rounded-lg border border-gray-300 px-3 py-2"
          placeholder="Defaults to your account name if left blank"
        />
        <InputError :message="form.errors.signature_name" class="mt-1" />
      </div>

      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Designation</label>
        <input
          v-model="form.signature_designation"
          type="text"
          class="w-full rounded-lg border border-gray-300 px-3 py-2"
          placeholder="Example: Application Verifier"
        />
        <InputError :message="form.errors.signature_designation" class="mt-1" />
      </div>

      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Signature Image</label>
        <input
          type="file"
          accept="image/*"
          @change="form.signature_file = $event.target.files[0] || null"
        />
        <p class="mt-1 text-xs text-gray-500">PNG or JPG, max 2MB.</p>
        <InputError :message="form.errors.signature_file" class="mt-1" />
      </div>

      <div v-if="signatureDetails?.has_signature" class="text-sm text-green-700">
        Current signature is uploaded.
        <a :href="route('settings.signature.preview')" target="_blank" class="ml-2 font-semibold underline">Preview</a>
      </div>

      <div>
        <button
          type="submit"
          class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
          :disabled="form.processing"
        >
          Save Signature Details
        </button>
      </div>
    </form>
  </section>
</template>
