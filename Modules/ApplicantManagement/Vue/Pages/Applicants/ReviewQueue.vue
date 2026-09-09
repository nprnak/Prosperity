<script setup>
import Pagination from '@/Components/Pagination.vue';
import QueueToolbar from '@/Components/QueueToolbar.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PlusIcon, EyeIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const canAddApplicant = (page.props.auth?.permissions || []).includes('profile.verify');

const props = defineProps({
    // A Laravel paginator: { data, links, from, to, total }.
    pending: { type: Object, default: () => ({ data: [], links: [] }) },
    // Paged under its own `decided` query parameter.
    recentlyReviewed: { type: Object, default: () => ({ data: [], links: [] }) },
    // Paper KYC forms this Verifier filed themselves — paged under `entered`.
    enteredByMe: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({ q: null }) },
});

const activeTab = ref('pending');
const search = ref(props.filters?.q || '');

const tabs = [
    { key: 'pending', label: 'Waiting on you', get count() { return props.pending.total; } },
    { key: 'decided', label: 'Recently decided', get count() { return props.recentlyReviewed.total; } },
    { key: 'entered', label: 'My paper entries', get count() { return props.enteredByMe.total; } },
];

// Still with the Verifier stage (fresh, or sent back by the Reviewer) — the
// staff KYC form is editable at exactly these statuses.
const isEditable = (applicant) => ['incomplete', 'returned', 'submitted'].includes(applicant.profile_status);

const applySearch = () => {
    router.get(route('applicants.review'), { q: search.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const statusClass = (status) => {
    if (status === 'approved') return 'bg-green-100 text-green-700';
    if (status === 'returned') return 'bg-amber-100 text-amber-900';
    return 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <Head title="KYC Review Queue" />

    <PanelLayout>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Profile Review</h2>
                    <p class="mt-1 text-sm text-gray-700">
                        Each profile shows the stage it is waiting for. A profile needs three different
                        people across verification, review and approval, so anything you have already
                        acted on at another stage is hidden.
                    </p>
                </div>
                <Link
                    v-if="canAddApplicant"
                    :href="route('applicants.add.create')"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-brand-600"
                >
                    <PlusIcon class="h-4 w-4" /> Add Applicant
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Full Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mobile</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Citizenship No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">BOID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="applicant in pending.data" :key="applicant.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                {{ applicant.full_name_en }}
                                <span v-if="applicant.workflow_cycle > 1" class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">
                                    Corrected
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ applicant.mobile || '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ applicant.citizenship_number || '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ applicant.boid || '—' }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                    Awaiting {{ applicant.pending_stage_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <Link :href="route('applicants.profile.show', applicant.id)" class="text-indigo-600 hover:text-indigo-900" title="View &amp; act">
                                    <EyeIcon class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!pending.data.length" class="text-center py-12">
                    <p class="text-gray-500">Nothing waiting on you. Profiles appear here once an applicant submits their KYC for review, or when a later stage sends one back.</p>
                </div>

                <div class="mt-4"><Pagination :meta="pending" label="profiles" /></div>
            </div>

            <!-- Recently decided -->
            <div v-show="activeTab === 'decided'" class="mt-4 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Full Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mobile</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Citizenship No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">BOID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="applicant in recentlyReviewed.data" :key="`decided-${applicant.id}`" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ applicant.full_name_en }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ applicant.mobile || '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ applicant.citizenship_number || '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ applicant.boid || '—' }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span :class="statusClass(applicant.profile_status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ applicant.profile_status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <Link :href="route('applicants.profile.show', applicant.id)" class="text-indigo-600 hover:text-indigo-900" title="View Profile">
                                    <EyeIcon class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!recentlyReviewed.data.length" class="text-center py-12">
                    <p class="text-gray-500">Nothing decided yet.</p>
                </div>

                <div class="mt-4"><Pagination :meta="recentlyReviewed" label="decisions" /></div>
            </div>

            <!-- My paper entries -->
            <div v-show="activeTab === 'entered'" class="mt-4 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Full Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mobile</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Citizenship No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">BOID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="applicant in enteredByMe.data" :key="`entered-${applicant.id}`" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ applicant.full_name_en }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ applicant.mobile || '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ applicant.citizenship_number || '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ applicant.boid || '—' }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span :class="statusClass(applicant.profile_status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ applicant.profile_status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <Link
                                    v-if="isEditable(applicant)"
                                    :href="route('applicants.add.kyc.edit', applicant.user_id)"
                                    class="text-indigo-600 hover:text-indigo-900"
                                    :title="applicant.profile_status === 'submitted' ? 'Edit & re-forward' : 'Continue editing'"
                                >
                                    <PencilSquareIcon class="h-4 w-4" />
                                </Link>
                                <Link v-else :href="route('applicants.profile.show', applicant.id)" class="text-indigo-600 hover:text-indigo-900" title="View Profile">
                                    <EyeIcon class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!enteredByMe.data.length" class="text-center py-12">
                    <p class="text-gray-500">You have not entered any paper KYC forms yet.</p>
                </div>

                <div class="mt-4"><Pagination :meta="enteredByMe" label="entries" /></div>
            </div>
        </div>
    </PanelLayout>
</template>
