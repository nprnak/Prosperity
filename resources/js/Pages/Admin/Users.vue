<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  users: Array,
  roles: Array,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingUser = ref(null);

const createForm = useForm({
  name: '',
  email: '',
  password: '',
  role: '',
});

const editForm = useForm({
  name: '',
  email: '',
  password: '',
  role: '',
});

const openCreateModal = () => {
  createForm.reset();
  createForm.clearErrors();
  showCreateModal.value = true;
};

const closeCreateModal = () => {
  showCreateModal.value = false;
};

const submitCreate = () => {
  createForm.post(route('admin.users.store'), {
    onSuccess: () => {
      closeCreateModal();
      createForm.reset();
    },
  });
};

const openEditModal = (user) => {
  editingUser.value = user;
  editForm.name = user.name;
  editForm.email = user.email;
  editForm.password = '';
  editForm.role = user.roles?.[0]?.name || '';
  editForm.clearErrors();
  showEditModal.value = true;
};

const closeEditModal = () => {
  showEditModal.value = false;
  editingUser.value = null;
};

const submitEdit = () => {
  if (!editingUser.value) {
    return;
  }

  editForm.patch(route('admin.users.update', editingUser.value.id), {
    onSuccess: () => {
      closeEditModal();
      editForm.password = '';
    },
  });
};

const deleteUser = (user) => {
  if (!confirm(`Delete user ${user.name}?`)) {
    return;
  }

  router.delete(route('admin.users.destroy', user.id));
};

const inputClass = 'w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-blue-500';
const primaryButtonClass = 'rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60';
const secondaryButtonClass = 'rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50';
</script>

<template>
  <Head title="Admin - Users Management" />
  <PanelLayout>
    <div class="p-6 bg-white rounded-lg shadow">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Users Management</h2>
        <button :class="primaryButtonClass" @click="openCreateModal">
          Add User
        </button>
      </div>

      <p v-if="$page.props.errors.delete" class="px-4 py-2 mb-4 text-sm text-red-700 rounded bg-red-50">
        {{ $page.props.errors.delete }}
      </p>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="border-b bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Name</th>
              <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Email</th>
              <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Role</th>
              <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
              <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 text-sm text-gray-900">{{ user.name }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ user.email }}</td>
              <td class="px-6 py-4 text-sm">
                <span class="px-2 py-1 text-xs font-semibold text-blue-700 bg-blue-100 rounded">
                  {{ user.roles?.[0]?.name || 'N/A' }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm">
                <span class="px-2 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded">Active</span>
              </td>
              <td class="px-6 py-4 space-x-2 text-sm">
                <button class="text-indigo-600 hover:text-indigo-900" @click="openEditModal(user)">Edit</button>
                <button class="text-red-600 hover:text-red-900" @click="deleteUser(user)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!users || users.length === 0" class="py-12 text-center">
        <p class="text-gray-500">No users found</p>
      </div>
    </div>

    <div v-if="showCreateModal" class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/40">
      <div class="w-full max-w-lg p-6 bg-white rounded-lg shadow-xl">
        <h3 class="mb-4 text-xl font-semibold text-gray-900">Add User</h3>
        <form @submit.prevent="submitCreate" class="space-y-4">
          <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">Name</label>
            <input v-model="createForm.name" type="text" :class="inputClass" />
            <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
          </div>

          <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">Email</label>
            <input v-model="createForm.email" type="email" :class="inputClass" />
            <p v-if="createForm.errors.email" class="mt-1 text-sm text-red-600">{{ createForm.errors.email }}</p>
          </div>

          <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">Password</label>
            <input v-model="createForm.password" type="password" :class="inputClass" />
            <p v-if="createForm.errors.password" class="mt-1 text-sm text-red-600">{{ createForm.errors.password }}</p>
          </div>

          <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">Role</label>
            <select v-model="createForm.role" :class="inputClass">
              <option value="">Select role</option>
              <option v-for="role in props.roles" :key="role.id" :value="role.name">{{ role.name }}</option>
            </select>
            <p v-if="createForm.errors.role" class="mt-1 text-sm text-red-600">{{ createForm.errors.role }}</p>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" :class="secondaryButtonClass" @click="closeCreateModal">Cancel</button>
            <button type="submit" :class="primaryButtonClass" :disabled="createForm.processing">
              Save
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showEditModal" class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/40">
      <div class="w-full max-w-lg p-6 bg-white rounded-lg shadow-xl">
        <h3 class="mb-4 text-xl font-semibold text-gray-900">Edit User</h3>
        <form @submit.prevent="submitEdit" class="space-y-4">
          <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">Name</label>
            <input v-model="editForm.name" type="text" :class="inputClass" />
            <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600">{{ editForm.errors.name }}</p>
          </div>

          <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">Email</label>
            <input v-model="editForm.email" type="email" :class="inputClass" />
            <p v-if="editForm.errors.email" class="mt-1 text-sm text-red-600">{{ editForm.errors.email }}</p>
          </div>

          <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">New Password (optional)</label>
            <input v-model="editForm.password" type="password" :class="inputClass" />
            <p v-if="editForm.errors.password" class="mt-1 text-sm text-red-600">{{ editForm.errors.password }}</p>
          </div>

          <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">Role</label>
            <select v-model="editForm.role" :class="inputClass">
              <option value="">Select role</option>
              <option v-for="role in props.roles" :key="role.id" :value="role.name">{{ role.name }}</option>
            </select>
            <p v-if="editForm.errors.role" class="mt-1 text-sm text-red-600">{{ editForm.errors.role }}</p>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" :class="secondaryButtonClass" @click="closeEditModal">Cancel</button>
            <button type="submit" :class="primaryButtonClass" :disabled="editForm.processing">
              Update
            </button>
          </div>
        </form>
      </div>
    </div>
  </PanelLayout>
</template>
