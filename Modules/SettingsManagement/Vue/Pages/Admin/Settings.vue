<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  settings: Object,
});
</script>

<template>
  <Head title="Admin - Settings" />
  <PanelLayout>
    <div class="space-y-6">
      <!-- Application Settings -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Application Settings</h2>

        <div class="space-y-6">
          <!-- System Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">System Name</label>
            <input type="text" value="Prosperity" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-600 focus:border-transparent" />
          </div>

          <!-- Contact Email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
            <input type="email" value="contact@prosperity.com" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-600 focus:border-transparent" />
          </div>

          <!-- Support Phone -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Support Phone</label>
            <input type="tel" placeholder="+977 1 XXXXXXX" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-600 focus:border-transparent" />
          </div>

          <!-- Max Applications Per User -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Max Applications Per User</label>
            <input type="number" value="5" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-600 focus:border-transparent" />
          </div>

          <!-- Enable Email Notifications -->
          <div class="flex items-center">
            <input type="checkbox" id="email-notif" checked class="rounded" />
            <label for="email-notif" class="ml-2 text-sm text-gray-700">Enable Email Notifications</label>
          </div>

          <!-- Save Button -->
          <div class="pt-4">
            <button class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Settings</button>
          </div>
        </div>
      </div>

      <!-- Roles & Permissions -->
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Roles & Permissions</h3>
        <div class="space-y-4">
          <div class="flex items-center justify-between p-4 border rounded-lg">
            <span class="font-medium">Admin</span>
            <button class="text-indigo-600 hover:text-indigo-900">Edit</button>
          </div>
          <div class="flex items-center justify-between p-4 border rounded-lg">
            <span class="font-medium">Finance Staff</span>
            <button class="text-indigo-600 hover:text-indigo-900">Edit</button>
          </div>
          <div class="flex items-center justify-between p-4 border rounded-lg">
            <span class="font-medium">Approver</span>
            <button class="text-indigo-600 hover:text-indigo-900">Edit</button>
          </div>
          <div class="flex items-center justify-between p-4 border rounded-lg">
            <span class="font-medium">Applicant</span>
            <button class="text-indigo-600 hover:text-indigo-900">Edit</button>
          </div>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
