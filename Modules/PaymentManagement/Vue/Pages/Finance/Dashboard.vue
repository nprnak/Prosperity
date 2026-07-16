<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ applications: Array, status: String, paymentMethods: { type: Array, default: () => [] } });

const paymentForms = {};
const verify = (paymentId, status) => {
  useForm({ status, notes: '' }).post(route('finance.payments.verify', paymentId));
};

const initForm = (appId) => {
  if (!paymentForms[appId]) {
    paymentForms[appId] = useForm({
      amount: '', payment_mode: 'cash', payment_method_id: null, payment_date: '', bank_name: '', payment_reference_no: '', cheque_no: '', holding_id_no: '', id_type: 'citizenship', notes: ''
    });
  }
  return paymentForms[appId];
};
</script>

<template>
  <Head title="Finance Dashboard" />
  <AuthenticatedLayout>
    <template #header><h2 class="font-semibold text-xl">Finance Dashboard</h2></template>
    <div class="py-8 max-w-6xl mx-auto space-y-6">
      <div v-for="app in applications" :key="app.id" class="bg-white p-4 rounded shadow space-y-3">
        <div class="font-medium">{{ app.application_number }} · {{ app.applicant?.full_name_en }} · {{ app.status }}</div>
        <form @submit.prevent="initForm(app.id).post(route('finance.payments.store', app.id))" class="grid grid-cols-4 gap-2">
          <input v-model="initForm(app.id).amount" type="number" step="0.01" placeholder="Amount" class="border rounded p-2" />
          <select v-model="initForm(app.id).payment_mode" class="border rounded p-2"><option>cash</option><option>cheque</option><option>online_transfer</option><option>self_cheque_deposit</option><option>ips</option><option>mobile_banking</option></select>
          <select v-if="paymentMethods.length" v-model="initForm(app.id).payment_method_id" class="border rounded p-2">
            <option :value="null">— payment method —</option>
            <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
          </select>
          <input v-model="initForm(app.id).payment_date" type="date" class="border rounded p-2" />
          <button class="bg-indigo-600 text-white rounded px-3">Record Payment</button>
        </form>

        <div class="text-sm" v-for="payment in app.payment_transactions || []" :key="payment.id">
          {{ payment.receipt_number }} - {{ payment.amount }} - {{ payment.verification_status }}
          <button class="ml-2 text-green-700" @click="verify(payment.id, 'verified')">Verify</button>
          <button class="ml-2 text-red-700" @click="verify(payment.id, 'rejected')">Reject</button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
