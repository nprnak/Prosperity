<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import JarScanInput from '../../Components/JarScanInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  lots: { type: Array, default: () => [] },
  recentDeliveries: { type: Object, default: () => ({ data: [] }) },
});

const activeLot = ref(props.lots.find((l) => l.status === 'dispatched') || props.lots[0] || null);

const customerQuery = ref('');
const customerResults = ref([]);
const searchCustomers = async () => {
  if (customerQuery.value.trim().length < 2) { customerResults.value = []; return; }
  const { data } = await axios.get(route('jar.lookup.customers'), { params: { q: customerQuery.value } });
  customerResults.value = data;
};
watch(customerQuery, searchCustomers);

const form = useForm({
  jar_customer_id: null,
  new_customer: { name: '', phone: '', type: 'household', route_area: '', default_price: null },
  jar_codes: [],
  unit_price: null,
  amount_paid: 0,
  payment_method: 'cash',
  returns: [],
  notes: '',
});

const selectedCustomer = ref(null);
const pickCustomer = (customer) => {
  selectedCustomer.value = customer;
  form.jar_customer_id = customer.id;
  form.unit_price = customer.default_price;
  customerQuery.value = customer.name;
  customerResults.value = [];
};
const useNewCustomer = ref(false);

const onScanDeliver = (code) => {
  if (!form.jar_codes.includes(code)) form.jar_codes.push(code);
};
const removeDeliverCode = (code) => { form.jar_codes = form.jar_codes.filter((c) => c !== code); };

const onScanReturn = (code) => {
  form.returns.push({ jar_code: code, is_new_registration: false, condition: 'good', notes: '' });
};
const addNewJarReturn = () => {
  form.returns.push({ jar_code: '', is_new_registration: true, condition: 'good', notes: '' });
};
const removeReturn = (index) => { form.returns.splice(index, 1); };

const submit = () => {
  if (!activeLot.value) return;
  form.post(route('jar.delivery.store', activeLot.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      form.jar_codes = [];
      form.returns = [];
      selectedCustomer.value = null;
      customerQuery.value = '';
      useNewCustomer.value = false;
    },
  });
};

const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
  <Head title="My Deliveries" />
  <PanelLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">My Deliveries</h2>
        <p class="mt-1 text-sm text-gray-700">Deliver filled jars and collect empties for your assigned lot.</p>
      </div>

      <div v-if="!lots.length" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
        No lot is currently assigned to you.
      </div>

      <template v-else>
        <div class="rounded-lg bg-white p-5 shadow">
          <label class="mb-1 block text-xs font-medium text-gray-700">Active lot</label>
          <select v-model="activeLot" class="w-full max-w-sm rounded-md border-gray-300 text-sm shadow-sm">
            <option v-for="lot in lots" :key="lot.id" :value="lot">
              {{ lot.lot_code }} — {{ lot.vehicle?.vehicle_number }} ({{ humanize(lot.status) }})
            </option>
          </select>
        </div>

        <form v-if="activeLot" class="space-y-6" @submit.prevent="submit">
          <div class="rounded-lg bg-white p-5 shadow">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Customer</h3>
            <div v-if="!useNewCustomer" class="relative">
              <input
                v-model="customerQuery"
                type="text"
                placeholder="Search household/dealer by name or phone"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm"
              />
              <ul v-if="customerResults.length" class="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">
                <li
                  v-for="c in customerResults" :key="c.id"
                  class="cursor-pointer px-3 py-2 text-sm hover:bg-gray-50"
                  @click="pickCustomer(c)"
                >
                  {{ c.name }} <span class="text-xs text-gray-500">({{ c.phone || 'no phone' }}) — {{ c.jars_outstanding }} owed</span>
                </li>
              </ul>
              <p v-if="selectedCustomer" class="mt-2 text-xs text-green-700">
                Selected: {{ selectedCustomer.name }} — outstanding: {{ selectedCustomer.jars_outstanding }} jar(s)
              </p>
              <button type="button" class="mt-2 text-xs font-semibold text-brand-700" @click="useNewCustomer = true">
                + New customer instead
              </button>
            </div>
            <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <input v-model="form.new_customer.name" type="text" placeholder="Name" class="rounded-md border-gray-300 text-sm shadow-sm" />
              <input v-model="form.new_customer.phone" type="text" placeholder="Phone" class="rounded-md border-gray-300 text-sm shadow-sm" />
              <select v-model="form.new_customer.type" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="household">Household</option>
                <option value="dealer">Dealer</option>
              </select>
              <input v-model="form.new_customer.route_area" type="text" placeholder="Route/area" class="rounded-md border-gray-300 text-sm shadow-sm" />
              <button type="button" class="text-xs font-semibold text-gray-600" @click="useNewCustomer = false">
                ← Search existing customer instead
              </button>
            </div>
          </div>

          <div class="rounded-lg bg-white p-5 shadow">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Deliver filled jars</h3>
            <JarScanInput placeholder="Scan a jar being delivered" @scan="onScanDeliver" />
            <ul v-if="form.jar_codes.length" class="mt-3 flex flex-wrap gap-2">
              <li v-for="code in form.jar_codes" :key="code" class="flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs text-blue-700">
                {{ code }}
                <button type="button" class="text-blue-400 hover:text-blue-700" @click="removeDeliverCode(code)">×</button>
              </li>
            </ul>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Unit price</label>
                <input v-model.number="form.unit_price" type="number" step="0.01" class="w-full rounded-md border-gray-300 text-sm shadow-sm" />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Amount paid</label>
                <input v-model.number="form.amount_paid" type="number" step="0.01" class="w-full rounded-md border-gray-300 text-sm shadow-sm" />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Payment method</label>
                <select v-model="form.payment_method" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                  <option value="cash">Cash</option>
                  <option value="due">On Credit (Due)</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>
          </div>

          <div class="rounded-lg bg-white p-5 shadow">
            <div class="mb-3 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-900">Collect empty jars</h3>
              <button type="button" class="text-xs font-semibold text-brand-700" @click="addNewJarReturn">+ Uncoded/new jar</button>
            </div>
            <JarScanInput placeholder="Scan a jar being returned" @scan="onScanReturn" />

            <table v-if="form.returns.length" class="mt-3 w-full text-sm">
              <thead>
                <tr class="border-b text-xs uppercase text-gray-500">
                  <th class="py-1 pr-2 text-left">Jar Code</th>
                  <th class="py-1 px-2 text-left">Condition</th>
                  <th class="py-1 px-2 text-left">Notes</th>
                  <th class="py-1 px-2 text-right"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(ret, index) in form.returns" :key="index" class="border-b last:border-0">
                  <td class="py-1 pr-2">
                    <span v-if="!ret.is_new_registration" class="font-medium">{{ ret.jar_code }}</span>
                    <span v-else class="text-xs italic text-gray-500">New/uncoded jar</span>
                  </td>
                  <td class="py-1 px-2">
                    <select v-model="ret.condition" class="rounded-md border-gray-300 text-xs shadow-sm">
                      <option value="good">Good</option>
                      <option value="damaged">Damaged</option>
                      <option value="leaking">Leaking</option>
                      <option value="cap_missing">Cap Missing</option>
                      <option value="other">Other</option>
                    </select>
                  </td>
                  <td class="py-1 px-2">
                    <input v-model="ret.notes" type="text" class="w-full rounded-md border-gray-300 text-xs shadow-sm" />
                  </td>
                  <td class="py-1 px-2 text-right">
                    <button type="button" class="text-red-500 hover:text-red-700" @click="removeReturn(index)">Remove</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center gap-3">
            <input v-model="form.notes" type="text" placeholder="Notes (optional)" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" />
            <PrimaryButton :disabled="form.processing || (!form.jar_customer_id && !form.new_customer.name)">
              Record Delivery
            </PrimaryButton>
          </div>
          <p v-if="Object.keys(form.errors).length" class="text-sm text-red-600">
            {{ Object.values(form.errors)[0] }}
          </p>
        </form>
      </template>

      <div class="rounded-lg bg-white p-6 shadow">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Recent deliveries</h3>
        <div v-if="recentDeliveries.data.length" class="overflow-x-auto">
          <table class="w-full min-w-[560px] text-sm">
            <thead>
              <tr class="border-b text-xs uppercase text-gray-500">
                <th class="py-2 pr-4 text-left">Customer</th>
                <th class="py-2 px-4 text-right">Delivered</th>
                <th class="py-2 px-4 text-right">Collected</th>
                <th class="py-2 px-4 text-left">Reconciliation</th>
                <th class="py-2 px-4 text-right">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in recentDeliveries.data" :key="d.id" class="border-b last:border-0">
                <td class="py-2 pr-4">{{ d.customer?.name }}</td>
                <td class="py-2 px-4 text-right">{{ d.filled_jars_delivered_count }}</td>
                <td class="py-2 px-4 text-right">{{ d.empty_jars_collected_count }}</td>
                <td class="py-2 px-4">{{ humanize(d.reconciliation_status) }}</td>
                <td class="py-2 px-4 text-right">{{ d.total_amount }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-8 text-center text-sm text-gray-500">No deliveries recorded yet.</p>
      </div>
    </div>
  </PanelLayout>
</template>
