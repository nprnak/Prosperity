<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import JarScanInput from '../../Components/JarScanInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
  batch: { type: Object, required: true },
});

const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const scanForm = useForm({ jar_code: '' });
const onScan = (code) => {
  scanForm.jar_code = code;
  scanForm.post(route('jar.production.scan', props.batch.id), { preserveScroll: true, onFinish: () => { scanForm.jar_code = ''; } });
};

const recordStep = (name) => {
  router.post(route(`jar.production.${name}`, props.batch.id), {}, { preserveScroll: true });
};

// One checkbox/reason pair per scanned jar for the quality-approval sweep —
// defaults to "approve" since most jars in a batch pass QC.
const decisions = reactive(
  Object.fromEntries((props.batch.items || []).map((item) => [item.jar_id, { pass: true, reason: '' }])),
);

const approvalForm = useForm({ approved_jar_ids: [], rejections: [], notes: '' });
const submitQualityApproval = () => {
  approvalForm.approved_jar_ids = Object.entries(decisions).filter(([, d]) => d.pass).map(([jarId]) => Number(jarId));
  approvalForm.rejections = Object.entries(decisions).filter(([, d]) => !d.pass).map(([jarId, d]) => ({ jar_id: Number(jarId), reason: d.reason }));
  approvalForm.post(route('jar.production.quality-approval', props.batch.id), { preserveScroll: true });
};

const canApprove = computed(() => props.batch.status === 'pending_approval' && props.batch.items?.length > 0);
</script>

<template>
  <Head :title="`Batch ${batch.batch_code}`" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Batch {{ batch.batch_code }}</h2>
        <p class="mt-1 text-sm text-gray-700">{{ batch.production_date }} — {{ humanize(batch.status) }}</p>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-lg bg-white p-5 shadow lg:col-span-2">
          <h3 class="mb-3 text-sm font-semibold text-gray-900">Scan jars into this batch</h3>
          <JarScanInput :disabled="batch.status !== 'open'" @scan="onScan" />
          <p v-if="batch.status !== 'open'" class="mt-2 text-xs text-amber-600">This batch is no longer open for scanning.</p>
        </div>

        <div class="rounded-lg bg-white p-5 shadow">
          <h3 class="mb-3 text-sm font-semibold text-gray-900">Process steps</h3>
          <div class="space-y-2 text-sm">
            <div class="flex items-center justify-between">
              <span>Cleaning</span>
              <SecondaryButton v-if="!batch.cleaning_at" @click="recordStep('cleaning')">Record</SecondaryButton>
              <span v-else class="text-xs text-green-700">Done</span>
            </div>
            <div class="flex items-center justify-between">
              <span>Refilling</span>
              <SecondaryButton v-if="!batch.refilling_at" @click="recordStep('refilling')">Record</SecondaryButton>
              <span v-else class="text-xs text-green-700">Done</span>
            </div>
            <div class="flex items-center justify-between">
              <span>Sealing</span>
              <SecondaryButton v-if="!batch.sealing_at" @click="recordStep('sealing')">Record</SecondaryButton>
              <span v-else class="text-xs text-green-700">Done</span>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Scanned jars ({{ batch.items?.length || 0 }})</h3>
        <div v-if="batch.items?.length" class="overflow-x-auto">
          <table class="w-full min-w-[640px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Jar Code</th>
                <th class="py-2 px-4 text-left">Quality Result</th>
                <th v-if="canApprove" class="py-2 px-4 text-left">Approve?</th>
                <th v-if="canApprove" class="py-2 px-4 text-left">Rejection reason</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in batch.items" :key="item.id" class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-900">{{ item.jar?.jar_code }}</td>
                <td class="py-2 px-4">{{ humanize(item.quality_result) }}</td>
                <td v-if="canApprove" class="py-2 px-4">
                  <select v-model="decisions[item.jar_id].pass" class="rounded-md border-gray-300 text-xs shadow-sm">
                    <option :value="true">Approve</option>
                    <option :value="false">Reject</option>
                  </select>
                </td>
                <td v-if="canApprove" class="py-2 px-4">
                  <input
                    v-if="!decisions[item.jar_id].pass"
                    v-model="decisions[item.jar_id].reason"
                    type="text"
                    placeholder="Reason"
                    class="w-full rounded-md border-gray-300 text-xs shadow-sm"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-12 text-center text-sm text-gray-500">No jars scanned into this batch yet.</p>

        <div v-if="canApprove" class="mt-4 flex items-center gap-3">
          <input v-model="approvalForm.notes" type="text" placeholder="Notes (optional)" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" />
          <PrimaryButton :disabled="approvalForm.processing" @click="submitQualityApproval">Submit Quality Approval</PrimaryButton>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
