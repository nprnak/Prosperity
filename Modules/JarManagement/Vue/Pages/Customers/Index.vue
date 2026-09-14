<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  customers: { type: Object, required: true },
  typeOptions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});

const q = ref(props.filters.q || '');
const search = () => router.get(route('jar.customers.index'), { q: q.value }, { preserveState: true });

const form = useForm({ name: '', phone: '', type: 'household', address: '', route_area: '', default_price: null, active: true });
const submit = () => form.post(route('jar.customers.store'), { onSuccess: () => form.reset() });
</script>

<template>
  <Head title="Jar Customers" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Jar Customers</h2>
        <p class="mt-1 text-sm text-gray-700">Households and dealers, with their running empty-jar balance.</p>
      </div>

      <form class="rounded-lg bg-white p-5 shadow" @submit.prevent="submit">
        <h3 class="mb-3 text-sm font-semibold text-gray-900">Add a customer</h3>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <input v-model="form.name" type="text" placeholder="Name" class="rounded-md border-gray-300 text-sm shadow-sm" />
          <input v-model="form.phone" type="text" placeholder="Phone" class="rounded-md border-gray-300 text-sm shadow-sm" />
          <select v-model="form.type" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
          <input v-model="form.route_area" type="text" placeholder="Route/area" class="rounded-md border-gray-300 text-sm shadow-sm" />
          <input v-model.number="form.default_price" type="number" step="0.01" placeholder="Default price" class="rounded-md border-gray-300 text-sm shadow-sm" />
        </div>
        <PrimaryButton class="mt-3" :disabled="form.processing">Add Customer</PrimaryButton>
      </form>

      <div class="rounded-lg bg-white p-6 shadow">
        <div class="mb-4 flex items-center gap-3">
          <input v-model="q" type="text" placeholder="Search by name or phone" class="w-full max-w-sm rounded-md border-gray-300 text-sm shadow-sm" @keydown.enter="search" />
          <PrimaryButton @click="search">Search</PrimaryButton>
        </div>

        <div v-if="customers.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[640px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Name</th>
                <th class="py-2 px-4 text-left">Phone</th>
                <th class="py-2 px-4 text-left">Type</th>
                <th class="py-2 px-4 text-right">Outstanding Jars</th>
                <th class="py-2 px-4 text-right"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in customers.data" :key="c.id" class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-900">{{ c.name }}</td>
                <td class="py-2 px-4">{{ c.phone || '—' }}</td>
                <td class="py-2 px-4 capitalize">{{ c.type }}</td>
                <td class="py-2 px-4 text-right">{{ c.jars_outstanding }}</td>
                <td class="py-2 px-4 text-right">
                  <Link :href="route('jar.customers.show', c.id)" class="text-xs font-semibold text-brand-700 hover:text-brand-900">View →</Link>
                </td>
              </tr>
            </tbody>
          </table>
          <Pagination :meta="customers" label="customers" />
        </div>
        <p v-else class="py-12 text-center text-sm text-gray-500">No customers yet.</p>
      </div>
    </div>
  </PanelLayout>
</template>
