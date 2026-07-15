<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
  applications: Object,
  summary: Object,
  filters: Object,
  options: Object,
});

const form = reactive({ ...props.filters });

const offeringsForCompany = computed(() =>
  form.company_id
    ? props.options.offerings.filter((offering) => offering.company_id === Number(form.company_id))
    : props.options.offerings,
);

const activeFilters = () =>
  Object.fromEntries(Object.entries(form).filter(([, value]) => value !== null && value !== ''));

const apply = () => router.get(route('admin.reports'), activeFilters(), { preserveState: true, preserveScroll: true });

const clear = () => {
  Object.keys(form).forEach((key) => (form[key] = null));
  apply();
};

const exportUrl = (format) => {
  const params = new URLSearchParams({ ...activeFilters(), format });

  return `${route('admin.reports.export')}?${params.toString()}`;
};

const money = (value) => Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
</script>

<template>
  <Head title="Admin - Reports" />
  <PanelLayout>
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-900">Reports</h2>
        <div class="flex gap-2">
          <a :href="exportUrl('xlsx')" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Excel</a>
          <a :href="exportUrl('csv')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">CSV</a>
          <a :href="exportUrl('pdf')" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">PDF</a>
        </div>
      </div>

      <div class="bg-white p-5 rounded-lg shadow">
        <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-6">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Company</label>
            <select v-model="form.company_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option :value="null">All companies</option>
              <option v-for="company in options.companies" :key="company.id" :value="company.id">{{ company.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Offering</label>
            <select v-model="form.share_offering_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option :value="null">All offerings</option>
              <option v-for="offering in offeringsForCompany" :key="offering.id" :value="offering.id">
                {{ offering.title }} ({{ offering.fiscal_year }})
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Status</label>
            <select v-model="form.status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option :value="null">All statuses</option>
              <option v-for="status in options.statuses" :key="status" :value="status">{{ status }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Payment Method</label>
            <select v-model="form.payment_method_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option :value="null">Any</option>
              <option v-for="method in options.paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">From</label>
            <input v-model="form.date_from" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">To</label>
            <input v-model="form.date_to" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
        </div>
        <div class="mt-4 flex gap-2">
          <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white" @click="apply">Apply Filters</button>
          <button class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="clear">Clear</button>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-5">
        <div class="bg-white p-4 rounded-lg shadow">
          <div class="text-xs uppercase text-gray-500">Applications</div>
          <div class="mt-1 text-2xl font-bold text-gray-900">{{ summary.totalApplications.toLocaleString() }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
          <div class="text-xs uppercase text-gray-500">Shares Applied</div>
          <div class="mt-1 text-2xl font-bold text-gray-900">{{ summary.totalShares.toLocaleString() }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
          <div class="text-xs uppercase text-gray-500">Total Declared</div>
          <div class="mt-1 text-2xl font-bold text-gray-900">Rs. {{ money(summary.totalDeclared) }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
          <div class="text-xs uppercase text-gray-500">Verified Payments</div>
          <div class="mt-1 text-2xl font-bold text-emerald-700">Rs. {{ money(summary.totalVerifiedPayments) }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
          <div class="text-xs uppercase text-gray-500">Shares Allotted</div>
          <div class="mt-1 text-2xl font-bold text-gray-900">{{ summary.totalAllotted.toLocaleString() }}</div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 uppercase border-b">
              <th class="px-4 py-3">Application</th>
              <th class="px-4 py-3">Applicant</th>
              <th class="px-4 py-3">Company / Offering</th>
              <th class="px-4 py-3">Shares</th>
              <th class="px-4 py-3">Total</th>
              <th class="px-4 py-3">Verified</th>
              <th class="px-4 py-3">Allotted</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="application in applications.data" :key="application.id">
              <td class="px-4 py-3 font-medium">{{ application.application_number }}</td>
              <td class="px-4 py-3">{{ application.applicant?.full_name_english }}</td>
              <td class="px-4 py-3 text-gray-600">
                <template v-if="application.offering">
                  {{ application.offering.company?.name }} / {{ application.offering.title }}
                </template>
                <template v-else>—</template>
              </td>
              <td class="px-4 py-3">{{ application.shares_applied }}</td>
              <td class="px-4 py-3">Rs. {{ money(application.total_amount_declared) }}</td>
              <td class="px-4 py-3">Rs. {{ money(application.verified_amount) }}</td>
              <td class="px-4 py-3">{{ application.allotment?.shares_allotted ?? 0 }}</td>
              <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ application.status }}</span></td>
              <td class="px-4 py-3 text-gray-600">{{ application.created_at?.slice(0, 10) }}</td>
            </tr>
            <tr v-if="!applications.data.length">
              <td colspan="9" class="px-4 py-6 text-center text-sm text-gray-500">No applications match the selected filters.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="applications.links?.length > 3" class="flex flex-wrap gap-1">
        <template v-for="link in applications.links" :key="link.label">
          <Link
            v-if="link.url"
            :href="link.url"
            class="rounded border px-3 py-1.5 text-sm"
            :class="link.active ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-700'"
            v-html="link.label"
          />
          <span v-else class="rounded border border-gray-200 px-3 py-1.5 text-sm text-gray-400" v-html="link.label" />
        </template>
      </div>
    </div>
  </PanelLayout>
</template>
