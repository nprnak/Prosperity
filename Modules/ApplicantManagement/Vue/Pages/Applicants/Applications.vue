<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon, EyeIcon, DocumentTextIcon, ReceiptPercentIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    applicant: Object,
    applications: { type: Array, default: () => [] },
});

const page = usePage();
const canOpenReceipts = () => (page.props.auth?.permissions || []).includes('voucher.download-any');

const receiptOf = (app) => {
    const payment = (app.payment_transactions || [])[0];

    return payment?.receipt_number
        ? { number: payment.receipt_number, voucherId: payment.voucher?.id }
        : null;
};

const statusClass = (status) => {
    if (['submitted', 'sent_to_bank', 'bank_accepted', 'blocked', 'payment_pending'].includes(status)) {
        return 'bg-yellow-100 text-yellow-700';
    }

    if (['payment_verified', 'reviewed', 'verified', 'approved', 'allotted', 'demat_credited'].includes(status)) {
        return 'bg-green-100 text-green-700';
    }

    if (['partially_allotted', 'refund_initiated', 'refund_completed'].includes(status)) {
        return 'bg-blue-100 text-blue-700';
    }

    if (['rejected', 'not_allotted'].includes(status)) {
        return 'bg-red-100 text-red-700';
    }

    return 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <Head :title="`Applications — ${applicant.full_name_en}`" />
    <PanelLayout>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ applicant.full_name_en }}'s Applications</h2>
                    <p class="mt-1 text-sm text-gray-700">Every share application this applicant has filed.</p>
                </div>
                <Link
                    :href="route('applicants.index')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    <ArrowLeftIcon class="h-4 w-4" /> Back to Applicant List
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shares</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="app in applications" :key="app.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ app.application_number }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ app.shares_applied }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ app.total_amount_declared }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span :class="statusClass(app.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ app.status_label || app.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <Link :href="route('admin.applications.show', app.id)" class="text-indigo-600 hover:text-indigo-900" title="View">
                                        <EyeIcon class="h-4 w-4" />
                                    </Link>
                                    <Link :href="route('applications.show', app.id)" class="text-slate-600 hover:text-slate-900" title="Application Form">
                                        <DocumentTextIcon class="h-4 w-4" />
                                    </Link>
                                    <Link
                                        v-if="receiptOf(app)?.voucherId && canOpenReceipts()"
                                        :href="route('vouchers.show', receiptOf(app).voucherId)"
                                        class="text-blue-600 hover:text-blue-900"
                                        title="Receipt"
                                    >
                                        <ReceiptPercentIcon class="h-4 w-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!applications.length" class="text-center py-12">
                <p class="text-gray-500">This applicant has not filed any share applications yet.</p>
            </div>
        </div>
    </PanelLayout>
</template>
