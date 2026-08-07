<script setup>
import Pagination from '@/Components/Pagination.vue';
import QueueToolbar from '@/Components/QueueToolbar.vue';
import WorkflowTimeline from '@/Components/WorkflowTimeline.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    // A Laravel paginator: { data, links, from, to, total }.
    pending: { type: Object, default: () => ({ data: [], links: [] }) },
    // Paged under its own `decided` query parameter.
    recentlyReviewed: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({ q: null }) },
});

const activeTab = ref('pending');
const search = ref(props.filters?.q || '');

const tabs = [
    { key: 'pending', label: 'Waiting on you', get count() { return props.pending.total; } },
    { key: 'decided', label: 'Recently decided', get count() { return props.recentlyReviewed.total; } },
];

const applySearch = () => {
    router.get(route('applicants.review'), { q: search.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// The engine records who acted; the profile columns no longer carry it.
const lastAction = (applicant) => applicant.workflow_events?.[0] ?? null;

const formatWhen = (value) => (value
    ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
    : '—');
</script>

<template>
    <Head title="KYC Review Queue" />

    <PanelLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">KYC Review</h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Profile review</h3>
                <p class="mt-1 max-w-[70ch] text-sm text-slate-600">
                    Each profile shows the stage it is waiting for. A profile needs three different
                    people across verification, review and approval, so anything you have already
                    acted on at another stage is hidden.
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
                    v-if="!pending.data.length"
                    class="rounded-xl bg-white p-8 text-sm text-slate-600 shadow-sm ring-1 ring-gray-200"
                >
                    Nothing waiting on you. Profiles appear here once an applicant submits their KYC
                    for review, or when a later stage sends one back.
                </p>

                <article
                    v-for="applicant in pending.data"
                    :key="applicant.id"
                    class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="font-semibold text-slate-900">
                                {{ applicant.full_name_en }}
                                <span class="font-normal text-slate-600">· {{ applicant.mobile }}</span>
                            </h4>
                            <p class="mt-1 text-sm text-slate-600">
                                Citizenship {{ applicant.citizenship_number || '—' }} ·
                                BOID {{ applicant.boid || '—' }} ·
                                {{ applicant.bank_name || '—' }} ({{ applicant.bank_account_number || '—' }})
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
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

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <Link
                            :href="route('applicants.profile.show', applicant.id)"
                            class="inline-flex items-center rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-white transition duration-150 hover:bg-brand-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand active:bg-brand-800"
                        >
                            View &amp; act
                        </Link>
                    </div>

                    <details class="mt-4 border-t border-gray-100 pt-3">
                        <summary class="cursor-pointer text-sm font-medium text-slate-600 hover:text-slate-900">
                            Review trail
                        </summary>
                        <div class="mt-3">
                            <WorkflowTimeline :events="applicant.workflow_events ?? []" />
                        </div>
                    </details>
                </article>

                <Pagination :meta="pending" label="profiles" />
            </section>

            <section v-show="activeTab === 'decided'" class="space-y-4">
                <p
                    v-if="!recentlyReviewed.data.length"
                    class="rounded-xl bg-white p-8 text-sm text-slate-600 shadow-sm ring-1 ring-gray-200"
                >
                    Nothing decided yet.
                </p>

                <article
                    v-for="applicant in recentlyReviewed.data"
                    :key="applicant.id"
                    class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="font-semibold text-slate-900">{{ applicant.full_name_en }}</h4>
                            <p class="mt-1 text-sm text-slate-600">
                                Last action by {{ lastAction(applicant)?.actor?.name ?? '—' }}
                                <span v-if="lastAction(applicant)">({{ lastAction(applicant).stage_label }})</span>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ formatWhen(lastAction(applicant)?.created_at) }}
                            </p>
                        </div>

                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                            :class="applicant.profile_status === 'approved'
                                ? 'bg-green-50 text-green-800 ring-green-200'
                                : 'bg-amber-50 text-amber-900 ring-amber-200'"
                        >
                            {{ applicant.profile_status_label }}
                        </span>
                    </div>

                    <div class="mt-4">
                        <Link
                            :href="route('applicants.profile.show', applicant.id)"
                            class="inline-flex items-center rounded-lg border border-brand px-3 py-1.5 text-sm font-semibold text-brand transition duration-150 hover:bg-brand-50"
                        >
                            View profile
                        </Link>
                    </div>
                </article>

                <Pagination :meta="recentlyReviewed" label="decisions" />
            </section>
        </div>
    </PanelLayout>
</template>
