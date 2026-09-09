<script setup>
import Pagination from '@/Components/Pagination.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { EyeIcon, DocumentPlusIcon, MagnifyingGlassIcon, ClipboardDocumentListIcon, UserPlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    applicants: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({ q: null }) },
    // Whoever can see the full applications list sees every approved
    // applicant here too; everyone else sees only the ones they entered.
    seesAll: { type: Boolean, default: false },
});

const page = usePage();
const search = ref(props.filters?.q || '');
const canFileApplication = (page.props.auth?.permissions || []).includes('application.verify');
const canAddApplicant = (page.props.auth?.permissions || []).includes('profile.verify');

const applySearch = () => {
    router.get(route('applicants.index'), { q: search.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const addressOf = (profile) => {
    const address = profile.permanent_address;
    if (!address) return '—';

    return [address.tole, address.local_level, address.district, address.province]
        .filter(Boolean)
        .join(', ') || '—';
};
</script>

<template>
    <Head title="Applicant List" />

    <PanelLayout>
        <div v-if="canAddApplicant" class="mb-4 flex justify-end">
            <Link
                :href="route('applicants.add.create')"
                class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600"
            >
                <UserPlusIcon class="h-4 w-4" /> Add Applicant
            </Link>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Approved applicants</h3>
                    <p class="mt-1 text-sm text-slate-700">
                        <template v-if="seesAll">Every applicant whose KYC has been approved.</template>
                        <template v-else>Approved applicants you entered from a paper KYC form.</template>
                    </p>
                </div>
                <form class="flex gap-2" @submit.prevent="applySearch">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search by name, mobile, citizenship..."
                        class="rounded-md border-gray-300 text-sm focus:border-indigo-600 focus:ring-indigo-600"
                    />
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        <MagnifyingGlassIcon class="h-4 w-4" /> Search
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Full Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mobile</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="profile in applicants.data" :key="profile.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ profile.full_name_en }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ addressOf(profile) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ profile.mobile || '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ profile.user?.email || '—' }}</td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <Link
                                        :href="route('applicants.profile.show', profile.id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                        title="View Profile"
                                    >
                                        <EyeIcon class="h-4 w-4" />
                                    </Link>
                                    <Link
                                        :href="route('applicants.applications', profile.id)"
                                        class="text-slate-600 hover:text-slate-900"
                                        title="View Applications"
                                    >
                                        <ClipboardDocumentListIcon class="h-4 w-4" />
                                    </Link>
                                    <Link
                                        v-if="canFileApplication"
                                        :href="route('applications.add.create', profile.user_id)"
                                        class="text-emerald-600 hover:text-emerald-900"
                                        title="File Application"
                                    >
                                        <DocumentPlusIcon class="h-4 w-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!applicants.data.length" class="text-center py-12">
                <p class="text-gray-500">No approved applicants match that search.</p>
            </div>

            <div class="mt-4">
                <Pagination :meta="applicants" label="applicants" />
            </div>
        </div>
    </PanelLayout>
</template>
