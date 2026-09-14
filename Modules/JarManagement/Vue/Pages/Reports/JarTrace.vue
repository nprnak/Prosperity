<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import JarScanInput from '../../Components/JarScanInput.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  jarCode: { type: String, default: '' },
  jar: { type: Object, default: null },
  error: { type: String, default: null },
});

const code = ref(props.jarCode);
const search = (value) => {
  code.value = value ?? code.value;
  router.get(route('jar.trace'), { jar_code: code.value }, { preserveState: true });
};

const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
  <Head title="Jar Trace" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Jar-Level Search</h2>
        <p class="mt-1 text-sm text-gray-700">Find a jar by its code and see its complete movement history.</p>
      </div>

      <div class="rounded-lg bg-white p-5 shadow">
        <JarScanInput placeholder="Scan or type a jar code" @scan="search" />
        <div class="mt-3 flex gap-2">
          <input v-model="code" type="text" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" @keydown.enter="search()" />
          <PrimaryButton @click="search()">Search</PrimaryButton>
        </div>
        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
      </div>

      <div v-if="jar" class="space-y-6">
        <div class="rounded-lg bg-white p-6 shadow">
          <h3 class="text-lg font-semibold text-gray-900">{{ jar.jar_code }}</h3>
          <div class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div><p class="text-xs text-gray-500">Status</p><p class="font-medium">{{ humanize(jar.status) }}</p></div>
            <div><p class="text-xs text-gray-500">Condition</p><p class="font-medium">{{ humanize(jar.condition) }}</p></div>
            <div><p class="text-xs text-gray-500">Current Batch</p><p class="font-medium">{{ jar.current_batch?.batch_code || '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Current Vehicle Lot</p><p class="font-medium">{{ jar.current_vehicle_lot?.lot_code || '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Current Customer</p><p class="font-medium">{{ jar.current_customer?.name || '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Registered</p><p class="font-medium">{{ jar.registered_at }}</p></div>
          </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow">
          <h3 class="mb-4 text-sm font-semibold text-gray-900">Movement history</h3>
          <ol class="space-y-3 border-l border-gray-200 pl-4">
            <li v-for="m in jar.movements" :key="m.id">
              <p class="text-sm font-medium text-gray-900">{{ humanize(m.event_type) }}</p>
              <p class="text-xs text-gray-500">
                {{ m.occurred_at }} — {{ m.recorded_by?.name }}
                <span v-if="m.batch"> · Batch {{ m.batch.batch_code }}</span>
                <span v-if="m.lot"> · Lot {{ m.lot.lot_code }}</span>
                <span v-if="m.customer"> · {{ m.customer.name }}</span>
              </p>
              <p v-if="m.notes" class="text-xs italic text-gray-500">{{ m.notes }}</p>
            </li>
          </ol>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
