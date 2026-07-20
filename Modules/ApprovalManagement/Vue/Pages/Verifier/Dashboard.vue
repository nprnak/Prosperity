<script setup>
import Pagination from '@/Components/Pagination.vue';
import StageActions from '@/Components/StageActions.vue';
import WorkflowTimeline from '@/Components/WorkflowTimeline.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

// A Laravel paginator: { data, links, from, to, total }.
defineProps({ applications: { type: Object, default: () => ({ data: [], links: [] }) } });
</script>

<template>
    <Head title="Verification Queue" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Verification Queue</h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-4 px-4 py-8">
            <p class="max-w-[70ch] text-sm text-gray-700">
                Payment-verified applications awaiting the first of three sign-offs. What you verify
                goes to a reviewer, then an approver — three different people are always required.
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
                    class="mt-4"
                    :action-url="route('verifier.applications.act', app.id)"
                    :can-send-back="app.can_send_back"
                    approve-label="Mark Verified"
                />

                <details class="mt-4 border-t border-gray-100 pt-3">
                    <summary class="cursor-pointer text-sm font-medium text-gray-700 hover:text-gray-900">
                        Review trail
                    </summary>
                    <div class="mt-3">
                        <WorkflowTimeline :events="app.workflow_events ?? []" />
                    </div>
                </details>
            </article>

            <p
                v-if="!applications.data.length"
                class="max-w-[70ch] rounded-xl bg-white p-8 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200"
            >
                Nothing waiting on you. Applications appear here once finance verifies the payment.
                Any you have already acted on at another stage are hidden, since each stage needs a different person.
            </p>

            <Pagination :meta="applications" label="applications" />
        </div>
    </AuthenticatedLayout>
</template>
