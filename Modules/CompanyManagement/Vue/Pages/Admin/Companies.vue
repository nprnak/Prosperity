<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ companies: Array, offeringStatuses: Array });

const companyForm = useForm({ name: '', code: '', description: '', status: 'active' });
const storeCompany = () => companyForm.post(route('admin.companies.store'), {
    preserveScroll: true,
    onSuccess: () => companyForm.reset(),
});

const offeringTarget = ref(null);
const offeringForm = useForm({
    title: '', fiscal_year: '', total_shares: 100000, share_rate: 100,
    min_shares: 10, max_shares: 1000, opens_at: '', closes_at: '', status: 'draft',
});
const storeOffering = () => offeringForm.post(route('admin.offerings.store', offeringTarget.value), {
    preserveScroll: true,
    onSuccess: () => { offeringForm.reset(); offeringTarget.value = null; },
});

const setOfferingStatus = (offering, status) =>
    useForm({ ...offering, status }).patch(route('admin.offerings.update', offering.id), { preserveScroll: true });

const statusBadge = (status) => ({
    draft: 'bg-gray-100 text-gray-600',
    upcoming: 'bg-blue-100 text-blue-700',
    open: 'bg-green-100 text-green-700',
    closed: 'bg-amber-100 text-amber-700',
    completed: 'bg-slate-200 text-slate-700',
}[status] || 'bg-gray-100 text-gray-600');
</script>

<template>
  <Head title="Companies" />
  <PanelLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-900">Companies &amp; Share Offerings</h2>
      </div>

      <div v-if="$page.props.flash?.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ $page.props.flash.success }}
      </div>

      <div class="bg-white p-5 rounded-lg shadow">
        <h3 class="font-semibold text-base mb-4">Add company</h3>
        <div class="grid gap-4 md:grid-cols-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Name *</label>
            <input v-model="companyForm.name" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="companyForm.errors.name" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Code *</label>
            <input v-model="companyForm.code" placeholder="e.g. PHL" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="companyForm.errors.code" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
            <select v-model="companyForm.status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="flex items-end">
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" :disabled="companyForm.processing" @click="storeCompany">
              Add Company
            </button>
          </div>
        </div>
      </div>

      <div v-for="company in companies" :key="company.id" class="bg-white p-5 rounded-lg shadow">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="font-semibold text-base">{{ company.name }} <span class="text-gray-400 font-normal">({{ company.code }})</span></h3>
            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="company.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
              {{ company.status }}
            </span>
          </div>
          <button class="rounded-lg border border-blue-300 px-3 py-1.5 text-sm text-blue-700"
              @click="offeringTarget = offeringTarget === company.id ? null : company.id">
            {{ offeringTarget === company.id ? 'Cancel' : 'New offering' }}
          </button>
        </div>

        <div v-if="offeringTarget === company.id" class="mt-4 grid gap-3 rounded-lg border border-blue-100 bg-blue-50/50 p-4 md:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Title *</label>
            <input v-model="offeringForm.title" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
            <InputError :message="offeringForm.errors.title" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Fiscal Year *</label>
            <input v-model="offeringForm.fiscal_year" placeholder="2082/83" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
            <InputError :message="offeringForm.errors.fiscal_year" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Total Shares *</label>
            <input v-model="offeringForm.total_shares" type="number" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
            <InputError :message="offeringForm.errors.total_shares" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Rate (Rs.) *</label>
            <input v-model="offeringForm.share_rate" type="number" step="0.01" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
            <InputError :message="offeringForm.errors.share_rate" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Min Shares *</label>
            <input v-model="offeringForm.min_shares" type="number" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
            <InputError :message="offeringForm.errors.min_shares" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Max Shares *</label>
            <input v-model="offeringForm.max_shares" type="number" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
            <InputError :message="offeringForm.errors.max_shares" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Opens</label>
            <input v-model="offeringForm.opens_at" type="date" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Closes</label>
            <input v-model="offeringForm.closes_at" type="date" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
            <InputError :message="offeringForm.errors.closes_at" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Status</label>
            <select v-model="offeringForm.status" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
              <option v-for="status in offeringStatuses" :key="status" :value="status">{{ status }}</option>
            </select>
          </div>
          <div class="flex items-end">
            <button class="rounded bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white" :disabled="offeringForm.processing" @click="storeOffering">
              Save Offering
            </button>
          </div>
        </div>

        <table v-if="company.offerings?.length" class="mt-4 w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 uppercase border-b">
              <th class="py-2 pr-4">Offering</th>
              <th class="py-2 pr-4">FY</th>
              <th class="py-2 pr-4">Rate</th>
              <th class="py-2 pr-4">Min/Max</th>
              <th class="py-2 pr-4">Window</th>
              <th class="py-2 pr-4">Applications</th>
              <th class="py-2 pr-4">Status</th>
              <th class="py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="offering in company.offerings" :key="offering.id">
              <td class="py-2 pr-4 font-medium">{{ offering.title }}</td>
              <td class="py-2 pr-4">{{ offering.fiscal_year }}</td>
              <td class="py-2 pr-4">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ offering.share_rate }}</td>
              <td class="py-2 pr-4">{{ offering.min_shares }}–{{ offering.max_shares }}</td>
              <td class="py-2 pr-4 text-gray-600">
                {{ offering.opens_at?.slice(0, 10) || 'anytime' }} → {{ offering.closes_at?.slice(0, 10) || 'open-ended' }}
              </td>
              <td class="py-2 pr-4">{{ offering.applications_count }}</td>
              <td class="py-2 pr-4">
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusBadge(offering.status)">{{ offering.status }}</span>
              </td>
              <td class="py-2 text-right">
                <button v-if="offering.status !== 'open'" class="text-xs text-green-700 underline" @click="setOfferingStatus(offering, 'open')">Open</button>
                <button v-else class="text-xs text-amber-700 underline" @click="setOfferingStatus(offering, 'closed')">Close</button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="mt-3 text-sm text-gray-500">No offerings yet.</p>
      </div>
    </div>
  </PanelLayout>
</template>
