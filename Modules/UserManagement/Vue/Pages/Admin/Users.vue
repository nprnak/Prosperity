<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
  users: Array,
  roles: Array,
  selectedRole: {
    type: String,
    default: '',
  },
});

const search = ref('');
const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingUser = ref(null);

const createForm = useForm({
  name: '',
  email: '',
  password: '',
  roles: [],
});

const editForm = useForm({
  name: '',
  email: '',
  password: '',
  roles: [],
});

// Password strength & show toggle
const createPasswordStrength = ref(0);
const editPasswordStrength = ref(0);
const showCreatePassword = ref(false);
const showEditPassword = ref(false);

watch(() => createForm.password, (val) => {
  let score = 0;
  if (!val) score = 0;
  if (val && val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  createPasswordStrength.value = score;
});

watch(() => editForm.password, (val) => {
  let score = 0;
  if (!val) score = 0;
  if (val && val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  editPasswordStrength.value = score;
});

const openCreateModal = () => {
  createForm.reset();
  createForm.clearErrors();
  createPasswordStrength.value = 0;
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
  editForm.roles = user.roles?.map(r => r.name) || [];
  editForm.clearErrors();
  editPasswordStrength.value = 0;
  showEditModal.value = true;
};

const closeEditModal = () => {
  showEditModal.value = false;
  editingUser.value = null;
};

const submitEdit = () => {
  if (!editingUser.value) return;
  editForm.patch(route('admin.users.update', editingUser.value.id), {
    onSuccess: () => {
      closeEditModal();
      editForm.password = '';
    },
  });
};

const deleteUser = (user) => {
  if (!confirm(`Delete user ${user.name}?`)) return;
  router.delete(route('admin.users.destroy', user.id));
};

const inputClass = 'w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-blue-500';
const primaryButtonClass = 'rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60';
const secondaryButtonClass = 'rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50';

const roleFilters = [
  { label: 'All Roles', value: '' },
  { label: 'Admin', value: 'admin' },
  { label: 'Finance Staff', value: 'finance_staff' },
  { label: 'Approver', value: 'approver' },
  { label: 'User', value: 'user' },
];

const applyRoleFilter = (role) => {
  router.get(route('admin.users'), role ? { role } : {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

// Client-side filtered users (search)
const filteredUsers = computed(() => {
  const q = (search.value || '').toLowerCase().trim();
  if (!q) return props.users || [];
  return (props.users || []).filter(u =>
    (u.name || '').toLowerCase().includes(q) ||
    (u.email || '').toLowerCase().includes(q) ||
    (u.roles || []).some(r => r.name.toLowerCase().includes(q))
  );
});

// Multi-select helper functions for tag-style UI
const addRoleTo = (form, roleName) => {
  if (!form.roles.includes(roleName)) {
    form.roles.push(roleName);
  }
};

const removeRoleFrom = (form, roleName) => {
  form.roles = form.roles.filter(r => r !== roleName);
};

const availableRolesFor = (form) => {
  return (props.roles || []).filter(r => !form.roles.includes(r.name));
};
</script>
<template>
  <Head title="Admin - Users Management" />
  <PanelLayout>
    <div class="p-6 bg-white rounded-lg shadow">
      <div class="flex items-center justify-between mb-6 gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900">Users Management</h2>
          <p class="text-sm text-gray-500">Manage users, assign multiple roles and control access.</p>
        </div>
        <div class="flex items-center gap-3">
          <div class="relative">
            <input v-model="search" type="search" placeholder="Search name, email or role" class="rounded-lg border border-gray-200 px-3 py-2 w-64 focus:border-blue-500" />
          </div>
          <button :class="primaryButtonClass" @click="openCreateModal">
            Add User
          </button>
        </div>
      </div>

      <p v-if="$page.props.errors.delete" class="px-4 py-2 mb-4 text-sm text-red-700 rounded bg-red-50">
        {{ $page.props.errors.delete }}
      </p>

      <div class="flex flex-wrap items-center gap-2 mb-4">
        <button
          v-for="filter in roleFilters"
          :key="filter.label"
          @click="applyRoleFilter(filter.value)"
          class="px-3 py-1.5 rounded-full text-xs font-semibold border"
          :class="selectedRole === filter.value
            ? 'border-blue-600 bg-blue-50 text-blue-700'
            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
        >
          {{ filter.label }}
        </button>
      </div>

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
            <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 text-sm text-gray-900">
                <div class="flex items-center gap-3">
                  <div class="flex items-center justify-center w-10 h-10 bg-indigo-100 text-indigo-700 rounded-full font-semibold">{{ (user.name || '').split(' ').map(n=>n[0]).join('').slice(0,2) }}</div>
                  <div>
                    <div class="font-medium">{{ user.name }}</div>
                    <div class="text-xs text-gray-500">Member since {{ new Date(user.created_at).toLocaleDateString() }}</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ user.email }}</td>
              <td class="px-6 py-4 text-sm">
                <div class="flex flex-wrap gap-2">
                  <span v-for="role in user.roles || []" :key="role.id" class="px-2 py-1 text-xs font-semibold text-blue-700 bg-blue-100 rounded-full">
                    {{ role.name }}
                  </span>
                  <span v-if="!(user.roles && user.roles.length)" class="px-2 py-1 text-xs font-semibold text-gray-600 bg-gray-100 rounded">N/A</span>
                </div>
              </td>
              <td class="px-6 py-4 text-sm">
                <span class="px-2 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded">Active</span>
              </td>
              <td class="px-6 py-4 space-x-2 text-sm">
                <button class="px-3 py-1 rounded bg-yellow-50 text-yellow-700 hover:bg-yellow-100" @click="openEditModal(user)">Edit</button>
                <button class="px-3 py-1 rounded bg-red-50 text-red-700 hover:bg-red-100" @click="deleteUser(user)">Delete</button>
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
            <InputLabel for="name" value="Full name" :required="true" />
            <TextInput id="name" type="text" placeholder="John Doe" class="mt-1 block w-full" v-model="createForm.name" />
            <InputError class="mt-2" :message="createForm.errors.name" />
          </div>

          <div>
            <InputLabel for="email" value="Email" :required="true" />
            <TextInput id="email" type="email" placeholder="you@company.com" class="mt-1 block w-full" v-model="createForm.email" />
            <InputError class="mt-2" :message="createForm.errors.email" />
          </div>

          <div>
            <InputLabel for="password" value="Password" :required="true" />
            <div class="relative mt-1">
              <TextInput :type="showCreatePassword ? 'text' : 'password'" id="password" placeholder="At least 8 characters" class="block w-full pr-10" v-model="createForm.password" />
              <button type="button" @click="showCreatePassword = !showCreatePassword" class="absolute inset-y-0 end-0 px-3 flex items-center text-gray-500" :aria-pressed="showCreatePassword">
                <svg v-if="!showCreatePassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M2.94 6.94a9.97 9.97 0 0114.12 0 9.97 9.97 0 01-14.12 0z" opacity=".2"/><path d="M10 4c3.866 0 7.09 2.69 8.483 6.363-.265.74-.63 1.44-1.073 2.07C15.09 14.31 11.866 17 8 17c-3.866 0-7.09-2.69-8.483-6.363C.782 9.897 1.147 9.197 1.412 8.456 2.91 5.21 6.045 4 10 4z"/><path d="M10 8a2 2 0 100 4 2 2 0 000-4z"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 010 1.414l-1 1A9.97 9.97 0 001.417 9.636C2.91 12.79 6.045 14 10 14c.92 0 1.807-.12 2.647-.344l1.873 1.873a1 1 0 001.414-1.414l-12-12a1 1 0 00-1.414 0z" clip-rule="evenodd"/></svg>
              </button>
            </div>
            <div class="mt-2">
              <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                <div :class="['h-full transition-all', createPasswordStrength >= 3 ? 'bg-green-500' : createPasswordStrength >= 2 ? 'bg-yellow-400' : 'bg-red-400']" :style="{ width: (createPasswordStrength/4*100) + '%' }"></div>
              </div>
              <p class="text-xs text-gray-600 mt-1">Use at least 8 characters, include uppercase, numbers, or symbols for a stronger password.</p>
            </div>
            <InputError class="mt-2" :message="createForm.errors.password" />
          </div>

          <div>
            <InputLabel for="roles" value="Roles" />
            <div class="mt-1">
              <div class="flex flex-wrap gap-2">
                <template v-for="roleName in createForm.roles" :key="roleName">
                  <span class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm">
                    {{ roleName }}
                    <button type="button" @click="removeRoleFrom(createForm, roleName)" class="ml-2 text-xs text-blue-500">&times;</button>
                  </span>
                </template>
                <span v-if="!createForm.roles.length" class="text-sm text-gray-500">No roles selected</span>
              </div>
              <div class="mt-2 flex gap-2 flex-wrap">
                <button v-for="r in availableRolesFor(createForm)" :key="r.id" @click.prevent="addRoleTo(createForm, r.name)" class="px-2 py-1 border rounded text-sm bg-white hover:bg-gray-50">{{ r.name }}</button>
              </div>
            </div>
            <InputError class="mt-2" :message="createForm.errors.roles || createForm.errors['roles.*']" />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" :class="secondaryButtonClass" @click="closeCreateModal">Cancel</button>
            <PrimaryButton :disabled="createForm.processing" class="" >
              Save
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showEditModal" class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/40">
      <div class="w-full max-w-lg p-6 bg-white rounded-lg shadow-xl">
        <h3 class="mb-4 text-xl font-semibold text-gray-900">Edit User</h3>
        <form @submit.prevent="submitEdit" class="space-y-4">
          <div>
            <InputLabel for="name" value="Full name" :required="true" />
            <TextInput id="name" type="text" placeholder="John Doe" class="mt-1 block w-full" v-model="editForm.name" />
            <InputError class="mt-2" :message="editForm.errors.name" />
          </div>

          <div>
            <InputLabel for="email" value="Email" :required="true" />
            <TextInput id="email" type="email" placeholder="you@company.com" class="mt-1 block w-full" v-model="editForm.email" />
            <InputError class="mt-2" :message="editForm.errors.email" />
          </div>

          <div>
            <InputLabel for="password" value="New Password (optional)" />
            <div class="relative mt-1">
              <TextInput :type="showEditPassword ? 'text' : 'password'" id="password_edit" placeholder="At least 8 characters" class="block w-full pr-10" v-model="editForm.password" />
              <button type="button" @click="showEditPassword = !showEditPassword" class="absolute inset-y-0 end-0 px-3 flex items-center text-gray-500" :aria-pressed="showEditPassword">
                <svg v-if="!showEditPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M2.94 6.94a9.97 9.97 0 0114.12 0 9.97 9.97 0 01-14.12 0z" opacity=".2"/><path d="M10 4c3.866 0 7.09 2.69 8.483 6.363-.265.74-.63 1.44-1.073 2.07C15.09 14.31 11.866 17 8 17c-3.866 0-7.09-2.69-8.483-6.363C.782 9.897 1.147 9.197 1.412 8.456 2.91 5.21 6.045 4 10 4z"/><path d="M10 8a2 2 0 100 4 2 2 0 000-4z"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 010 1.414l-1 1A9.97 9.97 0 001.417 9.636C2.91 12.79 6.045 14 10 14c.92 0 1.807-.12 2.647-.344l1.873 1.873a1 1 0 001.414-1.414l-12-12a1 1 0 00-1.414 0z" clip-rule="evenodd"/></svg>
              </button>
            </div>
            <div class="mt-2">
              <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                <div :class="['h-full transition-all', editPasswordStrength >= 3 ? 'bg-green-500' : editPasswordStrength >= 2 ? 'bg-yellow-400' : 'bg-red-400']" :style="{ width: (editPasswordStrength/4*100) + '%' }"></div>
              </div>
            </div>
            <InputError class="mt-2" :message="editForm.errors.password" />
          </div>

          <div>
            <InputLabel for="roles" value="Roles" />
            <div class="mt-1">
              <div class="flex flex-wrap gap-2">
                <template v-for="roleName in editForm.roles" :key="roleName">
                  <span class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm">
                    {{ roleName }}
                    <button type="button" @click="removeRoleFrom(editForm, roleName)" class="ml-2 text-xs text-blue-500">&times;</button>
                  </span>
                </template>
                <span v-if="!editForm.roles.length" class="text-sm text-gray-500">No roles selected</span>
              </div>
              <div class="mt-2 flex gap-2 flex-wrap">
                <button v-for="r in availableRolesFor(editForm)" :key="r.id" @click.prevent="addRoleTo(editForm, r.name)" class="px-2 py-1 border rounded text-sm bg-white hover:bg-gray-50">{{ r.name }}</button>
              </div>
            </div>
            <InputError class="mt-2" :message="editForm.errors.roles || editForm.errors['roles.*']" />
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
