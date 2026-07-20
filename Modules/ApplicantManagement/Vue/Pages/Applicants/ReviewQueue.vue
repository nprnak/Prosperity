<script setup>
import Pagination from '@/Components/Pagination.vue';
import StageActions from '@/Components/StageActions.vue';
import WorkflowTimeline from '@/Components/WorkflowTimeline.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    // A Laravel paginator: { data, links, from, to, total }.
    pending: { type: Object, default: () => ({ data: [], links: [] }) },
    // Paged under its own `decided` query parameter.
    recentlyReviewed: { type: Object, default: () => ({ data: [], links: [] }) },
});

// The engine records who acted; the profile columns no longer carry it.
const lastAction = (applicant) => applicant.workflow_events?.[0] ?? null;

const formatWhen = (value) => (value
    ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
    : '—');
</script>

<template>
    <Head title="KYC Review Queue" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">KYC Review</h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-8 px-4 py-8">
            <section>
                <h3 class="font-semibold text-gray-900">Waiting on you ({{ pending.total }})</h3>
                <p class="mt-1 max-w-[70ch] text-sm text-gray-700">
                    Each profile shows the stage it is waiting for. A profile needs three different
                    people across verification, review and approval, so anything you have already
                    acted on at another stage is hidden.
                </p>

                <p
                    v-if="!pending.data.length"
                    class="mt-4 max-w-[70ch] rounded-xl bg-white p-8 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200"
                >
                    Nothing waiting on you. Profiles appear here once an applicant submits their KYC
                    for review, or when a later stage sends one back.
                </p>

                <article
                    v-for="applicant in pending.data"
                    :key="applicant.id"
                    class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="font-semibold text-gray-900">
                                {{ applicant.full_name_en }}
                                <span class="font-normal text-gray-700">· {{ applicant.mobile }}</span>
                            </h4>
                            <p class="mt-1 text-sm text-gray-700">
                                Citizenship {{ applicant.citizenship_number || '—' }} ·
                                BOID {{ applicant.boid || '—' }} ·
                                {{ applicant.bank_name || '—' }} ({{ applicant.bank_account_number || '—' }})
                            </p>
                            <p class="mt-1 text-xs text-gray-600">
                                Submitted {{ formatWhen(applicant.profile_submitted_at) }}
                            </p>
                        </div>

                        <div class="flex flex-col items-end gap-1.5">
                            <p class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-900 ring-1 ring-blue-200">
                                Awaiting {{ applicant.pending_stage_label }}
                            </p>
                            <p
                                v-if="applicant.workflow_cycle > 1"
                                class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200"
                            >
                                Corrected · submission {{ applicant.workflow_cycle }}
                            </p>
                        </div>
                    </div>

                    <StageActions
                        class="mt-4"
                        :action-url="route('applicants.profile.act', applicant.id)"
                        :can-send-back="applicant.can_send_back"
                    />

                    <details class="mt-4 border-t border-gray-100 pt-3">
                        <summary class="cursor-pointer text-sm font-medium text-gray-700 hover:text-gray-900">
                            Review trail
                        </summary>
                        <div class="mt-3">
                            <WorkflowTimeline :events="applicant.workflow_events ?? []" />
                        </div>
                    </details>
                </article>

                <Pagination :meta="pending" label="profiles" />
            </section>

            <section>
                <h3 class="font-semibold text-gray-900">Recently decided</h3>

                <p v-if="!recentlyReviewed.data.length" class="mt-2 text-sm text-gray-700">
                    Nothing decided yet.
                </p>

                <div v-else class="mt-3 overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-700">
                                <th scope="col" class="px-4 py-2.5">Applicant</th>
                                <th scope="col" class="px-4 py-2.5">Outcome</th>
                                <th scope="col" class="px-4 py-2.5">Last action by</th>
                                <th scope="col" class="px-4 py-2.5">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="applicant in recentlyReviewed.data" :key="applicant.id">
                                <td class="px-4 py-2.5 text-gray-900">{{ applicant.full_name_en }}</td>
                                <td class="px-4 py-2.5">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="applicant.profile_status === 'approved'
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-amber-100 text-amber-900'"
                                    >{{ applicant.profile_status_label }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-gray-800">
                                    {{ lastAction(applicant)?.actor?.name ?? '—' }}
                                    <span v-if="lastAction(applicant)" class="text-gray-600">
                                        ({{ lastAction(applicant).stage_label }})
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-gray-800">
                                    {{ formatWhen(lastAction(applicant)?.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :meta="recentlyReviewed" label="decisions" />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
