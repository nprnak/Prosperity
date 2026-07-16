<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ pending: Array, recentlyReviewed: Array });

const approve = (applicantId) => useForm({}).post(route('applicants.profile.approve', applicantId));

const reject = (applicantId) => {
    const reason = window.prompt('Reason for rejection (sent to the applicant):');
    if (reason) {
        useForm({ rejection_reason: reason }).post(route('applicants.profile.reject', applicantId));
    }
};
</script>

<template>
  <Head title="Profile Review Queue" />
  <AuthenticatedLayout>
    <template #header><h2 class="font-semibold text-xl">Applicant Profile Review</h2></template>
    <div class="py-8 max-w-5xl mx-auto space-y-6">
      <div>
        <h3 class="font-semibold text-base mb-3">Pending review ({{ pending.length }})</h3>
        <p v-if="!pending.length" class="text-sm text-gray-500">No profiles waiting for review.</p>
        <div v-for="applicant in pending" :key="applicant.id" class="bg-white p-4 rounded shadow mb-3">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="font-medium">{{ applicant.full_name_en }} <span class="text-gray-400">·</span> {{ applicant.mobile }}</div>
              <div class="text-sm text-gray-600 mt-1">
                Citizenship: {{ applicant.citizenship_number || '—' }} ·
                BOID: {{ applicant.boid || '—' }} ·
                Bank: {{ applicant.bank_name || '—' }} ({{ applicant.bank_account_number || '—' }})
              </div>
              <div class="text-xs text-gray-400 mt-1">Submitted {{ applicant.profile_submitted_at }}</div>
            </div>
            <div class="flex gap-2 shrink-0">
              <button class="px-3 py-1 bg-green-600 text-white rounded" @click="approve(applicant.id)">Approve</button>
              <button class="px-3 py-1 bg-red-600 text-white rounded" @click="reject(applicant.id)">Reject</button>
            </div>
          </div>
        </div>
      </div>

      <div>
        <h3 class="font-semibold text-base mb-3">Recently reviewed</h3>
        <p v-if="!recentlyReviewed.length" class="text-sm text-gray-500">Nothing reviewed yet.</p>
        <table v-else class="w-full bg-white rounded shadow text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 uppercase border-b">
              <th class="px-4 py-2">Applicant</th>
              <th class="px-4 py-2">Status</th>
              <th class="px-4 py-2">Reviewed by</th>
              <th class="px-4 py-2">Reviewed at</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="applicant in recentlyReviewed" :key="applicant.id">
              <td class="px-4 py-2">{{ applicant.full_name_en }}</td>
              <td class="px-4 py-2">
                <span
                  :class="applicant.profile_status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                  class="px-2 py-0.5 rounded-full text-xs font-semibold"
                >{{ applicant.profile_status }}</span>
              </td>
              <td class="px-4 py-2">{{ applicant.profile_reviewer?.name || '—' }}</td>
              <td class="px-4 py-2">{{ applicant.profile_reviewed_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
