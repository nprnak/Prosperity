<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  logs: Array,
});
</script>

<template>
  <Head title="Admin - Activity Log" />
  <PanelLayout>
    <div class="bg-white rounded-lg shadow p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Activity Log</h2>
        <button class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
          Export
        </button>
      </div>

      <!-- Filter -->
      <div class="mb-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Event Type</label>
            <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-600 focus:border-transparent">
              <option>All Events</option>
              <option>Login</option>
              <option>Create</option>
              <option>Update</option>
              <option>Delete</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
            <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-600 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
            <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-600 focus:border-transparent" />
          </div>
        </div>
        <button class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Search</button>
      </div>

      <!-- Activity Table -->
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-4 text-sm text-gray-600">2026-07-12 15:30</td>
              <td class="px-6 py-4 text-sm text-gray-900">Admin User</td>
              <td class="px-6 py-4 text-sm"><span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-semibold">UPDATE</span></td>
              <td class="px-6 py-4 text-sm text-gray-600">Application submitted</td>
              <td class="px-6 py-4 text-sm"><button class="text-indigo-600 hover:text-indigo-900">View</button></td>
            </tr>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-4 text-sm text-gray-600">2026-07-12 14:15</td>
              <td class="px-6 py-4 text-sm text-gray-900">Finance Staff</td>
              <td class="px-6 py-4 text-sm"><span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-semibold">CREATE</span></td>
              <td class="px-6 py-4 text-sm text-gray-600">Payment verified</td>
              <td class="px-6 py-4 text-sm"><button class="text-indigo-600 hover:text-indigo-900">View</button></td>
            </tr>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-4 text-sm text-gray-600">2026-07-12 13:45</td>
              <td class="px-6 py-4 text-sm text-gray-900">Approver</td>
              <td class="px-6 py-4 text-sm"><span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs font-semibold">APPROVE</span></td>
              <td class="px-6 py-4 text-sm text-gray-600">Application approved</td>
              <td class="px-6 py-4 text-sm"><button class="text-indigo-600 hover:text-indigo-900">View</button></td>
            </tr>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-4 text-sm text-gray-600">2026-07-12 10:20</td>
              <td class="px-6 py-4 text-sm text-gray-900">Admin User</td>
              <td class="px-6 py-4 text-sm"><span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-semibold">LOGIN</span></td>
              <td class="px-6 py-4 text-sm text-gray-600">User logged in</td>
              <td class="px-6 py-4 text-sm"><button class="text-indigo-600 hover:text-indigo-900">View</button></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="mt-6 flex justify-between items-center">
        <span class="text-sm text-gray-600">Showing 1 to 10 of 100 entries</span>
        <div class="space-x-2">
          <button class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">Previous</button>
          <button class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">Next</button>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
