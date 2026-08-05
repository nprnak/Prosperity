<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  payments: Array,
  stats: Object,
  filters: { type: Object, default: () => ({}) },
});

const status = ref(props.filters.status || '');

const applyFilter = () => {
  router.get(route('admin.payments'), status.value ? { status: status.value } : {}, {
    preserveState: true,
    preserveScroll: true,
  });
};

// The named method when finance recorded one, otherwise the mode the money
// arrived by. The template previously read payment_method, which is neither —
// the column is payment_mode and the relation is paymentMethod.
const paymentMethodLabel = (payment) =>
  payment.payment_method?.name
  || (payment.payment_mode || '').replace(/_/g, ' ')
  || '—';
</script>

<template>
  <Head title="Admin - Payments Management" />
  <PanelLayout>
    <div class="space-y-6">
      <!-- Payment Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-6 border border-green-200">
          <h3 class="text-gray-600 text-sm font-medium">Total Verified</h3>
          <p class="text-4xl font-bold text-green-600 mt-2">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ stats?.verifiedAmount || 0 }}</p>
        </div>
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg p-6 border border-orange-200">
          <h3 class="text-gray-600 text-sm font-medium">Pending Verification</h3>
          <p class="text-4xl font-bold text-orange-600 mt-2">{{ stats?.pendingCount || 0 }}</p>
        </div>
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-6 border border-blue-200">
          <h3 class="text-gray-600 text-sm font-medium">Total Transactions</h3>
          <p class="text-4xl font-bold text-blue-600 mt-2">{{ stats?.totalCount || 0 }}</p>
        </div>
      </div>

      <!-- Payments Table -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-900">Payments Management</h2>
          <div class="space-x-2">
            <select v-model="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-600">
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="verified">Verified</option>
              <option value="rejected">Rejected</option>
            </select>
            <button type="button" @click="applyFilter" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Filter</button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application No</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Method</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="payment in payments" :key="payment.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm font-medium text-gray-900">TXN-{{ payment.id }}</td>
                <td class="px-6 py-4 text-sm text-gray-600">{{ payment.share_application?.applicant?.full_name_en }}</td>
                <td class="px-6 py-4 text-sm text-gray-600">{{ payment.share_application?.application_number }}</td>
                <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ payment.amount }}</td>
                <td class="px-6 py-4 text-sm text-gray-600 capitalize">{{ paymentMethodLabel(payment) }}</td>
                <td class="px-6 py-4 text-sm">
                  <span :class="{
                    'bg-yellow-100 text-yellow-700': payment.verification_status === 'pending',
                    'bg-green-100 text-green-700': payment.verification_status === 'verified',
                    'bg-red-100 text-red-700': payment.verification_status === 'rejected',
                  }" class="px-3 py-1 rounded-full text-xs font-semibold capitalize">
                    {{ payment.verification_status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">{{ payment.payment_date }}</td>
                <!-- This page is payment.view-any: a read-only overview. The
                     Verify and Reject buttons that used to sit here had no
                     handlers and no permission behind them; verification is
                     the finance dashboard's job, under payment.verify. -->
                <td class="px-6 py-4 text-sm">
                  <Link
                    v-if="payment.share_application?.id"
                    :href="route('admin.applications.show', payment.share_application.id)"
                    class="font-semibold text-indigo-600 hover:text-indigo-900"
                  >
                    View application
                  </Link>
                  <span v-else class="text-gray-400">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="!payments || payments.length === 0" class="text-center py-12">
          <p class="text-gray-500">No payments found</p>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
