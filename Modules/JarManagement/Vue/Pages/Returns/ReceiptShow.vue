<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import JarScanInput from '../../Components/JarScanInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  receipt: { type: Object, required: true },
  quarantineReasonOptions: { type: Array, default: () => [] },
});

const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const pendingDecision = ref('accepted');
const pendingReason = ref('');

const scanForm = useForm({ jar_code: '', decision: 'accepted', quarantine_reason: '', notes: '' });
const onScan = (code) => {
  scanForm.jar_code = code;
  scanForm.decision = pendingDecision.value;
  scanForm.quarantine_reason = pendingDecision.value === 'quarantined' ? pendingReason.value : '';
  scanForm.post(route('jar.receipts.scan', props.receipt.id), { preserveScroll: true });
};

const close = () => {
  router.post(route('jar.receipts.close', props.receipt.id));
};
</script>

<template>
  <Head :title="`Receipt — ${receipt.lot?.lot_code}`" />
  <PanelLayout>
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-2xl font-bold text-gray-900">Receipt — {{ receipt.lot?.lot_code }}</h2>
          <p class="mt-1 text-sm text-gray-700">
            {{ receipt.lot?.vehicle?.vehicle_number }} · Expected {{ receipt.total_expected }}, scanned {{ receipt.total_scanned }}
          </p>
        </div>
        <PrimaryButton @click="close">Close Receipt</PrimaryButton>
      </div>

      <div class="rounded-lg bg-white p-5 shadow">
        <h3 class="mb-3 text-sm font-semibold text-gray-900">Scan returned jar</h3>
        <div class="mb-3 flex flex-wrap items-center gap-3">
          <select v-model="pendingDecision" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="accepted">Accept</option>
            <option value="quarantined">Quarantine</option>
          </select>
          <select
            v-if="pendingDecision === 'quarantined'"
            v-model="pendingReason"
            class="rounded-md border-gray-300 text-sm shadow-sm"
          >
            <option value="">Reason...</option>
            <option v-for="opt in quarantineReasonOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
        <JarScanInput :disabled="pendingDecision === 'quarantined' && !pendingReason" @scan="onScan" />
        <p v-if="scanForm.errors.jar_code" class="mt-2 text-xs text-red-600">{{ scanForm.errors.jar_code }}</p>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Scanned so far ({{ receipt.items?.length || 0 }})</h3>
        <div v-if="receipt.items?.length" class="overflow-x-auto">
          <table class="w-full min-w-[560px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Jar Code</th>
                <th class="py-2 px-4 text-left">Decision</th>
                <th class="py-2 px-4 text-left">Reason</th>
                <th class="py-2 px-4 text-left">Matched Vehicle</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in receipt.items" :key="item.id" class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-900">{{ item.jar?.jar_code }}</td>
                <td class="py-2 px-4">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="item.decision === 'accepted' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                  >
                    {{ humanize(item.decision) }}
                  </span>
                </td>
                <td class="py-2 px-4">{{ item.quarantine_reason ? humanize(item.quarantine_reason) : '—' }}</td>
                <td class="py-2 px-4">{{ item.matched_to_vehicle ? 'Yes' : 'No' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-12 text-center text-sm text-gray-500">No jars scanned yet.</p>
      </div>
    </div>
  </PanelLayout>
</template>
