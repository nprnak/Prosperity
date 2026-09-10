<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import CapitalRaisedChart from '@/Components/Charts/CapitalRaisedChart.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
  BanknotesIcon,
  UserGroupIcon,
  ChartBarIcon,
  BuildingOffice2Icon,
  ClockIcon,
  ClipboardDocumentListIcon,
  FunnelIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  metrics: { type: Object, default: () => ({}) },
  capitalSeries: { type: Array, default: () => [] },
  // Every offering's subscription progress — open, upcoming and recently
  // closed together — so staff can see which issue needs attention.
  offerings: { type: Array, default: () => [] },
  // Non-draft applications grouped by status, for the funnel chart.
  statusBreakdown: { type: Array, default: () => [] },
  recentApplications: { type: Array, default: () => [] },
  totalCompanies: { type: Number, default: 0 },
  // Top focal persons by shares credited; empty for staff without report.view.
  focalPersons: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({ companies: [], offerings: [] }) },
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency_symbol || 'Rs.');

const formatNumber = (value) => Number(value || 0).toLocaleString('en-IN');

// Every angle a decision-maker might want to slice the same figures by: one
// company, one issue within it, or a date window — sent as query params so
// the view is a shareable/bookmarkable URL, the same convention the report
// screen uses.
const form = ref({
  company_id: props.filters.company_id ?? null,
  share_offering_id: props.filters.share_offering_id ?? null,
  date_from: props.filters.date_from ?? '',
  date_to: props.filters.date_to ?? '',
});

const offeringOptions = computed(() => props.filterOptions.offerings.filter(
  (offering) => !form.value.company_id || String(offering.company_id) === String(form.value.company_id),
));

// Switching company strands a picked offering from a different one.
watch(() => form.value.company_id, () => {
  if (form.value.share_offering_id && !offeringOptions.value.some((o) => String(o.id) === String(form.value.share_offering_id))) {
    form.value.share_offering_id = null;
  }
});

const hasActiveFilters = computed(() => Boolean(
  form.value.company_id || form.value.share_offering_id || form.value.date_from || form.value.date_to,
));

const applyFilters = () => {
  const params = {};
  Object.entries(form.value).forEach(([key, value]) => {
    if (value !== null && value !== '') params[key] = value;
  });

  router.get(route('admin.dashboard'), params, { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
  form.value = { company_id: null, share_offering_id: null, date_from: '', date_to: '' };
  router.get(route('admin.dashboard'), {}, { preserveState: true, preserveScroll: true });
};

const kpiCards = computed(() => [
  {
    label: 'Capital Raised',
    value: `${currency.value} ${formatNumber(props.metrics.capitalRaised)}`,
    icon: BanknotesIcon,
    badgeClass: 'bg-emerald-50 text-emerald-700',
  },
  {
    label: 'Total Shareholders',
    value: formatNumber(props.metrics.totalShareholders),
    icon: UserGroupIcon,
    badgeClass: 'bg-blue-50 text-blue-700',
  },
  {
    label: 'Shares Allotted',
    value: formatNumber(props.metrics.totalSharesAllotted),
    icon: ChartBarIcon,
    badgeClass: 'bg-purple-50 text-purple-700',
  },
  {
    label: 'Active Offerings',
    value: formatNumber(props.metrics.activeOfferings),
    hint: `Across ${props.totalCompanies} compan${props.totalCompanies === 1 ? 'y' : 'ies'}`,
    icon: BuildingOffice2Icon,
    badgeClass: 'bg-amber-50 text-amber-700',
  },
  {
    label: 'Pending Applications',
    value: formatNumber(props.metrics.pendingApplications),
    icon: ClockIcon,
    badgeClass: 'bg-orange-50 text-orange-700',
  },
  {
    label: 'Total Applications',
    value: formatNumber(props.metrics.totalApplications),
    icon: ClipboardDocumentListIcon,
    badgeClass: 'bg-slate-100 text-slate-700',
  },
]);

// Bar width for the status funnel, relative to whichever status has the most
// applications right now — the busiest stage always reads as a full bar.
const maxStatusCount = computed(() => Math.max(1, ...props.statusBreakdown.map((row) => row.count)));
const statusBarWidth = (count) => `${Math.round((count / maxStatusCount.value) * 100)}%`;

const offeringStatusClass = (status) => ({
  open: 'bg-green-100 text-green-700',
  upcoming: 'bg-blue-100 text-blue-700',
  closed: 'bg-gray-100 text-gray-700',
  completed: 'bg-purple-100 text-purple-700',
  draft: 'bg-yellow-100 text-yellow-700',
}[status] || 'bg-gray-100 text-gray-700');

const offeringStatusLabel = (status) => ({
  open: 'Open',
  upcoming: 'Upcoming',
  closed: 'Closed',
  completed: 'Completed',
  draft: 'Draft',
}[status] || status);

// Same grouping Applications Management uses, so a status reads the same
// colour everywhere in the app.
const applicationStatusClass = (status) => {
  if (['submitted', 'sent_to_bank', 'bank_accepted', 'blocked', 'payment_pending'].includes(status)) {
    return 'bg-yellow-100 text-yellow-700';
  }
  if (['payment_verified', 'reviewed', 'verified', 'approved', 'allotted', 'demat_credited'].includes(status)) {
    return 'bg-green-100 text-green-700';
  }
  if (['partially_allotted', 'refund_initiated', 'refund_completed'].includes(status)) {
    return 'bg-blue-100 text-blue-700';
  }
  if (['rejected', 'not_allotted'].includes(status)) {
    return 'bg-red-100 text-red-700';
  }
  return 'bg-gray-100 text-gray-700';
};
</script>

<template>
  <Head title="Admin Dashboard" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Share Portfolio Dashboard</h2>
        <p class="mt-1 text-sm text-gray-700">Live status of every offering, application and deposit across the portfolio.</p>
      </div>

      <div class="rounded-lg bg-white p-5 shadow">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
          <FunnelIcon class="h-4 w-4 text-gray-500" /> Filter this view
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Company</label>
            <select v-model="form.company_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option :value="null">All companies</option>
              <option v-for="company in filterOptions.companies" :key="company.id" :value="company.id">
                {{ company.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">Offering</label>
            <select v-model="form.share_offering_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option :value="null">All offerings</option>
              <option v-for="offering in offeringOptions" :key="offering.id" :value="offering.id">
                {{ offering.title }}
              </option>
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
        <div class="mt-4 flex flex-wrap items-center gap-2">
          <button class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" @click="applyFilters">
            Apply
          </button>
          <button
            v-if="hasActiveFilters"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="clearFilters"
          >
            Clear filters
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div v-for="card in kpiCards" :key="card.label" class="rounded-lg bg-white p-5 shadow">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="card.badgeClass">
              <component :is="card.icon" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
              <p class="text-xs font-medium text-gray-700">{{ card.label }}</p>
              <p class="truncate text-lg font-bold text-gray-900">{{ card.value }}</p>
            </div>
          </div>
          <p v-if="card.hint" class="mt-2 text-xs text-gray-600">{{ card.hint }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-lg bg-white p-6 shadow lg:col-span-2">
          <h3 class="text-lg font-semibold text-gray-900">Capital Raised Over Time</h3>
          <p class="mb-4 text-xs text-gray-600">Verified deposits by day, across every offering.</p>
          <CapitalRaisedChart v-if="capitalSeries.length" :points="capitalSeries" />
          <p v-else class="py-16 text-center text-sm text-gray-500">No verified deposits yet.</p>
        </div>

        <div class="rounded-lg bg-white p-6 shadow">
          <h3 class="text-lg font-semibold text-gray-900">Application Status</h3>
          <p class="mb-4 text-xs text-gray-600">Where applications are sitting right now.</p>
          <div v-if="statusBreakdown.length" class="space-y-3">
            <div v-for="row in statusBreakdown" :key="row.status">
              <div class="mb-1 flex justify-between text-xs text-gray-700">
                <span>{{ row.label }}</span>
                <span class="font-semibold text-gray-900">{{ row.count }}</span>
              </div>
              <div class="h-2 rounded-full bg-gray-100">
                <div class="h-2 rounded-full bg-brand-600" :style="{ width: statusBarWidth(row.count) }" />
              </div>
            </div>
          </div>
          <p v-else class="py-16 text-center text-sm text-gray-500">No applications yet.</p>
        </div>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
          <h3 class="text-lg font-semibold text-gray-900">Offering-wise Subscription</h3>
          <Link :href="route('admin.companies')" class="text-xs font-semibold text-brand-700 hover:text-brand-900">
            Manage companies →
          </Link>
        </div>

        <div v-if="offerings.length" class="overflow-x-auto">
          <table class="w-full min-w-[860px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Offering</th>
                <th class="py-2 px-4 text-left">Status</th>
                <th class="py-2 px-4 text-right">Subscribed / Total</th>
                <th class="py-2 px-4 text-left">Progress</th>
                <th class="py-2 px-4 text-right">Remaining</th>
                <th class="py-2 pl-4 text-right">Closes On</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="offering in offerings" :key="offering.id">
                <td class="py-3 pr-4">
                  <div class="font-medium text-gray-900">{{ offering.title }}</div>
                  <div class="text-xs text-gray-600">{{ offering.company }}</div>
                </td>
                <td class="py-3 px-4">
                  <span :class="offeringStatusClass(offering.status)" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                    {{ offeringStatusLabel(offering.status) }}
                  </span>
                </td>
                <td class="py-3 px-4 text-right text-gray-900 whitespace-nowrap">
                  {{ formatNumber(offering.sharesSubscribed) }} / {{ formatNumber(offering.totalShares) }}
                </td>
                <td class="py-3 px-4">
                  <div class="flex items-center gap-2">
                    <div class="h-2 w-28 shrink-0 rounded-full bg-gray-100">
                      <div class="h-2 rounded-full bg-emerald-600" :style="{ width: offering.subscriptionPercent + '%' }" />
                    </div>
                    <span class="text-xs text-gray-600">{{ offering.subscriptionPercent }}%</span>
                  </div>
                </td>
                <td class="py-3 px-4 text-right text-gray-900 whitespace-nowrap">{{ formatNumber(offering.sharesRemaining) }}</td>
                <td class="py-3 pl-4 text-right text-gray-600 whitespace-nowrap">{{ offering.closesAt || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-8 text-center text-sm text-gray-500">No offerings have been created yet.</p>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-lg bg-white p-6 shadow">
          <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-lg font-semibold text-gray-900">Recent Applications</h3>
            <Link :href="route('admin.applications')" class="text-xs font-semibold text-brand-700 hover:text-brand-900">
              All applications →
            </Link>
          </div>
          <div v-if="recentApplications.length" class="divide-y">
            <div v-for="app in recentApplications" :key="app.id" class="flex items-center justify-between gap-3 py-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-gray-900">{{ app.applicant }}</p>
                <p class="truncate text-xs text-gray-600">{{ app.applicationNumber }} · {{ app.offering }}</p>
              </div>
              <div class="shrink-0 text-right">
                <p class="text-sm font-semibold text-gray-900">{{ currency }} {{ formatNumber(app.amount) }}</p>
                <span :class="applicationStatusClass(app.status)" class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold">
                  {{ app.statusLabel }}
                </span>
              </div>
            </div>
          </div>
          <p v-else class="py-8 text-center text-sm text-gray-500">No applications yet.</p>
        </div>

        <div class="rounded-lg bg-white p-6 shadow">
          <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-lg font-semibold text-gray-900">Top Focal Persons</h3>
            <Link v-if="focalPersons.length" :href="route('admin.reports.show', 'focal-persons')" class="text-xs font-semibold text-brand-700 hover:text-brand-900">
              Full report →
            </Link>
          </div>

          <table v-if="focalPersons.length" class="w-full text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 text-left">Focal Person</th>
                <th class="py-2 text-left">Code</th>
                <th class="py-2 text-right">Shares</th>
                <th class="py-2 text-right">Deposit</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="person in focalPersons" :key="person.name">
                <td class="py-2 text-gray-900">{{ person.name }}</td>
                <td class="py-2 font-mono text-gray-600">{{ person.code }}</td>
                <td class="py-2 text-right text-gray-900">{{ person.shares }}</td>
                <td class="py-2 text-right text-gray-900">{{ currency }} {{ person.amount }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="py-8 text-center text-sm text-gray-500">No focal-person activity yet.</p>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
