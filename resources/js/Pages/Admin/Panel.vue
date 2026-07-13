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

      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-6 border border-blue-200">
          <div class="text-blue-600 text-2xl mb-2">👥</div>
          <h3 class="text-gray-600 text-sm font-medium">Total Users</h3>
          <p class="text-4xl font-bold text-blue-600 mt-2">{{ stats?.totalUsers || 0 }}</p>
          <Link href="#" class="text-blue-600 text-sm hover:text-blue-800 mt-2 inline-block">Manage Users →</Link>
        </div>

        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-6 border border-green-200">
          <div class="text-green-600 text-2xl mb-2">📝</div>
          <h3 class="text-gray-600 text-sm font-medium">Applications</h3>
          <p class="text-4xl font-bold text-green-600 mt-2">{{ stats?.totalApplications || 0 }}</p>
          <Link href="#" class="text-green-600 text-sm hover:text-green-800 mt-2 inline-block">View All →</Link>
        </div>

        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg p-6 border border-orange-200">
          <div class="text-orange-600 text-2xl mb-2">⏳</div>
          <h3 class="text-gray-600 text-sm font-medium">Pending Approvals</h3>
          <p class="text-4xl font-bold text-orange-600 mt-2">{{ stats?.pendingApplications || 0 }}</p>
          <Link href="#" class="text-orange-600 text-sm hover:text-orange-800 mt-2 inline-block">Review →</Link>
        </div>

        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-6 border border-purple-200">
          <div class="text-purple-600 text-2xl mb-2">💰</div>
          <h3 class="text-gray-600 text-sm font-medium">Pending Payments</h3>
          <p class="text-4xl font-bold text-purple-600 mt-2">{{ stats?.pendingPayments || 0 }}</p>
          <Link href="#" class="text-purple-600 text-sm hover:text-purple-800 mt-2 inline-block">Verify →</Link>
        </div>
      </div>

      <!-- Detailed Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Financial Overview -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-xl font-bold text-gray-900 mb-4">💳 Financial Overview</h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Total Capital Raised</span>
              <span class="text-2xl font-bold text-green-600">Rs. {{ stats?.capitalRaised || 0 }}</span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Verified Payments</span>
              <span class="text-lg font-semibold text-gray-900">{{ stats?.verifiedPayments || 0 }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-gray-600">Pending Verification</span>
              <span class="text-lg font-semibold text-orange-600">{{ stats?.pendingPayments || 0 }}</span>
            </div>
          </div>
        </div>

        <!-- Allotment Overview -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-xl font-bold text-gray-900 mb-4">📊 Shares Allotment</h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Total Shares Allotted</span>
              <span class="text-2xl font-bold text-indigo-600">{{ stats?.totalSharesAllotted || 0 }}</span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b">
              <span class="text-gray-600">Total Allotments</span>
              <span class="text-lg font-semibold text-gray-900">{{ stats?.totalAllotments || 0 }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-gray-600">Average per Application</span>
              <span class="text-lg font-semibold text-blue-600">{{ stats?.averageSharesPerApp || 0 }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4">📌 Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Link href="#" class="p-4 border border-gray-300 rounded-lg hover:bg-indigo-50 hover:border-indigo-300 transition">
            <div class="text-xl mb-2">📋</div>
            <div class="font-semibold text-gray-900">View Applications</div>
            <div class="text-xs text-gray-500 mt-1">Review all submissions</div>
          </Link>

          <Link href="#" class="p-4 border border-gray-300 rounded-lg hover:bg-green-50 hover:border-green-300 transition">
            <div class="text-xl mb-2">💰</div>
            <div class="font-semibold text-gray-900">Verify Payments</div>
            <div class="text-xs text-gray-500 mt-1">{{ stats?.pendingPayments || 0 }} pending</div>
          </Link>

          <Link href="#" class="p-4 border border-gray-300 rounded-lg hover:bg-blue-50 hover:border-blue-300 transition">
            <div class="text-xl mb-2">📊</div>
            <div class="font-semibold text-gray-900">View Allotments</div>
            <div class="text-xs text-gray-500 mt-1">Share distributions</div>
          </Link>

          <Link href="#" class="p-4 border border-gray-300 rounded-lg hover:bg-purple-50 hover:border-purple-300 transition">
            <div class="text-xl mb-2">📈</div>
            <div class="font-semibold text-gray-900">Reports & Analytics</div>
            <div class="text-xs text-gray-500 mt-1">View detailed reports</div>
          </Link>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
