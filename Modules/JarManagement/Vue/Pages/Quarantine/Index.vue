<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
  quarantinedJars: { type: Object, default: () => ({ data: [] }) },
  flaggedReturns: { type: Array, default: () => [] },
});

const resolve = (jar, releaseToCleaningQueue) => {
  router.post(route('jar.quarantine.resolve', jar.id), { release_to_cleaning_queue: releaseToCleaningQueue }, { preserveScroll: true });
};

const verifyReturn = (ret) => {
  router.post(route('jar.quarantine.returns.verify', ret.id), {}, { preserveScroll: true });
};
</script>

<template>
  <Head title="Quarantine Review" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Quarantine Review</h2>
        <p class="mt-1 text-sm text-gray-700">Quarantined jars and excess returns awaiting a decision.</p>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Quarantined jars</h3>
        <div v-if="quarantinedJars.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[480px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Jar Code</th>
                <th class="py-2 px-4 text-left">Condition</th>
                <th class="py-2 px-4 text-right"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="jar in quarantinedJars.data" :key="jar.id" class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-900">{{ jar.jar_code }}</td>
                <td class="py-2 px-4 capitalize">{{ jar.condition }}</td>
                <td class="py-2 px-4 text-right space-x-2">
                  <SecondaryButton @click="resolve(jar, true)">Release to Cleaning</SecondaryButton>
                  <SecondaryButton @click="resolve(jar, false)">Retire</SecondaryButton>
                </td>
              </tr>
            </tbody>
          </table>
          <Pagination :meta="quarantinedJars" label="jars" />
        </div>
        <p v-else class="py-8 text-center text-sm text-gray-500">No jars currently quarantined.</p>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Flagged excess/ownership returns</h3>
        <div v-if="flaggedReturns.length" class="space-y-4">
          <div v-for="delivery in flaggedReturns" :key="delivery.id" class="rounded-md border border-amber-200 bg-amber-50 p-3">
            <p class="text-sm font-medium text-gray-900">{{ delivery.customer?.name }} — {{ delivery.delivered_at }}</p>
            <ul class="mt-2 space-y-1">
              <li v-for="ret in delivery.returns" :key="ret.id" class="flex items-center justify-between text-sm">
                <span>{{ ret.jar?.jar_code }} {{ ret.is_new_registration ? '(new/uncoded)' : '' }}</span>
                <SecondaryButton @click="verifyReturn(ret)">Mark Verified</SecondaryButton>
              </li>
            </ul>
          </div>
        </div>
        <p v-else class="py-8 text-center text-sm text-gray-500">No flagged returns.</p>
      </div>
    </div>
  </PanelLayout>
</template>
