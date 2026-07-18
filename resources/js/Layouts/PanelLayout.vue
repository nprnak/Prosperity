<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const isAdmin = computed(() =>
  page.props.auth?.user?.roles?.some((role) => role.name === 'admin') ?? false,
);

const permissions = computed(() => page.props.auth?.permissions || []);
const can = (permission) => !permission || permissions.value.includes(permission);

// Staff items appear only when the user holds the matching permission —
// admins hold every permission, so they still see the full menu.
const staffMenuItems = [
  { label: 'Dashboard', icon: '📊', route: 'admin.dashboard', startsWith: '/admin/dashboard', permission: 'dashboard.view-admin' },
  { label: 'Role Hub', icon: '🧩', route: 'admin.roles.hub', startsWith: '/admin/roles/hub', permission: 'user.manage' },
  { label: 'Users', icon: '👥', route: 'admin.users', startsWith: '/admin/users', permission: 'user.manage' },
  { label: 'Companies', icon: '🏢', route: 'admin.companies', startsWith: '/admin/companies', permission: 'company.manage' },
  { label: 'Applications', icon: '📝', route: 'admin.applications', startsWith: '/admin/applications', permission: 'application.view-any' },
  { label: 'Payments', icon: '💳', route: 'admin.payments', startsWith: '/admin/payments', permission: 'payment.view-any' },
  { label: 'Payment Methods', icon: '🏦', route: 'admin.payment-methods', startsWith: '/admin/payment-methods', permission: 'payment-method.manage' },
  { label: 'Approvals', icon: '✅', route: 'approver.dashboard', startsWith: '/approver', permission: 'application.approve' },
  { label: 'Allotments', icon: '📋', route: 'admin.allotments', startsWith: '/admin/allotments', permission: 'allotment.manage' },
  { label: 'Reports', icon: '📈', route: 'admin.reports', startsWith: '/admin/reports', permission: 'report.view' },
  { label: 'Admin Settings', icon: '⚙️', route: 'admin.credentials', startsWith: '/admin/credentials', permission: 'settings.manage' },
  { label: 'Site Settings', icon: '🏛️', route: 'admin.settings', startsWith: '/admin/settings', permission: 'settings.manage' },
  { label: 'Activity Log', icon: '🔍', route: 'admin.logs', startsWith: '/admin/logs', permission: 'audit.view' },
];

const personalMenuItems = [
  { label: 'Share Application', icon: '🧾', route: 'applications.wizard', startsWith: '/applications', permission: 'application.submit' },
  { label: 'Profile', icon: '👤', route: 'profile.edit', startsWith: '/profile' },
  { label: 'Settings', icon: '🛠️', route: 'settings.edit', startsWith: '/settings' },
];

const visibleStaffItems = computed(() => staffMenuItems.filter((item) => can(item.permission)));
const isStaff = computed(() => visibleStaffItems.value.length > 0);

const menuItems = computed(() => {
  if (isStaff.value) {
    return [...visibleStaffItems.value, ...personalMenuItems.filter((item) => can(item.permission))];
  }

  return [
    { label: 'Dashboard ', icon: '🏠', route: 'dashboard', startsWith: '/dashboard' },
    ...personalMenuItems.filter((item) => can(item.permission)),
  ];
});

const panelHeading = computed(() => (isAdmin.value ? 'Admin Panel' : isStaff.value ? 'Staff Panel' : 'User Panel'));
const panelSubheading = computed(() =>
  isAdmin.value
    ? 'Access admin modules and user-side options'
    : isStaff.value
      ? 'Modules available to your role'
      : 'Manage settings and share application',
);
const panelHeaderClass = 'bg-gradient-to-r from-blue-700 via-blue-600 to-sky-500';
const activeClass = computed(() =>
  isAdmin.value
    ? 'bg-blue-50 border-l-4 border-blue-700 font-semibold text-blue-800'
    : 'bg-sky-50 border-l-4 border-sky-700 font-semibold text-sky-800',
);

const isActive = (item) => page.url.startsWith(item.startsWith);
</script>

<template>
  <AuthenticatedLayout>
    <div class="min-h-screen bg-slate-100/70">
      <div :class="['border-b shadow-sm', panelHeaderClass]">
        <div class="flex items-center justify-between px-4 py-3 mx-auto max-w-7xl sm:px-6">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-100">Prosperity CMS</p>
            <h1 class="text-base font-bold text-white sm:text-lg">{{ panelHeading }}</h1>
          </div>
          <div class="px-3 py-1 text-xs text-white border rounded border-white/30 bg-white/10">
            {{ panelSubheading }}
          </div>
        </div>
      </div>

      <div class="px-4 py-6 mx-auto max-w-7xl sm:px-6">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
        <div class="md:col-span-1">
          <div class="overflow-hidden bg-white border rounded-md shadow-sm border-slate-200">
            <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
              <h2 class="text-sm font-semibold tracking-wide uppercase text-slate-700">Navigation</h2>
            </div>
            <nav class="divide-y divide-slate-100">
              <Link
                v-for="item in menuItems"
                :key="item.route"
                :href="route(item.route)"
                :class="[
                  'flex items-center px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 transition border-l-4 border-transparent',
                  isActive(item) ? activeClass : '',
                ]"
              >
                <span class="inline-flex items-center justify-center w-6 h-6 mr-3 text-xs rounded bg-slate-100">{{ item.icon }}</span>
                <span class="font-medium">{{ item.label }}</span>
              </Link>
            </nav>
          </div>
        </div>

        <div class="md:col-span-3">
          <slot />
        </div>
      </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
