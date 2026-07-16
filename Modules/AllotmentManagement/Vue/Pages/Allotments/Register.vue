<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({ allotments: Array, pendingApplications: Array, totalShares: Number, filters: Object });
const createAllotment = (appId) => {
  useForm({ shares_allotted: 1, allotment_date: new Date().toISOString().slice(0,10), demat_account_no: '', dp_id: '', client_id: '', certificate_number: '' })
    .post(route('allotments.store', appId));
};
const applyFilters = (event) => {
  const form = new FormData(event.target);
  router.get(route('allotments.register'), { q: form.get('q'), sort: form.get('sort') }, { preserveState: true });
};
</script>

<template>
  <Head title="Shareholder Register" />
  <AuthenticatedLayout>
    <template #header><h2 class="font-semibold text-xl">Shareholder Register</h2></template>
    <div class="py-8 max-w-6xl mx-auto space-y-4">
      <div class="bg-white rounded shadow p-4">Total shares allotted: <strong>{{ totalShares }}</strong></div>
      <div class="bg-white rounded shadow p-4">
        <Link :href="route('allotments.export')" class="px-3 py-2 bg-indigo-600 text-white rounded">Export Excel/CSV</Link>
      </div>
      <div class="bg-white rounded shadow p-4">
        <h3 class="font-medium">Pending approved applications</h3>
        <div v-for="app in pendingApplications" :key="app.id" class="mt-2">
          {{ app.application_number }} · {{ app.applicant?.full_name_en }}
          <button class="ml-2 text-indigo-700" @click="createAllotment(app.id)">Create allotment</button>
        </div>
      </div>
      <div class="bg-white rounded shadow p-4">
        <h3 class="font-medium">Allotments</h3>
        <form class="my-3 flex gap-2" @submit.prevent="applyFilters">
          <input name="q" :value="filters?.q || ''" placeholder="Search applicant" class="border rounded p-2" />
          <select name="sort" class="border rounded p-2" :value="filters?.sort || 'desc'">
            <option value="desc">Newest</option>
            <option value="asc">Oldest</option>
          </select>
          <button class="px-3 py-2 bg-gray-700 text-white rounded">Apply</button>
        </form>
        <table class="w-full text-sm">
          <thead><tr class="text-left"><th>Applicant</th><th>Shares</th><th>Date</th></tr></thead>
          <tbody>
            <tr v-for="a in allotments" :key="a.id" class="border-t"><td>{{ a.applicant?.full_name_en }}</td><td>{{ a.shares_allotted }}</td><td>{{ a.allotment_date }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
