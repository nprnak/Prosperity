<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineProps({ applications: Array });

const rejecting = reactive({});

const verify = (appId) => {
    router.post(route('verifier.applications.verify', appId), {}, { preserveScroll: true });
};

const reject = (appId) => {
    const reason = (rejecting[appId] || '').trim();
    if (!reason) return;
    router.post(route('verifier.applications.reject', appId), { rejection_reason: reason }, { preserveScroll: true });
};
</script>

<template>
  <Head title="Verifier Dashboard" />
  <AuthenticatedLayout>
    <template #header><h2 class="font-semibold text-xl">Verifier Dashboard</h2></template>
    <div class="py-8 max-w-5xl mx-auto space-y-4 px-4">
      <div v-if="$page.props.flash?.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ $page.props.flash.success }}
      </div>

      <p class="text-sm text-gray-600">Reviewed applications awaiting verification. Verified applications move on to the approver.</p>

      <div v-for="app in applications" :key="app.id" class="bg-white p-4 rounded shadow">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <div class="font-medium">{{ app.application_number }} · {{ app.applicant?.full_name_english }}</div>
            <div class="text-sm text-gray-500">
              {{ app.shares_applied }} shares · {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ app.total_amount_declared }}
              <span v-if="app.reviewer"> · reviewed by {{ app.reviewer.name }}</span>
            </div>
          </div>
          <button class="px-3 py-1 bg-blue-600 text-white rounded" @click="verify(app.id)">Mark Verified</button>
        </div>
        <div class="mt-3 flex gap-2">
          <input v-model="rejecting[app.id]" placeholder="Rejection reason" class="flex-1 rounded border border-gray-300 px-3 py-1 text-sm" />
          <button class="px-3 py-1 bg-red-600 text-white rounded disabled:opacity-50" :disabled="!(rejecting[app.id] || '').trim()" @click="reject(app.id)">Reject</button>
        </div>
      </div>

      <div v-if="!applications.length" class="bg-white p-6 rounded shadow text-center text-sm text-gray-500">
        No applications waiting for verification.
      </div>
    </div>
  </AuthenticatedLayout>
</template>
