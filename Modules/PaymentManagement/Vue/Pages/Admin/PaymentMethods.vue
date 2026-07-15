<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ methods: Array });

const blankMethod = {
    name: '', account_name: '', account_number: '', bank_name: '',
    instructions: '', qr_image: null, status: 'active', sort_order: 0,
};

const form = useForm({ ...blankMethod });
const editingId = ref(null);

const startEdit = (method) => {
    editingId.value = method.id;
    Object.assign(form, {
        name: method.name,
        account_name: method.account_name || '',
        account_number: method.account_number || '',
        bank_name: method.bank_name || '',
        instructions: method.instructions || '',
        qr_image: null,
        status: method.status,
        sort_order: method.sort_order,
    });
};

const cancelEdit = () => {
    editingId.value = null;
    Object.assign(form, { ...blankMethod });
    form.clearErrors();
};

const save = () => {
    const options = { preserveScroll: true, forceFormData: true, onSuccess: cancelEdit };
    if (editingId.value) {
        form.post(route('admin.payment-methods.update', editingId.value), options);
    } else {
        form.post(route('admin.payment-methods.store'), options);
    }
};

const destroy = (method) => {
    if (window.confirm(`Delete payment method "${method.name}"?`)) {
        router.delete(route('admin.payment-methods.destroy', method.id), { preserveScroll: true });
    }
};
</script>

<template>
  <Head title="Payment Methods" />
  <PanelLayout>
    <div class="space-y-6">
      <h2 class="text-xl font-semibold text-gray-900">Payment Methods</h2>

      <div v-if="$page.props.flash?.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ $page.props.flash.success }}
      </div>

      <div class="bg-white p-5 rounded-lg shadow">
        <h3 class="font-semibold text-base mb-4">{{ editingId ? 'Edit payment method' : 'Add payment method' }}</h3>
        <div class="grid gap-4 md:grid-cols-3">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Name *</label>
            <input v-model="form.name" placeholder="e.g. eSewa, Bank Deposit" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
            <InputError :message="form.errors.name" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Bank Name</label>
            <input v-model="form.bank_name" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Account Name</label>
            <input v-model="form.account_name" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Account Number</label>
            <input v-model="form.account_number" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">QR Image</label>
            <input type="file" accept="image/*" class="w-full text-sm" @change="form.qr_image = $event.target.files[0]" />
            <InputError :message="form.errors.qr_image" class="mt-1" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
            <select v-model="form.status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-medium text-gray-700">Instructions</label>
            <textarea v-model="form.instructions" rows="2" placeholder="Shown to applicants on the payment help section" class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
          </div>
          <div class="flex items-end gap-2">
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing" @click="save">
              {{ editingId ? 'Update' : 'Add Method' }}
            </button>
            <button v-if="editingId" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="cancelEdit">Cancel</button>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 uppercase border-b">
              <th class="px-4 py-3">QR</th>
              <th class="px-4 py-3">Name</th>
              <th class="px-4 py-3">Bank / Account</th>
              <th class="px-4 py-3">Payments</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="method in methods" :key="method.id">
              <td class="px-4 py-3">
                <img v-if="method.qr_image_path" :src="route('payment-methods.qr', method.id)" :alt="`${method.name} QR`" class="h-12 w-12 rounded object-contain border border-gray-100" />
                <span v-else class="text-xs text-gray-400">none</span>
              </td>
              <td class="px-4 py-3 font-medium">{{ method.name }}</td>
              <td class="px-4 py-3 text-gray-600">
                {{ method.bank_name || '—' }}<span v-if="method.account_number"> · {{ method.account_number }}</span>
              </td>
              <td class="px-4 py-3">{{ method.transactions_count }}</td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="method.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'">
                  {{ method.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <button class="text-xs text-blue-700 underline mr-3" @click="startEdit(method)">Edit</button>
                <button class="text-xs text-red-700 underline" :disabled="method.transactions_count > 0" @click="destroy(method)">Delete</button>
              </td>
            </tr>
            <tr v-if="!methods.length">
              <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No payment methods configured yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </PanelLayout>
</template>
