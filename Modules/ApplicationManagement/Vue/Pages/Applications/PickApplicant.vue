<script setup>
import Pagination from '@/Components/Pagination.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { MagnifyingGlassIcon, DocumentPlusIcon, IdentificationIcon, UserCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    applicants: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({ q: null }) },
});

const page = usePage();
const search = ref(props.filters?.q || '');

const applySearch = () => {
    router.get(route('applications.add.pick'), { q: search.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="File a Paper Application" />

    <PanelLayout>
        <div class="bg-white rounded-lg shadow p-6 space-y-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Choose the Applicant</h2>
                <p class="mt-1 text-sm text-gray-700">
                    Only applicants whose KYC is already approved can have an application filed for them.
                    Search by name, mobile, or citizenship number.
                </p>
            </div>

            <p v-if="page.props.errors?.profile" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ page.props.errors.profile }}
            </p>

            <form class="flex gap-2" @submit.prevent="applySearch">
                <div class="relative w-full">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search approved applicants..."
                        class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-brand focus:ring-brand"
                    />
                </div>
                <button type="submit" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600">
                    <MagnifyingGlassIcon class="h-4 w-4" /> Search
                </button>
            </form>

            <div class="space-y-3">
                <p
                    v-if="!applicants.data.length"
                    class="rounded-lg border border-gray-200 p-8 text-center text-sm text-gray-700"
                >
                    No approved applicants match that search.
                </p>

                <article
                    v-for="profile in applicants.data"
                    :key="profile.id"
                    class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-gray-200 p-4 transition hover:bg-gray-50"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500">
                            <UserCircleIcon class="h-7 w-7" />
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900">{{ profile.full_name_en }}</h4>
                            <p class="mt-0.5 text-sm text-gray-700">
                                {{ profile.user?.email || '—' }} · {{ profile.mobile || '—' }}
                            </p>
                            <p class="mt-0.5 flex items-center gap-1 text-xs text-gray-600">
                                <IdentificationIcon class="h-3.5 w-3.5" /> BOID {{ profile.boid || '—' }}
                            </p>
                        </div>
                    </div>

                    <Link
                        :href="route('applications.add.create', profile.user_id)"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-brand-600"
                    >
                        <DocumentPlusIcon class="h-4 w-4" /> File Application
                    </Link>
                </article>

                <Pagination :meta="applicants" label="applicants" />
            </div>
        </div>
    </PanelLayout>
</template>
