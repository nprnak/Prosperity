<script setup>
import Pagination from '@/Components/Pagination.vue';
import QueueToolbar from '@/Components/QueueToolbar.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PlusIcon, EyeIcon, DocumentTextIcon, ShieldCheckIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const canAddApplication = (page.props.auth?.permissions || []).includes('application.verify');

// A Laravel paginator: { data, links, from, to, total }.
const props = defineProps({
    pending: { type: Object, default: () => ({ data: [], links: [] }) },
    decided: { type: Object, default: () => ({ data: [], links: [] }) },
    // Paper applications filed by this user themselves — empty for anyone
    // who isn't an Application Verifier.
    enteredByMe: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({ q: null }) },
});

const activeTab = ref('pending');
const search = ref(props.filters?.q || '');

const tabs = [
    { key: 'pending', label: 'Waiting on you', get count() { return props.pending.total; } },
    { key: 'decided', label: 'Recently decided', get count() { return props.decided.total; } },
    { key: 'entered', label: 'My filed applications', get count() { return props.enteredByMe.total; } },
];

const applySearch = () => {
    router.get(route('applications.review'), { q: search.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// A paper application this verifier filed themselves reopens in the same
// wizard while it is still a draft, or once a later stage has sent it back
// for correction — anything past that is the review chain's to decide on.
// ?resume=1 marks this as an explicit "go fix it" click, so the wizard skips
// straight to the pre-filled form instead of its confirmation notice.
const editUrl = (application) => (
  ['draft', 'returned'].includes(application.status) && application.applicant?.user_id
    ? `${route('applications.add.create', application.applicant.user_id)}?resume=1`
    : null
);

const issuedVoucher = (application) =>
    (application.payment_transactions || []).map((payment) => payment.voucher).find((voucher) => !!voucher) || null;

const statusClass = (status) => {
    if (['submitted', 'sent_to_bank', 'bank_accepted', 'blocked', 'payment_pending', 'verified', 'reviewed'].includes(status)) {
        return 'bg-yellow-100 text-yellow-700';
    }

    if (['payment_verified', 'approved', 'allotted', 'demat_credited'].includes(status)) {
        return 'bg-green-100 text-green-700';
    }

    if (['rejected', 'not_allotted'].includes(status)) {
        return 'bg-red-100 text-red-700';
    }

    if (status === 'returned') {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <Head title="Application Review" />

    <PanelLayout>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Application Review</h2>
                    <p class="mt-1 text-sm text-gray-700">
                        Each application shows the stage it is waiting for. An application needs three
                        different people across verification, review and approval, so anything you have
                        already acted on at another stage is hidden.
                    </p>
                </div>
                <Link
                    v-if="canAddApplication"
                    :href="route('applications.add.pick')"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-brand-600"
                >
                    <PlusIcon class="h-4 w-4" /> Add Application
                </Link>
            </div>

            <QueueToolbar
                v-model="activeTab"
                v-model:search="search"
                :tabs="tabs"
                @search="applySearch"
            />

            <!-- Waiting on you -->
            <div v-show="activeTab === 'pending'" class="mt-4 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shares</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="app in pending.data" :key="app.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                {{ app.application_number }}
                                <span v-if="app.workflow_cycle > 1" class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">
                                    Corrected
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ app.applicant?.full_name_en }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ app.shares_applied }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ app.total_amount_declared }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                    Awaiting {{ app.pending_stage_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <Link :href="route('admin.applications.show', app.id)" class="text-indigo-600 hover:text-indigo-900" title="View &amp; act">
                                        <EyeIcon class="h-4 w-4" />
                                    </Link>
                                    <Link :href="route('applications.show', app.id)" class="text-slate-600 hover:text-slate-900" title="Printed Form">
                                        <DocumentTextIcon class="h-4 w-4" />
                                    </Link>
                                    <a
                                        v-if="issuedVoucher(app)"
                                        :href="route('vouchers.verify', { code: issuedVoucher(app).verification_code })"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-emerald-600 hover:text-emerald-900"
                                        title="Verify Voucher"
                                    >
                                        <ShieldCheckIcon class="h-4 w-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!pending.data.length" class="text-center py-12">
                    <p class="text-gray-500">Nothing waiting on you. Applications appear here once an applicant submits one, or when a later stage sends one back.</p>
                </div>

                <div class="mt-4"><Pagination :meta="pending" label="applications" /></div>
            </div>

            <!-- Recently decided -->
            <div v-show="activeTab === 'decided'" class="mt-4 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shares</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="app in decided.data" :key="`decided-${app.id}`" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ app.application_number }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ app.applicant?.full_name_en }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ app.shares_applied }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ app.total_amount_declared }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span :class="statusClass(app.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ app.status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <Link :href="route('admin.applications.show', app.id)" class="text-indigo-600 hover:text-indigo-900" title="View">
                                        <EyeIcon class="h-4 w-4" />
                                    </Link>
                                    <Link :href="route('applications.show', app.id)" class="text-slate-600 hover:text-slate-900" title="Printed Form">
                                        <DocumentTextIcon class="h-4 w-4" />
                                    </Link>
                                    <a
                                        v-if="issuedVoucher(app)"
                                        :href="route('vouchers.verify', { code: issuedVoucher(app).verification_code })"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-emerald-600 hover:text-emerald-900"
                                        title="Verify Voucher"
                                    >
                                        <ShieldCheckIcon class="h-4 w-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!decided.data.length" class="text-center py-12">
                    <p class="text-gray-500">You have not decided any applications yet.</p>
                </div>

                <div class="mt-4"><Pagination :meta="decided" label="decisions" /></div>
            </div>

            <!-- My filed applications -->
            <div v-show="activeTab === 'entered'" class="mt-4 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shares</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="app in enteredByMe.data" :key="`entered-${app.id}`" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ app.application_number }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ app.applicant?.full_name_en }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ app.shares_applied }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ app.total_amount_declared }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span :class="statusClass(app.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ app.status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <Link :href="route('admin.applications.show', app.id)" class="text-indigo-600 hover:text-indigo-900" title="View">
                                        <EyeIcon class="h-4 w-4" />
                                    </Link>
                                    <Link
                                        v-if="editUrl(app)"
                                        :href="editUrl(app)"
                                        class="text-amber-600 hover:text-amber-800"
                                        :title="app.status === 'returned' ? 'Edit and resubmit' : 'Continue draft'"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!enteredByMe.data.length" class="text-center py-12">
                    <p class="text-gray-500">You have not filed any paper applications yet.</p>
                </div>

                <div class="mt-4"><Pagination :meta="enteredByMe" label="filed applications" /></div>
            </div>
        </div>
    </PanelLayout>
</template>
