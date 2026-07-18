<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
  roleUsers: Object,
  workflowCounts: Object,
});

const roleMeta = {
  admin: {
    title: 'Admin Team',
    color: 'slate',
    usersRoute: () => route('admin.users', { role: 'admin' }),
    actionLabel: 'Manage Admin Users',
  },
  finance_staff: {
    title: 'Finance Staff Team',
    color: 'blue',
    usersRoute: () => route('admin.users', { role: 'finance_staff' }),
    actionLabel: 'Manage Finance Users',
  },
  approver: {
    title: 'Approver Team',
    color: 'amber',
    usersRoute: () => route('admin.users', { role: 'approver' }),
    actionLabel: 'Manage Approver Users',
  },
  applicant: {
    title: 'Applicant Users',
    color: 'emerald',
    usersRoute: () => route('admin.users', { role: 'applicant' }),
    actionLabel: 'Manage Applicant Users',
  },
};

const cardClass = (color) => {
  const styles = {
    slate: 'border-slate-200 bg-slate-50/50',
    blue: 'border-blue-200 bg-blue-50/50',
    amber: 'border-amber-200 bg-amber-50/50',
    emerald: 'border-emerald-200 bg-emerald-50/50',
  };

  return styles[color] || styles.slate;
};
</script>

<template>
  <Head title="Admin - Role Hub" />
  <PanelLayout>
    <div class="space-y-6">
      <div class="p-6 rounded-lg bg-gradient-to-r from-slate-800 via-slate-700 to-blue-700 text-white">
        <h2 class="text-2xl font-bold">Admin Managed Role Hub</h2>
        <p class="mt-2 text-sm text-slate-100">
          Centralized role operations are handled from admin. Separate route-level role restrictions can be applied later without changing user assignments.
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <Link :href="route('finance.dashboard')" class="rounded-lg border border-blue-200 bg-white p-4 hover:bg-blue-50">
          <p class="text-xs uppercase text-gray-500">Finance Queue</p>
          <p class="mt-1 text-2xl font-bold text-blue-700">{{ workflowCounts?.pendingFinance || 0 }}</p>
          <p class="text-xs text-gray-600 mt-1">Pending payment verifications</p>
        </Link>
        <Link :href="route('approver.dashboard')" class="rounded-lg border border-amber-200 bg-white p-4 hover:bg-amber-50">
          <p class="text-xs uppercase text-gray-500">Approver Queue</p>
          <p class="mt-1 text-2xl font-bold text-amber-700">{{ workflowCounts?.pendingApprovals || 0 }}</p>
          <p class="text-xs text-gray-600 mt-1">Applications ready for approval</p>
        </Link>
        <Link :href="route('admin.allotments')" class="rounded-lg border border-indigo-200 bg-white p-4 hover:bg-indigo-50">
          <p class="text-xs uppercase text-gray-500">Allotment Queue</p>
          <p class="mt-1 text-2xl font-bold text-indigo-700">{{ workflowCounts?.pendingAllotments || 0 }}</p>
          <p class="text-xs text-gray-600 mt-1">Approved applications awaiting allotment</p>
        </Link>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div
          v-for="(users, roleKey) in roleUsers"
          :key="roleKey"
          class="rounded-lg border p-4"
          :class="cardClass(roleMeta[roleKey]?.color)"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">{{ roleMeta[roleKey]?.title || roleKey }}</h3>
              <p class="text-xs text-gray-600 mt-1">Total users: {{ users?.length || 0 }}</p>
            </div>
            <Link
              :href="roleMeta[roleKey]?.usersRoute()"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
            >
              {{ roleMeta[roleKey]?.actionLabel || 'Manage Users' }}
            </Link>
          </div>

          <div class="mt-3 space-y-2" v-if="users?.length">
            <div v-for="u in users.slice(0, 5)" :key="u.id" class="rounded-md bg-white/80 border border-gray-200 px-3 py-2">
              <p class="text-sm font-medium text-gray-900">{{ u.name }}</p>
              <p class="text-xs text-gray-600">{{ u.email }}</p>
            </div>
            <p v-if="users.length > 5" class="text-xs text-gray-600">+{{ users.length - 5 }} more</p>
          </div>
          <p v-else class="mt-3 text-xs text-gray-600">No users assigned yet.</p>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
