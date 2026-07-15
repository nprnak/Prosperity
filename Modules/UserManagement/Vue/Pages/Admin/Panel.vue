<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
  stats: Object,
});
</script>

<template>
  <Head title="Admin Panel - Dashboard" />
  <PanelLayout>
    <!-- Main Stats Grid -->
    <div class="space-y-6">
      <h2 class="text-3xl font-bold text-gray-900">Admin Dashboard</h2>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
        <div class="p-6 border border-blue-200 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100">
          <div class="mb-2 text-2xl text-blue-600">👥</div>
          <h3 class="text-sm font-medium text-gray-600">Total Users</h3>
          <p class="mt-2 text-4xl font-bold text-blue-600">{{ stats?.totalUsers || 0 }}</p>
          <Link :href="route('admin.users')" class="inline-block mt-2 text-sm text-blue-600 hover:text-blue-800">Manage Users →</Link>
        </div>

        <div class="p-6 border border-green-200 rounded-lg bg-gradient-to-br from-green-50 to-green-100">
          <div class="mb-2 text-2xl text-green-600">📝</div>
          <h3 class="text-sm font-medium text-gray-600">Applications</h3>
          <p class="mt-2 text-4xl font-bold text-green-600">{{ stats?.totalApplications || 0 }}</p>
          <Link :href="route('admin.applications')" class="inline-block mt-2 text-sm text-green-600 hover:text-green-800">View All →</Link>
        </div>

        <div class="p-6 border border-orange-200 rounded-lg bg-gradient-to-br from-orange-50 to-orange-100">
          <div class="mb-2 text-2xl text-orange-600">⏳</div>
          <h3 class="text-sm font-medium text-gray-600">Pending Approvals</h3>
          <p class="mt-2 text-4xl font-bold text-orange-600">{{ stats?.pendingApplications || 0 }}</p>
          <Link :href="route('approver.dashboard')" class="inline-block mt-2 text-sm text-orange-600 hover:text-orange-800">Review →</Link>
        </div>

        <div class="p-6 border border-purple-200 rounded-lg bg-gradient-to-br from-purple-50 to-purple-100">
          <div class="mb-2 text-2xl text-purple-600">💰</div>
          <h3 class="text-sm font-medium text-gray-600">Pending Payments</h3>
          <p class="mt-2 text-4xl font-bold text-purple-600">{{ stats?.pendingPayments || 0 }}</p>
          <Link :href="route('finance.dashboard')" class="inline-block mt-2 text-sm text-purple-600 hover:text-purple-800">Verify →</Link>
        </div>
      </div>

      <!-- Detailed Stats Cards -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <!-- Financial Overview -->
        <div class="p-6 bg-white rounded-lg shadow">
          <h3 class="mb-4 text-xl font-bold text-gray-900">💳 Financial Overview</h3>
          <div class="space-y-3">
            <div class="flex items-center justify-between pb-3 border-b">
              <span class="text-gray-600">Total Capital Raised</span>
              <span class="text-2xl font-bold text-green-600">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ stats?.capitalRaised || 0 }}</span>
            </div>
            <div class="flex items-center justify-between pb-3 border-b">
              <span class="text-gray-600">Verified Payments</span>
              <span class="text-lg font-semibold text-gray-900">{{ stats?.verifiedPayments || 0 }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-600">Pending Verification</span>
              <span class="text-lg font-semibold text-orange-600">{{ stats?.pendingPayments || 0 }}</span>
            </div>
          </div>
        </div>

        <!-- Allotment Overview -->
        <div class="p-6 bg-white rounded-lg shadow">
          <h3 class="mb-4 text-xl font-bold text-gray-900">📊 Shares Allotment</h3>
          <div class="space-y-3">
            <div class="flex items-center justify-between pb-3 border-b">
              <span class="text-gray-600">Total Shares Allotted</span>
              <span class="text-2xl font-bold text-indigo-600">{{ stats?.totalSharesAllotted || 0 }}</span>
            </div>
            <div class="flex items-center justify-between pb-3 border-b">
              <span class="text-gray-600">Total Allotments</span>
              <span class="text-lg font-semibold text-gray-900">{{ stats?.totalAllotments || 0 }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-600">Average per Application</span>
              <span class="text-lg font-semibold text-blue-600">{{ stats?.averageSharesPerApp || 0 }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="p-6 bg-white rounded-lg shadow">
        <h3 class="mb-4 text-xl font-bold text-gray-900">👤 Role Distribution</h3>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <Link :href="route('admin.users', { role: 'admin' })" class="p-4 border border-gray-200 rounded-lg hover:bg-slate-50">
            <div class="text-xs text-gray-500 uppercase">Admin</div>
            <div class="mt-2 text-2xl font-bold text-slate-800">{{ stats?.adminUsers || 0 }}</div>
          </Link>
          <Link :href="route('admin.users', { role: 'finance_staff' })" class="p-4 border border-gray-200 rounded-lg hover:bg-blue-50">
            <div class="text-xs text-gray-500 uppercase">Finance Staff</div>
            <div class="mt-2 text-2xl font-bold text-blue-700">{{ stats?.financeUsers || 0 }}</div>
          </Link>
          <Link :href="route('admin.users', { role: 'approver' })" class="p-4 border border-gray-200 rounded-lg hover:bg-amber-50">
            <div class="text-xs text-gray-500 uppercase">Approver</div>
            <div class="mt-2 text-2xl font-bold text-amber-700">{{ stats?.approverUsers || 0 }}</div>
          </Link>
          <Link :href="route('admin.users', { role: 'user' })" class="p-4 border border-gray-200 rounded-lg hover:bg-emerald-50">
            <div class="text-xs text-gray-500 uppercase">Applicant User</div>
            <div class="mt-2 text-2xl font-bold text-emerald-700">{{ stats?.applicantUsers || 0 }}</div>
          </Link>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="p-6 bg-white rounded-lg shadow">
        <h3 class="mb-4 text-xl font-bold text-gray-900">📌 Quick Actions</h3>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
          <Link :href="route('admin.applications')" class="p-4 transition border border-gray-300 rounded-lg hover:bg-indigo-50 hover:border-indigo-300">
            <div class="mb-2 text-xl">📋</div>
            <div class="font-semibold text-gray-900">View Applications</div>
            <div class="mt-1 text-xs text-gray-500">Review all submissions</div>
          </Link>

          <Link :href="route('approver.dashboard')" class="p-4 transition border border-gray-300 rounded-lg hover:bg-amber-50 hover:border-amber-300">
            <div class="mb-2 text-xl">✅</div>
            <div class="font-semibold text-gray-900">Approve Applications</div>
            <div class="mt-1 text-xs text-gray-500">Open approver workflow as admin</div>
          </Link>

          <Link :href="route('finance.dashboard')" class="p-4 transition border border-gray-300 rounded-lg hover:bg-green-50 hover:border-green-300">
            <div class="mb-2 text-xl">💰</div>
            <div class="font-semibold text-gray-900">Verify Payments</div>
            <div class="mt-1 text-xs text-gray-500">{{ stats?.pendingPayments || 0 }} pending</div>
          </Link>

          <Link :href="route('admin.allotments')" class="p-4 transition border border-gray-300 rounded-lg hover:bg-blue-50 hover:border-blue-300">
            <div class="mb-2 text-xl">📊</div>
            <div class="font-semibold text-gray-900">View Allotments</div>
            <div class="mt-1 text-xs text-gray-500">Share distributions</div>
          </Link>

          <Link :href="route('admin.reports')" class="p-4 transition border border-gray-300 rounded-lg hover:bg-purple-50 hover:border-purple-300">
            <div class="mb-2 text-xl">📈</div>
            <div class="font-semibold text-gray-900">Reports & Analytics</div>
            <div class="mt-1 text-xs text-gray-500">View detailed reports</div>
          </Link>
        </div>
      </div>

      <div class="p-6 bg-white rounded-lg shadow">
        <h3 class="mb-4 text-xl font-bold text-gray-900">🧩 Role Workspaces (Admin Only)</h3>
        <p class="mb-4 text-sm text-gray-600">
          Finance staff and approver operations are currently managed from admin access. You can split route access by role later without changing user records.
        </p>
        <div class="mb-4">
          <Link :href="route('admin.roles.hub')" class="inline-flex rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">
            Open Admin Role Hub
          </Link>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <Link :href="route('admin.users', { role: 'finance_staff' })" class="p-4 transition border border-gray-300 rounded-lg hover:bg-blue-50 hover:border-blue-300">
            <div class="font-semibold text-gray-900">Finance Staff Users</div>
            <div class="mt-1 text-xs text-gray-500">Create and update payment-verification accounts</div>
          </Link>
          <Link :href="route('admin.users', { role: 'approver' })" class="p-4 transition border border-gray-300 rounded-lg hover:bg-amber-50 hover:border-amber-300">
            <div class="font-semibold text-gray-900">Approver Users</div>
            <div class="mt-1 text-xs text-gray-500">Manage approval users and reviewer accounts</div>
          </Link>
          <Link :href="route('admin.users', { role: 'user' })" class="p-4 transition border border-gray-300 rounded-lg hover:bg-emerald-50 hover:border-emerald-300">
            <div class="font-semibold text-gray-900">Applicant Users</div>
            <div class="mt-1 text-xs text-gray-500">Manage public applicant accounts</div>
          </Link>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
