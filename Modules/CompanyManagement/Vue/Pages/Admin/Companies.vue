<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ companies: Array, offeringStatuses: Array });

const blankCompany = {
    name: '', name_np: '', code: '', description: '', address: '', address_np: '',
    bank_name: '', bank_account_number: '', logo: null, status: 'active',
};

const companyForm = useForm({ ...blankCompany });
const editingCompanyId = ref(null);

const startEditCompany = (company) => {
    editingCompanyId.value = company.id;
    Object.assign(companyForm, {
        name: company.name,
        name_np: company.name_np || '',
        code: company.code,
        description: company.description || '',
        address: company.address || '',
        address_np: company.address_np || '',
        bank_name: company.bank_name || '',
        bank_account_number: company.bank_account_number || '',
        logo: null,
        status: company.status,
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelCompanyEdit = () => {
    editingCompanyId.value = null;
    Object.assign(companyForm, { ...blankCompany });
    companyForm.clearErrors();
};

const saveCompany = () => {
    const options = {
        forceFormData: true,
        onSuccess: () => {
            cancelCompanyEdit();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    };
    if (editingCompanyId.value) {
        companyForm.post(route('admin.companies.update', editingCompanyId.value), options);
    } else {
        companyForm.post(route('admin.companies.store'), options);
    }
};

const blankOffering = {
    title: '', fiscal_year: '', total_shares: 100000, share_rate: 100,
    min_shares: 10, max_shares: 1000, opens_at: '', closes_at: '', status: 'draft',
};

const offeringTarget = ref(null);
const editingOfferingId = ref(null);
const offeringForm = useForm({ ...blankOffering });

const startEditOffering = (company, offering) => {
    offeringTarget.value = company.id;
    editingOfferingId.value = offering.id;
    Object.assign(offeringForm, {
        title: offering.title,
        fiscal_year: offering.fiscal_year,
        total_shares: offering.total_shares,
        share_rate: offering.share_rate,
        min_shares: offering.min_shares,
        max_shares: offering.max_shares,
        opens_at: offering.opens_at?.slice(0, 10) || '',
        closes_at: offering.closes_at?.slice(0, 10) || '',
        status: offering.status,
    });
};

const cancelOfferingEdit = () => {
    offeringTarget.value = null;
    editingOfferingId.value = null;
    Object.assign(offeringForm, { ...blankOffering });
    offeringForm.clearErrors();
};

const toggleNewOffering = (company) => {
    if (offeringTarget.value === company.id && !editingOfferingId.value) {
        cancelOfferingEdit();
    } else {
        cancelOfferingEdit();
        offeringTarget.value = company.id;
    }
};

const saveOffering = () => {
    const options = { preserveScroll: true, onSuccess: cancelOfferingEdit };
    if (editingOfferingId.value) {
        offeringForm.patch(route('admin.offerings.update', editingOfferingId.value), options);
    } else {
        offeringForm.post(route('admin.offerings.store', offeringTarget.value), options);
    }
};

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

      <div class="bg-white p-5 rounded-lg shadow">
        <h3 class="font-semibold text-base mb-4">{{ editingCompanyId ? 'Edit company' : 'Add company' }}</h3>
        <div class="grid gap-4 md:grid-cols-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Name *</label>
            <input v-model="companyForm.name" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="companyForm.errors.name" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Name (Nepali)</label>
            <input v-model="companyForm.name_np" placeholder="e.g. प्रोस्पेरिटी होल्डिङ्स लिमिटेड" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="companyForm.errors.name_np" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Code *</label>
            <input v-model="companyForm.code" placeholder="e.g. PHL" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="companyForm.errors.code" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Address</label>
            <input v-model="companyForm.address" placeholder="e.g. Kathmandu-11" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="companyForm.errors.address" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Address (Nepali)</label>
            <input v-model="companyForm.address_np" placeholder="e.g. का.म.न.पा.- ११, काठमाडौँ।" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="companyForm.errors.address_np" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Bank Name</label>
            <input v-model="companyForm.bank_name" placeholder="e.g. Nepal Bank Limited" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="companyForm.errors.bank_name" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Bank Account Number</label>
            <input v-model="companyForm.bank_account_number" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="companyForm.errors.bank_account_number" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Company Logo</label>
            <input type="file" accept="image/*" class="w-full text-sm" @change="companyForm.logo = $event.target.files[0]" />
            <InputError :message="companyForm.errors.logo" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
            <select v-model="companyForm.status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="flex items-end gap-2">
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" :disabled="companyForm.processing" @click="saveCompany">
              {{ editingCompanyId ? 'Update Company' : 'Add Company' }}
            </button>
            <button v-if="editingCompanyId" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="cancelCompanyEdit">Cancel</button>
          </div>
        </div>
      </div>

      <div v-for="company in companies" :key="company.id" class="bg-white p-5 rounded-lg shadow">
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-start gap-3">
            <img
              v-if="company.logo_path"
              :src="route('companies.logo', company.id)"
              :alt="`${company.name} logo`"
              class="h-12 w-12 rounded object-contain border border-gray-100"
            />
            <div>
              <h3 class="font-semibold text-base">{{ company.name }} <span class="text-gray-400 font-normal">({{ company.code }})</span></h3>
              <p v-if="company.address" class="text-sm text-gray-600">{{ company.address }}</p>
              <p v-if="company.bank_name || company.bank_account_number" class="text-sm text-gray-600">
                {{ company.bank_name }}<span v-if="company.bank_account_number"> · A/C {{ company.bank_account_number }}</span>
              </p>
              <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="company.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                {{ company.status }}
              </span>
            </div>
          </div>
          <div class="flex gap-2">
            <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700" @click="startEditCompany(company)">
              Edit
            </button>
            <button class="rounded-lg border border-blue-300 px-3 py-1.5 text-sm text-blue-700" @click="toggleNewOffering(company)">
              {{ offeringTarget === company.id && !editingOfferingId ? 'Cancel' : 'New offering' }}
            </button>
          </div>
        </div>

        <div v-if="offeringTarget === company.id" class="mt-4 rounded-lg border border-blue-100 bg-blue-50/50 p-4">
          <h4 class="mb-3 text-sm font-semibold text-blue-900">{{ editingOfferingId ? 'Edit offering' : 'New offering' }}</h4>
          <div class="grid gap-3 md:grid-cols-4">
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
            <div class="flex items-end gap-2">
              <button class="rounded bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white" :disabled="offeringForm.processing" @click="saveOffering">
                {{ editingOfferingId ? 'Update Offering' : 'Save Offering' }}
              </button>
              <button v-if="editingOfferingId" class="rounded border border-gray-300 px-3 py-1.5 text-sm" @click="cancelOfferingEdit">Cancel</button>
            </div>
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
              <td class="py-2 text-right whitespace-nowrap">
                <button class="text-xs text-blue-700 underline mr-3" @click="startEditOffering(company, offering)">Edit</button>
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
