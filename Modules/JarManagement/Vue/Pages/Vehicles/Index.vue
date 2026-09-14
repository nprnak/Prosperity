<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
  vehicles: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
});

const vehicleForm = useForm({ vehicle_number: '', name: '', max_jars: 80, active: true });
const submitVehicle = () => vehicleForm.post(route('jar.vehicles.store'), { onSuccess: () => vehicleForm.reset() });

const driverForm = useForm({ name: '', phone: '', license_no: '', active: true });
const submitDriver = () => driverForm.post(route('jar.vehicles.drivers.store'), { onSuccess: () => driverForm.reset() });
</script>

<template>
  <Head title="Vehicles & Drivers" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Vehicles & Drivers</h2>
        <p class="mt-1 text-sm text-gray-700">Master data used when creating a dispatch lot.</p>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-lg bg-white p-5 shadow">
          <form class="mb-4" @submit.prevent="submitVehicle">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Add vehicle</h3>
            <div class="grid grid-cols-2 gap-3">
              <input v-model="vehicleForm.vehicle_number" type="text" placeholder="Vehicle number" class="rounded-md border-gray-300 text-sm shadow-sm" />
              <input v-model="vehicleForm.name" type="text" placeholder="Name (optional)" class="rounded-md border-gray-300 text-sm shadow-sm" />
              <input v-model.number="vehicleForm.max_jars" type="number" min="1" max="80" placeholder="Max jars" class="rounded-md border-gray-300 text-sm shadow-sm" />
            </div>
            <PrimaryButton class="mt-3" :disabled="vehicleForm.processing">Add Vehicle</PrimaryButton>
          </form>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Vehicle</th>
                <th class="py-2 px-4 text-right">Max Jars</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="v in vehicles" :key="v.id" class="border-b last:border-0">
                <td class="py-2 pr-4">{{ v.vehicle_number }} <span class="text-xs text-gray-500">{{ v.name }}</span></td>
                <td class="py-2 px-4 text-right">{{ v.max_jars }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="rounded-lg bg-white p-5 shadow">
          <form class="mb-4" @submit.prevent="submitDriver">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Add driver</h3>
            <div class="grid grid-cols-2 gap-3">
              <input v-model="driverForm.name" type="text" placeholder="Name" class="rounded-md border-gray-300 text-sm shadow-sm" />
              <input v-model="driverForm.phone" type="text" placeholder="Phone" class="rounded-md border-gray-300 text-sm shadow-sm" />
              <input v-model="driverForm.license_no" type="text" placeholder="License no." class="rounded-md border-gray-300 text-sm shadow-sm" />
            </div>
            <PrimaryButton class="mt-3" :disabled="driverForm.processing">Add Driver</PrimaryButton>
          </form>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Name</th>
                <th class="py-2 px-4 text-left">Phone</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in drivers" :key="d.id" class="border-b last:border-0">
                <td class="py-2 pr-4">{{ d.name }}</td>
                <td class="py-2 px-4">{{ d.phone || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
