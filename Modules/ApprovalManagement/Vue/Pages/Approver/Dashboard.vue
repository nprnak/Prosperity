<script setup>
import Pagination from '@/Components/Pagination.vue';
import QueueToolbar from '@/Components/QueueToolbar.vue';
import StageActions from '@/Components/StageActions.vue';
import WorkflowTimeline from '@/Components/WorkflowTimeline.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

// A Laravel paginator: { data, links, from, to, total }.
const props = defineProps({
    applications: { type: Object, default: () => ({ data: [], links: [] }) },
    viewedApplicationIds: { type: Array, default: () => [] },
    approvedByMe: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({ q: null }) },
});

const activeTab = ref('pending');
const search = ref(props.filters?.q || '');

const tabs = [
    { key: 'pending', label: 'Pending', get count() { return props.applications.total; } },
    { key: 'approved', label: 'Approved by you', get count() { return props.approvedByMe.total; } },
];

const applySearch = () => {
    router.get(route('approver.dashboard'), { q: search.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const issuedVoucher = (application) =>
    (application.payment_transactions || []).map((payment) => payment.voucher).find((voucher) => !!voucher) || null;

const hasViewedForm = (application, viewedApplicationIds) => viewedApplicationIds.includes(application.id);
</script>

<template>
    <Head title="Approval Queue" />

    <PanelLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Approval Queue</h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Application approval</h3>
                <p class="mt-1 max-w-[70ch] text-sm text-gray-700">
                    Reviewed applications awaiting final sign-off. Approving issues the voucher and
                    notifies the applicant, so it is the last reversible moment.
                </p>
                <p class="mt-1 max-w-[70ch] text-xs text-amber-800">
                    Before clicking Approve &amp; Issue Voucher, open the application form once using View Application Form.
                </p>
            </div>

            <QueueToolbar
                v-model="activeTab"
                v-model:search="search"
                :tabs="tabs"
                @search="applySearch"
            />

            <section v-show="activeTab === 'pending'" class="space-y-4">
                <p
                    v-if="!applications.data.length"
                    class="max-w-[70ch] rounded-xl bg-white p-8 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200"
                >
                    Nothing waiting on you. Applications appear here once a reviewer signs them off.
                    Any you have already acted on at another stage are hidden, since each stage needs a different person.
                </p>

                <article
                    v-for="app in applications.data"
                    :key="app.id"
                    class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">
                                {{ app.application_number }}
                                <span class="font-normal text-gray-700">· {{ app.applicant?.full_name_en }}</span>
                            </h3>
                            <p class="mt-1 text-sm text-gray-700">
                                {{ app.shares_applied }} shares ·
                                {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ app.total_amount_declared }}
                            </p>
                        </div>

                        <p
                            v-if="app.workflow_cycle > 1"
                            class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200"
                        >
                            Corrected · submission {{ app.workflow_cycle }}
                        </p>
                    </div>

                    <StageActions
                        v-if="hasViewedForm(app, viewedApplicationIds)"
                        class="mt-4"
                        :action-url="route('approver.applications.act', app.id)"
                        :can-send-back="app.can_send_back"
                        approve-label="Approve &amp; Issue Voucher"
                    />

                    <p
                        v-else
                        class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900"
                    >
                        View Application Form first, then Approve &amp; Issue Voucher becomes available.
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                        <Link
                            :href="route('admin.applications.show', app.id)"
                            class="font-medium text-blue-700 hover:text-blue-900 hover:underline"
                        >
                            View Application Form
                        </Link>
                        <a
                            v-if="issuedVoucher(app)"
                            :href="route('vouchers.verify', { code: issuedVoucher(app).verification_code })"
                            target="_blank"
                            rel="noopener"
                            class="font-medium text-emerald-700 hover:text-emerald-900 hover:underline"
                        >
                            Verify Voucher
                        </a>
                    </div>

                    <details class="mt-4 border-t border-gray-100 pt-3">
                        <summary class="cursor-pointer text-sm font-medium text-gray-700 hover:text-gray-900">
                            Review trail
                        </summary>
                        <div class="mt-3">
                            <WorkflowTimeline :events="app.workflow_events ?? []" />
                        </div>
                    </details>
                </article>

                <Pagination :meta="applications" label="applications" />
            </section>

            <section v-show="activeTab === 'approved'" class="space-y-4">
                <p
                    v-if="!approvedByMe.data.length"
                    class="max-w-[70ch] rounded-xl bg-white p-6 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200"
                >
                    You have not approved any applications yet.
                </p>

                <article
                    v-for="app in approvedByMe.data"
                    :key="`approved-${app.id}`"
                    class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-emerald-200"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">
                                {{ app.application_number }}
                                <span class="font-normal text-gray-700">· {{ app.applicant?.full_name_en }}</span>
                            </h3>
                            <p class="mt-1 text-sm text-gray-700">
                                {{ app.shares_applied }} shares ·
                                {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ app.total_amount_declared }}
                            </p>
                        </div>

                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">
                            {{ app.status_label || app.status }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                        <Link
                            :href="route('admin.applications.show', app.id)"
                            class="font-medium text-blue-700 hover:text-blue-900 hover:underline"
                        >
                            View Application Form
                        </Link>
                        <a
                            v-if="issuedVoucher(app)"
                            :href="route('vouchers.verify', { code: issuedVoucher(app).verification_code })"
                            target="_blank"
                            rel="noopener"
                            class="font-medium text-emerald-700 hover:text-emerald-900 hover:underline"
                        >
                            Verify Voucher
                        </a>
                    </div>

                    <details class="mt-4 border-t border-gray-100 pt-3">
                        <summary class="cursor-pointer text-sm font-medium text-gray-700 hover:text-gray-900">
                            Review trail
                        </summary>
                        <div class="mt-3">
                            <WorkflowTimeline :events="app.workflow_events ?? []" />
                        </div>
                    </details>
                </article>

                <Pagination :meta="approvedByMe" label="approved applications" />
            </section>
        </div>
    </PanelLayout>
</template>
