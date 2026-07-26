<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

/**
 * Designating focal persons. Anyone with an approved KYC profile can be tagged,
 * whatever their role — the tag carries no permissions, it only decides who an
 * applicant may credit their application to.
 */
const props = defineProps({
  candidates: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ search: '', tagged: false }) },
});

const search = ref(props.filters.search || '');
const taggedOnly = ref(Boolean(props.filters.tagged));

const query = () => {
  const params = {};
  if (search.value) params.search = search.value;
  if (taggedOnly.value) params.tagged = 1;

  return params;
};

const reload = () => {
  router.get(route('admin.focal-persons'), query(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

let searchTimer = null;
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(reload, 300);
});
watch(taggedOnly, reload);

const toggle = (candidate) => {
  // Un-tagging keeps every existing attribution: the figures already reported
  // against this person must stay attributable to them.
  if (candidate.is_focal_person) {
    const stranded = candidate.applicants_count + candidate.applications_count;
    const warning = stranded > 0
      ? `\n\n${candidate.applicants_count} applicant(s) and ${candidate.applications_count} application(s) stay credited to them; they simply stop appearing as a choice.`
      : '';

    if (!confirm(`Remove focal person designation from ${candidate.name}?${warning}`)) return;
  }

  router.patch(
    route('admin.focal-persons.update', candidate.id),
    { is_focal_person: !candidate.is_focal_person, ...query() },
    { preserveScroll: true },
  );
};

const buttonClass = (candidate) => (candidate.is_focal_person
  ? 'rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100'
  : 'rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700');
</script>

<template>
  <Head title="Admin - Focal Persons" />
  <PanelLayout>
    <div class="p-6 bg-white rounded-lg shadow">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
        <h2 class="text-2xl font-bold text-gray-900">Focal Persons</h2>
      </div>
      <p class="max-w-[75ch] mb-6 text-sm text-gray-600">
        Designate anyone with an approved KYC profile as a focal person. They receive a code, which
        applicants quote on their application to credit it to them. The designation grants no
        permissions and does not change the person's role.
      </p>

      <p v-if="$page.props.errors.is_focal_person" class="px-4 py-2 mb-4 text-sm text-red-700 rounded bg-red-50">
        {{ $page.props.errors.is_focal_person }}
      </p>

      <div class="flex flex-wrap items-center gap-3 mb-4">
        <input
          v-model="search"
          type="search"
          placeholder="Search name, email or code"
          class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
        />
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="taggedOnly" type="checkbox" class="text-blue-600 border-gray-300 rounded" />
          Focal persons only
        </label>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="border-b bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">Name</th>
              <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">Email</th>
              <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">Code</th>
              <th class="px-4 py-3 text-xs font-medium text-right text-gray-500 uppercase">Applicants</th>
              <th class="px-4 py-3 text-xs font-medium text-right text-gray-500 uppercase">Applications</th>
              <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
              <th class="px-4 py-3 text-xs font-medium text-right text-gray-500 uppercase">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="candidate in candidates" :key="candidate.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-900">{{ candidate.name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ candidate.email }}</td>
              <td class="px-4 py-3 font-mono text-sm text-gray-900">{{ candidate.focal_person_code || '—' }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-700">{{ candidate.applicants_count }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-700">{{ candidate.applications_count }}</td>
              <td class="px-4 py-3 text-sm">
                <span
                  v-if="candidate.is_focal_person"
                  class="px-2 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded"
                >
                  Focal person
                </span>
                <span v-else class="px-2 py-1 text-xs font-semibold text-gray-600 bg-gray-100 rounded">
                  Eligible
                </span>
                <!-- Tagged but KYC no longer approved: kept for history, but
                     no longer offered to applicants. -->
                <span
                  v-if="candidate.is_focal_person && !candidate.kyc_approved"
                  class="ml-1 px-2 py-1 text-xs font-semibold rounded text-amber-800 bg-amber-100"
                  title="KYC is no longer approved, so this person is not offered to applicants."
                >
                  KYC not approved
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <button :class="buttonClass(candidate)" @click="toggle(candidate)">
                  {{ candidate.is_focal_person ? 'Remove' : 'Make focal person' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!candidates.length" class="py-12 text-center">
        <p class="text-gray-500">
          No eligible users found. A user needs an approved KYC profile before they can be designated.
        </p>
      </div>
    </div>
  </PanelLayout>
</template>
