<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const user = usePage().props.auth.user;

const profileForm = useForm({
  name: user?.name || '',
  email: user?.email || '',
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const submitProfile = () => {
  profileForm.patch(route('admin.credentials.profile.update'));
};

const submitPassword = () => {
  passwordForm.patch(route('admin.credentials.password.update'), {
    onSuccess: () => passwordForm.reset(),
  });
};

const inputClass = 'w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-blue-500';
const buttonClass = 'rounded-lg bg-blue-600 px-5 py-2.5 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60';
</script>

<template>
  <Head title="Admin - Settings" />
  <PanelLayout>
    <div class="space-y-6">
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Settings</h2>
        <p class="text-sm text-gray-600">Manage your username, email, and password in the settings tab.</p>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-semibold text-gray-900 mb-4">Username & Email</h3>
        <form @submit.prevent="submitProfile" class="space-y-4 max-w-2xl">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
            <input v-model="profileForm.name" type="text" :class="inputClass" placeholder="Enter username" />
            <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input v-model="profileForm.email" type="email" :class="inputClass" placeholder="Enter email" />
            <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
          </div>

          <div class="pt-2">
            <button type="submit" :class="buttonClass" :disabled="profileForm.processing">
              Save Login Details
            </button>
            <span v-if="profileForm.recentlySuccessful" class="ml-3 text-sm text-green-700">Saved.</span>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-semibold text-gray-900 mb-4">Password</h3>
        <form @submit.prevent="submitPassword" class="space-y-4 max-w-2xl">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
            <input v-model="passwordForm.current_password" type="password" :class="inputClass" placeholder="Enter current password" />
            <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input v-model="passwordForm.password" type="password" :class="inputClass" placeholder="Minimum 8 characters" />
            <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
            <input v-model="passwordForm.password_confirmation" type="password" :class="inputClass" placeholder="Re-enter new password" />
          </div>

          <div class="pt-2">
            <button type="submit" :class="buttonClass" :disabled="passwordForm.processing">
              Update Password
            </button>
            <span v-if="passwordForm.recentlySuccessful" class="ml-3 text-sm text-green-700">Password updated.</span>
          </div>
        </form>
      </div>
    </div>
  </PanelLayout>
</template>
