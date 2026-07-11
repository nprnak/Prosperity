<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ applications: Array });
const reject = (appId) => useForm({ rejection_reason: 'Insufficient details' }).post(route('approver.applications.reject', appId));
const approve = (appId) => useForm({}).post(route('approver.applications.approve', appId));
</script>

<template>
  <Head title="Approver Dashboard" />
  <AuthenticatedLayout>
    <template #header><h2 class="font-semibold text-xl">Approver Dashboard</h2></template>
    <div class="py-8 max-w-5xl mx-auto space-y-4">
      <div v-for="app in applications" :key="app.id" class="bg-white p-4 rounded shadow">
        <div class="font-medium">{{ app.application_number }} · {{ app.applicant?.full_name_english }}</div>
        <div class="mt-2 flex gap-2">
          <button class="px-3 py-1 bg-green-600 text-white rounded" @click="approve(app.id)">Approve</button>
          <button class="px-3 py-1 bg-red-600 text-white rounded" @click="reject(app.id)">Reject</button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
