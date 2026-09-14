<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import JarScanInput from '../../Components/JarScanInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  lot: { type: Object, required: true },
});

const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const scanForm = useForm({ jar_code: '' });
const onScan = (code) => {
  scanForm.jar_code = code;
  scanForm.post(route('jar.dispatch.load', props.lot.id), { preserveScroll: true, onFinish: () => { scanForm.jar_code = ''; } });
};

const dispatch = () => {
  router.post(route('jar.dispatch.dispatch', props.lot.id), {}, { preserveScroll: true });
};

const remaining = computed(() => props.lot.max_jars - (props.lot.items?.length || 0));
</script>

<template>
  <Head :title="`Lot ${lot.lot_code}`" />
  <PanelLayout>
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-2xl font-bold text-gray-900">Lot {{ lot.lot_code }}</h2>
          <p class="mt-1 text-sm text-gray-700">
            {{ lot.vehicle?.vehicle_number }} · {{ lot.driver?.name || 'No driver' }} · {{ lot.assigned_staff?.name }}
            · {{ humanize(lot.status) }}
          </p>
        </div>
        <PrimaryButton v-if="lot.status === 'loading' && lot.items?.length" @click="dispatch">Dispatch Vehicle</PrimaryButton>
      </div>

      <div v-if="lot.status === 'loading'" class="rounded-lg bg-white p-5 shadow">
        <h3 class="mb-3 text-sm font-semibold text-gray-900">
          Load jars — {{ lot.items?.length || 0 }} / {{ lot.max_jars }} ({{ remaining }} remaining)
        </h3>
        <JarScanInput :disabled="remaining <= 0" @scan="onScan" />
        <p v-if="remaining <= 0" class="mt-2 text-xs text-amber-600">This lot has reached its {{ lot.max_jars }}-jar maximum.</p>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Loaded jars</h3>
        <div v-if="lot.items?.length" class="overflow-x-auto">
          <table class="w-full min-w-[480px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Jar Code</th>
                <th class="py-2 px-4 text-left">Status</th>
                <th class="py-2 px-4 text-left">Loaded By</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in lot.items" :key="item.id" class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-900">{{ item.jar?.jar_code }}</td>
                <td class="py-2 px-4">{{ humanize(item.status) }}</td>
                <td class="py-2 px-4">{{ item.loaded_by?.name }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-12 text-center text-sm text-gray-500">No jars loaded yet.</p>
      </div>
    </div>
  </PanelLayout>
</template>
