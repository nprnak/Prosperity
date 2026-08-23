<script setup>
import Pagination from '@/Components/Pagination.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

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
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">File a Paper Application</h2>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="rounded-lg border bg-white p-4">
                <h3 class="text-lg font-semibold text-slate-900">Choose the applicant</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Only applicants whose KYC is already approved can have an application filed for them.
                    Search by name, mobile, or citizenship number.
                </p>
            </div>

            <p v-if="page.props.errors?.profile" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ page.props.errors.profile }}
            </p>

            <form class="flex gap-2" @submit.prevent="applySearch">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search approved applicants..."
                    class="w-full rounded-md border-slate-300 text-sm focus:border-brand focus:ring-brand"
                />
                <button type="submit" class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                    Search
                </button>
            </form>

            <div class="space-y-3">
                <p
                    v-if="!applicants.data.length"
                    class="rounded-xl bg-white p-8 text-sm text-slate-600 shadow-sm ring-1 ring-gray-200"
                >
                    No approved applicants match that search.
                </p>

                <article
                    v-for="profile in applicants.data"
                    :key="profile.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200"
                >
                    <div>
                        <h4 class="font-semibold text-slate-900">{{ profile.full_name_en }}</h4>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ profile.user?.email || '—' }} · {{ profile.mobile || '—' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">BOID {{ profile.boid || '—' }}</p>
                    </div>

                    <Link
                        :href="route('applications.add.create', profile.user_id)"
                        class="inline-flex items-center rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-brand-600"
                    >
                        File Application
                    </Link>
                </article>

                <Pagination :meta="applicants" label="applicants" />
            </div>
        </div>
    </PanelLayout>
</template>
