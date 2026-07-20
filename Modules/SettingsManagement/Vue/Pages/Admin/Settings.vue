<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ settings: Object });

const form = useForm({
    ...props.settings.organization,
    ...props.settings.mail,
    ...props.settings.application,
});

const save = () => {
    form.put(route('admin.settings.update'), { preserveScroll: true });
};
</script>

<template>
  <Head title="Site Settings" />
  <PanelLayout>
    <div class="space-y-6">
      <h2 class="text-xl font-semibold text-gray-900">Site Settings</h2>

      <!-- Organization -->
      <div class="bg-white p-5 rounded-lg shadow">
        <h3 class="font-semibold text-base mb-1">Organization</h3>
        <p class="text-sm text-gray-500 mb-4">Shown on receipts, vouchers and outgoing emails.</p>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Organization Name *</label>
            <input v-model="form.org_name" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.org_name" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Contact Email *</label>
            <input v-model="form.contact_email" type="email" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.contact_email" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Support Phone</label>
            <input v-model="form.support_phone" placeholder="+977 1 XXXXXXX" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.support_phone" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Address</label>
            <input v-model="form.org_address" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.org_address" class="mt-1" />
          </div>
        </div>
      </div>

      <!-- Email / SMTP -->
      <div class="bg-white p-5 rounded-lg shadow">
        <h3 class="font-semibold text-base mb-1">Email (SMTP)</h3>
        <p class="text-sm text-gray-500 mb-4">Leave the host empty to keep the server default mailer.</p>
        <div class="grid gap-4 md:grid-cols-3">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">SMTP Host</label>
            <input v-model="form.mail_host" placeholder="smtp.example.com" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.mail_host" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Port</label>
            <input v-model="form.mail_port" type="number" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.mail_port" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Encryption</label>
            <select v-model="form.mail_encryption" class="w-full rounded-lg border border-gray-300 px-3 py-2">
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
              <option value="none">None</option>
            </select>
            <InputError :message="form.errors.mail_encryption" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Username</label>
            <input v-model="form.mail_username" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.mail_username" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Password</label>
            <input v-model="form.mail_password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.mail_password" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">From Address</label>
            <input v-model="form.mail_from_address" type="email" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.mail_from_address" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">From Name</label>
            <input v-model="form.mail_from_name" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.mail_from_name" class="mt-1" />
          </div>
        </div>
      </div>

      <!-- Application defaults -->
      <div class="bg-white p-5 rounded-lg shadow">
        <h3 class="font-semibold text-base mb-1">Application</h3>
        <p class="text-sm text-gray-500 mb-4">Currency and limits applied across the application.</p>
        <div class="grid gap-4 md:grid-cols-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Currency Code *</label>
            <input v-model="form.currency_code" maxlength="3" placeholder="NPR" class="w-full rounded-lg border border-gray-300 px-3 py-2 uppercase" />
            <InputError :message="form.errors.currency_code" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Currency Symbol *</label>
            <input v-model="form.currency_symbol" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.currency_symbol" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Max Upload Size (KB) *</label>
            <input v-model="form.max_upload_size_kb" type="number" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.max_upload_size_kb" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Max Applications / User *</label>
            <input v-model="form.max_applications_per_user" type="number" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.max_applications_per_user" class="mt-1" />
          </div>
        </div>
      </div>

      <div>
        <button class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="form.processing" @click="save">
          Save Settings
        </button>
        <span v-if="form.recentlySuccessful" class="ml-3 text-sm text-green-700">Saved.</span>
      </div>
    </div>
  </PanelLayout>
</template>
