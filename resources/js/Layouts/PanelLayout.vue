<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useSidebar } from '@/Composables/useSidebar';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
  HomeIcon,
  PuzzlePieceIcon,
  UsersIcon,
  UserGroupIcon,
  BuildingOffice2Icon,
  DocumentTextIcon,
  BanknotesIcon,
  ClipboardDocumentCheckIcon,
  PresentationChartLineIcon,
  BuildingLibraryIcon,
  ClockIcon,
  WrenchScrewdriverIcon,
  PaperAirplaneIcon,
  UserCircleIcon,
  IdentificationIcon,
  DocumentPlusIcon,
  QueueListIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const { expanded, mobileOpen, closeMobile } = useSidebar();

// The seeded role is super_admin; there has never been one called 'admin'.
const isAdmin = computed(() =>
  page.props.auth?.user?.roles?.some((role) => role.name === 'super_admin') ?? false,
);

const permissions = computed(() => page.props.auth?.permissions || []);
// A permission can be a single string or an array meaning "any of these" —
// the KYC review queue is gated the same way its route is: verify, review,
// or approve on profiles, whichever stage the user holds.
const can = (permission) => {
  if (!permission) return true;
  return Array.isArray(permission)
    ? permission.some((p) => permissions.value.includes(p))
    : permissions.value.includes(permission);
};

// Staff items appear only when the user holds the matching permission —
// admins hold every permission, so they still see the full menu.
// Who can browse the Applicant List. Application Verifier is deliberately
// left out: they have their own purpose-built "Add Application" picker for
// finding who to file for, so the generic roster would just be a second,
// redundant way to reach the same thing.
const APPLICANT_LIST_PERMISSIONS = [
  'profile.verify', 'profile.review', 'profile.approve',
  'application.review', 'application.approve',
  'application.view-any',
];

const staffMenuItems = [
  { label: 'Dashboard', icon: HomeIcon, route: 'admin.dashboard', startsWith: '/admin/dashboard', permission: 'dashboard.view-admin' },
  { label: 'Role Hub', icon: PuzzlePieceIcon, route: 'admin.roles.hub', startsWith: '/admin/roles/hub', permission: 'user.manage' },
  { label: 'Users', icon: UsersIcon, route: 'admin.users', startsWith: '/admin/users', permission: 'user.manage' },
  { label: 'Focal Persons', icon: UserGroupIcon, route: 'admin.focal-persons', startsWith: '/admin/focal-persons', permission: 'focal-person.manage' },
  { label: 'Companies', icon: BuildingOffice2Icon, route: 'admin.companies', startsWith: '/admin/companies', permission: 'company.manage' },
  { label: 'Applications', icon: DocumentTextIcon, route: 'admin.applications', startsWith: '/admin/applications', permission: 'application.view-any' },
  { label: 'Payment Methods', icon: BanknotesIcon, route: 'admin.payment-methods', startsWith: '/admin/payment-methods', permission: 'payment-method.manage' },
  // The roster of approved applicants, shared by both review chains.
  { label: 'Applicant List', icon: QueueListIcon, route: 'applicants.index', startsWith: '/applicants/list', permission: APPLICANT_LIST_PERMISSIONS },
  // The KYC chain (profile.*) is separate from the application chain
  // (application.*) below — a user can hold either or both, so this is
  // gated on any of the three profile stages rather than one.
  { label: 'Profile Review', icon: IdentificationIcon, route: 'applicants.review', startsWith: '/applicants/review', permission: ['profile.verify', 'profile.review', 'profile.approve'] },
  // Paper-based KYC entry moved onto the Applicant List page itself (an
  // "Add Applicant" button at its top) rather than living in the sidebar.
  // The three application stages share one queue too, the same way Profile
  // Review does — whichever of the three permissions a user holds gets them
  // in, and the page itself shows only what's actually theirs to act on.
  { label: 'Application Review', icon: ClipboardDocumentCheckIcon, route: 'applications.review', startsWith: '/applications/review', permission: ['application.verify', 'application.review', 'application.approve'] },
  // The Application Verifier's own way to find who to file for — kept
  // separate from the Applicant List, which they don't otherwise need.
  { label: 'Add Application', icon: DocumentPlusIcon, route: 'applications.add.pick', startsWith: '/applications/add', permission: 'application.verify' },
  { label: 'Reports', icon: PresentationChartLineIcon, route: 'admin.reports', startsWith: '/admin/reports', permission: 'report.view' },
  { label: 'Site Settings', icon: BuildingLibraryIcon, route: 'admin.settings', startsWith: '/admin/settings', permission: 'settings.manage' },
  { label: 'Activity Log', icon: ClockIcon, route: 'admin.logs', startsWith: '/admin/logs', permission: 'audit.view' },
];

const isApplicant = computed(() => page.props.auth?.user?.roles?.some((role) => role.name === 'applicant') ?? false);

const personalMenuItems = computed(() => {
  // Personal login-details/password/signature page — the route itself
  // has no permission gate, so every authenticated user should see it.
  // (Not to be confused with the admin-only Admin/Site Settings above,
  // which really are gated on settings.manage.)
  const base = [
    { label: 'Settings', icon: WrenchScrewdriverIcon, route: 'settings.edit', startsWith: '/settings', permission: null },
  ];

  if (isApplicant.value) {
    // Only applicants hold a KYC profile to manage — staff (verifiers
    // included) have no Profile record, so the link is meaningless for them.
    base.unshift(
      { label: 'Share Application', icon: PaperAirplaneIcon, route: 'applications.wizard', startsWith: '/applications', permission: 'application.submit' },
      { label: 'Profile', icon: UserCircleIcon, route: 'profile.edit', startsWith: '/profile', permission: null },
    );
  }

  return base;
});

const visibleStaffItems = computed(() => staffMenuItems.filter((item) => can(item.permission)));
const isStaff = computed(() => visibleStaffItems.value.length > 0);

const menuItems = computed(() => {
  const personal = personalMenuItems.value.filter((item) => item.permission ? can(item.permission) : true);

  if (isStaff.value) {
    return [...visibleStaffItems.value, ...personal];
  }

  return [
    { label: 'Dashboard', icon: HomeIcon, route: 'dashboard', startsWith: '/dashboard' },
    ...personal,
  ];
});

const panelHeading = computed(() => (isAdmin.value ? 'Admin Panel' : isStaff.value ? 'Staff Panel' : 'User Panel'));
const panelSubheading = computed(() =>
  isAdmin.value
    ? 'Access admin modules and user-side options'
    : isStaff.value
      ? 'Modules available to your role'
      : 'Manage settings',
);

const isActive = (item) => page.url.startsWith(item.startsWith);
</script>

<template>
  <AuthenticatedLayout>
    <template v-if="$slots.header" #header>
      <slot name="header" />
    </template>

    <div class="relative min-h-[calc(100vh-4rem)]">
      <!-- Mobile backdrop -->
      <div
        v-if="mobileOpen"
        class="fixed inset-0 z-30 bg-slate-900/50 md:hidden"
        @click="closeMobile"
      />

      <!-- Sidebar -->
      <aside
        :class="[
          'fixed inset-y-0 top-16 z-40 flex h-[calc(100vh-4rem)] flex-col overflow-y-auto border-r border-slate-200 bg-white shadow-sm transition-all duration-200 ease-in-out',
          mobileOpen ? 'translate-x-0' : '-translate-x-full',
          'md:translate-x-0',
          expanded ? 'w-72 md:w-64' : 'w-72 md:w-16',
        ]"
      >
        <nav class="flex-1 space-y-1 px-2 py-4">
          <Link
            v-for="item in menuItems"
            :key="item.route"
            :href="route(item.route)"
            :title="item.label"
            :class="[
              'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition',
              isActive(item)
                ? 'bg-brand-50 font-semibold text-brand'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
              !expanded && 'md:justify-center',
            ]"
            @click="closeMobile"
          >
            <component :is="item.icon" class="h-5 w-5 shrink-0" />
            <span :class="!expanded && 'md:hidden'">{{ item.label }}</span>
          </Link>
        </nav>
      </aside>

      <!-- Content -->
      <div :class="['transition-all duration-200 ease-in-out', expanded ? 'md:ml-64' : 'md:ml-16']">
        <div class="border-b border-slate-200 bg-white shadow-sm">
          <div class="flex items-center justify-between px-4 py-3 sm:px-6">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Prosperity CMS</p>
              <h1 class="text-base font-bold text-slate-900 sm:text-lg">{{ panelHeading }}</h1>
            </div>
            <div class="hidden rounded border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700 sm:block">
              {{ panelSubheading }}
            </div>
          </div>
        </div>

        <div class="px-4 py-6 sm:px-6">
          <slot />
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
