<script setup>
import Pagination from '@/Components/Pagination.vue';
import StageActions from '@/Components/StageActions.vue';
import WorkflowTimeline from '@/Components/WorkflowTimeline.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// A Laravel paginator: { data, links, from, to, total }.
defineProps({
    pendingApplications: { type: Object, default: () => ({ data: [], links: [] }) },
    viewedApplicationIds: { type: Array, default: () => [] },
    verifiedByMe: { type: Object, default: () => ({ data: [], links: [] }) },
});

const issuedVoucher = (application) =>
    (application.payment_transactions || []).map((payment) => payment.voucher).find((voucher) => !!voucher) || null;

const hasViewedForm = (application, viewedApplicationIds) => viewedApplicationIds.includes(application.id);
</script>

<template>
    <Head title="Verification Queue" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Verification Queue</h2>
        </template>

        <div class="max-w-5xl px-4 py-8 mx-auto space-y-4">
            <p class="max-w-[70ch] text-sm text-gray-700">
                Submitted applications awaiting the first of three sign-offs. What you verify
                goes to a reviewer, then an approver — three different people are always required.
            </p>
            <p class="max-w-[70ch] text-xs text-amber-800">
                Before clicking Mark Verified, open the application form once using View Application Form.
            </p>

            <h3 class="text-sm font-semibold tracking-wide text-gray-700 uppercase">Pending Applications</h3>

            <article
                v-for="app in pendingApplications.data"
                :key="app.id"
                class="p-5 bg-white shadow-sm rounded-xl ring-1 ring-gray-200"
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
                    :action-url="route('verifier.applications.act', app.id)"
                    :can-send-back="app.can_send_back"
                    approve-label="Mark Verified"
                />

                <p
                    v-else
                    class="px-3 py-2 mt-4 text-xs font-medium border rounded-lg border-amber-200 bg-amber-50 text-amber-900"
                >
                    View Application Form first, then Mark Verified becomes available.
                </p>

                <div class="flex flex-wrap items-center gap-3 mt-3 text-sm">
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

                <details class="pt-3 mt-4 border-t border-gray-100">
                    <summary class="text-sm font-medium text-gray-700 cursor-pointer hover:text-gray-900">
                        Review trail
                    </summary>
                    <div class="mt-3">
                        <WorkflowTimeline :events="app.workflow_events ?? []" />
                    </div>
                </details>
            </article>

            <p
                v-if="!pendingApplications.data.length"
                class="max-w-[70ch] rounded-xl bg-white p-8 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200"
            >
                Nothing waiting on you. Applications appear here once an applicant submits them.
                Any you have already acted on at another stage are hidden, since each stage needs a different person.
            </p>

            <Pagination :meta="pendingApplications" label="applications" />

            <h3 class="pt-4 text-sm font-semibold tracking-wide text-gray-700 uppercase">Applications Verified By You</h3>

            <article
                v-for="app in verifiedByMe.data"
                :key="`verified-${app.id}`"
                class="p-5 bg-white shadow-sm rounded-xl ring-1 ring-emerald-200"
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

                <details class="pt-3 mt-4 border-t border-gray-100">
                    <summary class="text-sm font-medium text-gray-700 cursor-pointer hover:text-gray-900">
                        Review trail
                    </summary>
                    <div class="mt-3">
                        <WorkflowTimeline :events="app.workflow_events ?? []" />
                    </div>
                </details>
            </article>

            <p
                v-if="!verifiedByMe.data.length"
                class="max-w-[70ch] rounded-xl bg-white p-6 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200"
            >
                You have not verified any applications yet.
            </p>

            <Pagination :meta="verifiedByMe" label="verified applications" />
        </div>
    </AuthenticatedLayout>
</template>
