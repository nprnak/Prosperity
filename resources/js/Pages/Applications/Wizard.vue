<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
  draft: Object,
  applications: Array,
});

const form = useForm({
  step: 1,
  payload: {
    full_name_nepali: props.draft?.applicant?.full_name_nepali || '',
    full_name_english: props.draft?.applicant?.full_name_english || '',
    date_of_birth: props.draft?.applicant?.date_of_birth || '',
    age: props.draft?.applicant?.age || '',
    mobile_number: props.draft?.applicant?.mobile_number || '',
    email: props.draft?.applicant?.email || '',
    permanent_district: props.draft?.applicant?.permanent_district || '',
    permanent_municipality: props.draft?.applicant?.permanent_municipality || '',
    permanent_ward: props.draft?.applicant?.permanent_ward || '',
    shares_applied: props.draft?.shares_applied || 1,
    total_amount_declared: props.draft?.total_amount_declared || 100,
    declaration_accepted: false,
    photo: null,
    citizenship_doc: null,
    national_id_doc: null,
    pan_doc: null,
  },
});

const saveStep = () => {
  form.post(route('applications.draft'), { forceFormData: true });
};

const submitFinal = () => {
  const id = props.draft?.id;
  if (!id) return;
  useForm({ declaration_accepted: true }).post(route('applications.submit', id));
};
</script>

<template>
  <Head title="Application Wizard" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Application Wizard</h2>
    </template>

    <div class="py-8 max-w-5xl mx-auto space-y-6">
      <div class="bg-white p-6 rounded shadow">
        <p class="text-sm text-gray-500 mb-4">5-step draft flow: personal → address/ID → investment/heir → work/docs → declaration/review.</p>
        <div class="grid grid-cols-2 gap-4">
          <input v-model="form.payload.full_name_nepali" placeholder="Full name (Nepali)" class="border rounded p-2" />
          <input v-model="form.payload.full_name_english" placeholder="Full name (English)" class="border rounded p-2" />
          <input v-model="form.payload.date_of_birth" type="date" class="border rounded p-2" />
          <input v-model="form.payload.age" type="number" min="0" placeholder="Age" class="border rounded p-2" />
          <input v-model="form.payload.mobile_number" placeholder="Mobile" class="border rounded p-2" />
          <input v-model="form.payload.email" type="email" placeholder="Email" class="border rounded p-2" />
          <input v-model="form.payload.permanent_district" placeholder="Permanent district" class="border rounded p-2" />
          <input v-model="form.payload.permanent_municipality" placeholder="Permanent municipality" class="border rounded p-2" />
          <input v-model="form.payload.permanent_ward" placeholder="Permanent ward" class="border rounded p-2" />
          <input v-model="form.payload.shares_applied" type="number" min="1" placeholder="Shares applied" class="border rounded p-2" />
          <input v-model="form.payload.total_amount_declared" type="number" step="0.01" min="0" placeholder="Total declared" class="border rounded p-2" />
          <input type="file" @change="form.payload.photo = $event.target.files[0]" class="border rounded p-2" />
          <input type="file" @change="form.payload.citizenship_doc = $event.target.files[0]" class="border rounded p-2" />
          <input type="file" @change="form.payload.national_id_doc = $event.target.files[0]" class="border rounded p-2" />
          <input type="file" @change="form.payload.pan_doc = $event.target.files[0]" class="border rounded p-2" />
        </div>
        <div class="mt-4 flex gap-2">
          <button @click="saveStep" class="px-4 py-2 bg-indigo-600 text-white rounded">Save Draft</button>
          <button @click="submitFinal" class="px-4 py-2 bg-green-600 text-white rounded" :disabled="!draft">Submit</button>
        </div>
      </div>

      <div class="bg-white p-6 rounded shadow">
        <h3 class="font-semibold mb-2">My Applications</h3>
        <table class="w-full text-sm">
          <thead><tr class="text-left"><th>No</th><th>Status</th><th>Shares</th><th>Total</th></tr></thead>
          <tbody>
            <tr v-for="app in applications" :key="app.id" class="border-t">
              <td>{{ app.application_number }}</td>
              <td>{{ app.status }}</td>
              <td>{{ app.shares_applied }}</td>
              <td>{{ app.total_amount_declared }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
