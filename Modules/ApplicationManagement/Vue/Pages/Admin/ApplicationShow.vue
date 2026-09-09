<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import StageActions from '@/Components/StageActions.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import WorkflowTimeline from '@/Components/WorkflowTimeline.vue';
import { ArrowLeftIcon, DocumentTextIcon, ReceiptPercentIcon, ArrowDownTrayIcon, DocumentPlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  application: Object,
  citizenshipUrls: { type: Array, default: () => [] },
  photoUrl: { type: String, default: null },
  signatureUrl: { type: String, default: null },
  receipt: { type: Object, default: null },
  backUrl: { type: String, default: null },
  backLabel: { type: String, default: 'Back to Applications' },
  addApplicationUrl: { type: String, default: null },
});

const nominee = computed(() => (props.application.applicant?.nominees || [])[0] || null);
const sourcesOfFunds = computed(() =>
  (props.application.applicant?.sources_of_funds || []).map((source) => source.source_type),
);

// Printing one document at a time: the chosen sheet is the only thing the
// print stylesheet reveals, so nothing else on the page comes along with it.
const printDoc = ref(null);

const printDocument = async (key) => {
  printDoc.value = key;
  await nextTick();
  window.print();
};

if (typeof window !== 'undefined') {
  window.addEventListener('afterprint', () => {
    printDoc.value = null;
  });
}

const page = usePage();
const can = (permission) => (page.props.auth?.permissions || []).includes(permission);

const vouchers = computed(() => props.application.vouchers || []);

const formatDate = (value) => {
  if (!value) return '-';
  return new Date(value).toLocaleString();
};

const statusLabel = (status) => {
  const labels = {
    draft: 'Draft',
    submitted: 'Submitted',
    sent_to_bank: 'Sent To Bank',
    bank_accepted: 'Bank Accepted',
    blocked: 'Blocked',
    payment_pending: 'Payment Pending',
    payment_verified: 'Payment Verified',
    reviewed: 'Reviewed',
    verified: 'Verified',
    approved: 'Approved',
    allotted: 'Allotted',
    partially_allotted: 'Partially Allotted',
    not_allotted: 'Not Allotted',
    refund_initiated: 'Refund Initiated',
    refund_completed: 'Refund Completed',
    demat_credited: 'Demat Credited',
    rejected: 'Rejected',
  };

  return labels[status] || status;
};

const canVerifyFromDetail = computed(() =>
  can('application.verify') && props.application?.status === 'submitted',
);

const canReviewFromDetail = computed(() =>
  can('application.review') && props.application?.status === 'verified',
);

const canApproveFromDetail = computed(() =>
  can('application.approve') && props.application?.status === 'reviewed',
);
</script>

<template>
  <Head :title="`Application ${application.application_number}`" />

  <PanelLayout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Application Details</h1>
          <p class="text-sm text-gray-600">Application No: {{ application.application_number }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Link
            v-if="addApplicationUrl"
            :href="addApplicationUrl"
            class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600"
          >
            <DocumentPlusIcon class="h-4 w-4" /> Add Application
          </Link>
          <Link
            :href="route('applications.show', application.id)"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            <DocumentTextIcon class="h-4 w-4" /> Application Form
          </Link>
          <Link
            :href="backUrl || route('admin.applications')"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            <ArrowLeftIcon class="h-4 w-4" /> {{ backLabel }}
          </Link>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-lg font-semibold text-gray-900">Applicant</h2>
          <p class="text-sm text-gray-700"><span class="font-medium">Name:</span> {{ application.applicant?.full_name_en || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Email:</span> {{ application.applicant?.email || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Mobile:</span> {{ application.applicant?.mobile || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Citizenship No:</span> {{ application.applicant?.citizenship_number || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">BOID:</span> {{ application.applicant?.boid || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Bank:</span> {{ application.applicant?.bank_name || '-' }} {{ application.applicant?.bank_branch || '' }}</p>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-lg font-semibold text-gray-900">Application Summary</h2>
          <p class="text-sm text-gray-700"><span class="font-medium">Status:</span> {{ statusLabel(application.status) }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Issue Code:</span> {{ application.issue_code || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Declared Deposits:</span> {{ vouchers.length || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Shares Applied:</span> {{ application.shares_applied }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Amount Per Share:</span> {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ application.amount_per_share }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Total Declared:</span> {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ application.total_amount_declared }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Blocked Amount:</span> {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ application.blocked_amount || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Refunded Amount:</span> {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ application.refunded_amount || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Submitted At:</span> {{ formatDate(application.submitted_at) }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Reviewed By:</span> {{ application.reviewer?.name || '-' }}</p>
          <p class="text-sm text-gray-700"><span class="font-medium">Reviewed At:</span> {{ formatDate(application.reviewed_at) }}</p>
          <p class="text-sm text-gray-700" v-if="application.rejection_reason"><span class="font-medium">Rejection Reason:</span> {{ application.rejection_reason }}</p>
        </section>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-3 lg:col-span-1">
          <h2 class="text-lg font-semibold text-gray-900">Photo &amp; Signature</h2>
          <div class="flex gap-4">
            <div class="flex h-28 w-24 items-center justify-center rounded border border-gray-200 bg-gray-50">
              <img v-if="photoUrl" :src="photoUrl" alt="Applicant photo" class="h-full w-full object-cover" />
              <span v-else class="text-xs text-gray-400">No photo</span>
            </div>
            <div class="flex h-28 w-40 items-center justify-center rounded border border-gray-200 bg-gray-50">
              <img v-if="signatureUrl" :src="signatureUrl" alt="Applicant signature" class="max-h-full max-w-full object-contain" />
              <span v-else class="text-xs text-gray-400">No signature</span>
            </div>
          </div>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-2 lg:col-span-1">
          <h2 class="text-lg font-semibold text-gray-900">Source of Funds</h2>
          <p v-if="!sourcesOfFunds.length" class="text-sm text-gray-500">Not declared.</p>
          <ul v-else class="list-inside list-disc text-sm text-gray-700">
            <li v-for="source in sourcesOfFunds" :key="source" class="capitalize">{{ source.replace(/_/g, ' ') }}</li>
          </ul>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-1 lg:col-span-1">
          <h2 class="text-lg font-semibold text-gray-900">Nominee</h2>
          <template v-if="nominee">
            <p class="text-sm text-gray-700"><span class="font-medium">Name:</span> {{ nominee.full_name }}</p>
            <p class="text-sm text-gray-700"><span class="font-medium">Relationship:</span> {{ nominee.relationship || '-' }}</p>
            <p class="text-sm text-gray-700"><span class="font-medium">Mobile:</span> {{ nominee.mobile || '-' }}</p>
            <p class="text-sm text-gray-700"><span class="font-medium">Address:</span> {{ nominee.address || '-' }}</p>
          </template>
          <p v-else class="text-sm text-gray-500">No nominee on file.</p>
        </section>
      </div>

      <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-lg font-semibold text-gray-900">Declared Bank Vouchers</h2>
          <div v-if="citizenshipUrls.length" class="flex flex-wrap gap-2">
            <button
              v-for="doc in citizenshipUrls"
              :key="`print-${doc.side}`"
              type="button"
              class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-gray-400 hover:bg-gray-50"
              @click="printDocument(`citizenship-${doc.side}`)"
            >
              🖨️ Print Citizenship ({{ doc.label }})
            </button>
          </div>
        </div>
        <template v-if="vouchers.length">
          <div
            v-for="(voucher, index) in vouchers"
            :key="voucher.id"
            class="border-t border-gray-100 py-4 first:border-t-0 first:pt-0"
          >
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Voucher {{ index + 1 }}</p>
            <div class="mt-2 grid gap-x-6 gap-y-1 sm:grid-cols-2">
              <p class="text-sm text-gray-700"><span class="font-medium">Payment Type:</span> <span class="capitalize">{{ (voucher.payment_type || '-').replace('_', ' ') }}</span></p>
              <p class="text-sm text-gray-700"><span class="font-medium">Paying Bank:</span> {{ voucher.deposited_bank || '-' }}</p>
              <p class="text-sm text-gray-700"><span class="font-medium">Transaction Code / Cheque No:</span> {{ voucher.transaction_code || '-' }}</p>
              <p class="text-sm text-gray-700"><span class="font-medium">ASBA Reference:</span> {{ voucher.asba_reference || '-' }}</p>
              <p class="text-sm text-gray-700"><span class="font-medium">Amount:</span> {{ $page.props.settings?.currency_symbol || 'Rs.' }} {{ voucher.amount || '-' }}</p>
            </div>
            <template v-if="voucher.has_image">
              <div class="mt-2 flex flex-wrap items-center gap-3">
                <a :href="route('applications.voucher-image', [application.id, voucher.id])" target="_blank" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
                  Open in new tab
                </a>
                <button
                  type="button"
                  class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-gray-400 hover:bg-gray-50"
                  @click="printDocument(`voucher-${voucher.id}`)"
                >
                  🖨️ Print This Voucher
                </button>
              </div>
              <img
                :src="route('applications.voucher-image', [application.id, voucher.id])"
                alt="Uploaded bank voucher"
                class="mt-3 max-h-[600px] max-w-full rounded-lg border border-gray-200 object-contain"
              />
            </template>
            <p v-else class="mt-2 text-sm text-gray-500">No slip uploaded for this deposit.</p>
          </div>
        </template>
        <p v-else class="text-sm text-gray-500">The applicant has not declared any bank vouchers.</p>
      </section>

      <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-lg font-semibold text-gray-900">Review Trail</h2>
        <p class="mt-1 mb-4 text-sm text-gray-600">
          Every sign-off, return and send-back, with the remarks given at the time.
        </p>
        <WorkflowTimeline :events="application.workflow_events ?? []" order="asc" />
      </section>

      <section v-if="receipt" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Receipt</h2>
        <p class="text-sm text-gray-700 mb-3"><span class="font-medium">Receipt No:</span> {{ receipt.receiptNumber || '-' }}</p>
        <div class="overflow-hidden rounded-lg border border-gray-200" style="height: 32rem;">
          <iframe :src="receipt.downloadUrl" title="Receipt preview" class="h-full w-full"></iframe>
        </div>
        <div class="mt-3 flex flex-wrap gap-3">
          <Link :href="receipt.showUrl" class="inline-flex items-center gap-1 text-sm font-semibold text-emerald-700 hover:text-emerald-900">
            <ReceiptPercentIcon class="h-4 w-4" /> View Receipt
          </Link>
          <a :href="receipt.downloadUrl" class="inline-flex items-center gap-1 text-sm font-semibold text-emerald-700 hover:text-emerald-900">
            <ArrowDownTrayIcon class="h-4 w-4" /> Download Receipt PDF
          </a>
        </div>
      </section>

      <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100" v-if="application.allotment">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Allotment</h2>
        <p class="text-sm text-gray-700"><span class="font-medium">Certificate No:</span> {{ application.allotment.certificate_number || '-' }}</p>
        <p class="text-sm text-gray-700"><span class="font-medium">Shares Allotted:</span> {{ application.allotment.shares_allotted || '-' }}</p>
        <p class="text-sm text-gray-700"><span class="font-medium">Allotment Date:</span> {{ formatDate(application.allotment.allotment_date) }}</p>
      </section>

      <section v-if="canVerifyFromDetail" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-blue-100">
        <h2 class="text-lg font-semibold text-gray-900">Verifier Action</h2>
        <p class="mt-1 mb-4 text-sm text-gray-600">
          You can mark this application verified after reviewing the full form and documents.
        </p>
        <StageActions
          :action-url="route('verifier.applications.act', application.id)"
          :can-send-back="application.can_send_back"
          approve-label="Mark Verified"
        />
      </section>

      <section v-if="canReviewFromDetail" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-indigo-100">
        <h2 class="text-lg font-semibold text-gray-900">Reviewer Action</h2>
        <p class="mt-1 mb-4 text-sm text-gray-600">
          You can mark this application reviewed after checking the verifier stage outcome and full form details.
        </p>
        <StageActions
          :action-url="route('reviewer.applications.act', application.id)"
          :can-send-back="application.can_send_back"
          approve-label="Mark Reviewed"
        />
      </section>

      <section v-if="canApproveFromDetail" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-emerald-100">
        <h2 class="text-lg font-semibold text-gray-900">Approver Action</h2>
        <p class="mt-1 mb-4 text-sm text-gray-600">
          You can give final approval after reviewing the full form, previous remarks, and reviewer decision.
        </p>
        <StageActions
          :action-url="route('approver.applications.act', application.id)"
          :can-send-back="application.can_send_back"
          approve-label="Approve &amp; Issue Voucher"
        />
      </section>

      <!-- One sheet per document, rendered only while that document is being
           printed. Teleported to <body> so the print rule can hide every other
           top-level node outright: hiding by visibility alone would leave the
           admin page's layout behind and pad the output with blank sheets. -->
      <Teleport to="body">
      <div v-if="printDoc" class="doc-print-sheet">
        <template v-for="doc in citizenshipUrls" :key="`sheet-${doc.side}`">
          <div v-if="printDoc === `citizenship-${doc.side}`">
            <p class="doc-print-caption">
              नागरिकता / Citizenship ({{ doc.label }})
              · {{ application.application_number }}
              · {{ application.applicant?.full_name_en || '' }}
            </p>
            <img :src="doc.url" alt="Citizenship document" />
          </div>
        </template>

        <template v-for="voucher in vouchers" :key="`sheet-voucher-${voucher.id}`">
          <div v-if="printDoc === `voucher-${voucher.id}`">
            <p class="doc-print-caption">
              भुक्तानी रसिद / Bank Voucher
              <span v-if="voucher.transaction_code">({{ voucher.transaction_code }})</span>
              · {{ application.application_number }}
              · {{ voucher.deposited_bank || '' }}
            </p>
            <img :src="route('applications.voucher-image', [application.id, voucher.id])" alt="Bank voucher" />
          </div>
        </template>
      </div>
      </Teleport>
    </div>
  </PanelLayout>
</template>

<style>
/* Off-screen until a print is actually requested. */
.doc-print-sheet {
  display: none;
}

@media print {
  @page {
    size: A4 portrait;
    margin: 10mm;
  }

  /* Everything except the teleported sheet is removed from the layout, not
     merely hidden, so the output is exactly one page. */
  body > *:not(.doc-print-sheet) {
    display: none !important;
  }

  .doc-print-sheet {
    display: block !important;
  }

  .doc-print-caption {
    margin-bottom: 4mm;
    font-size: 11pt;
    font-weight: 700;
  }

  .doc-print-sheet img {
    max-width: 100%;
    max-height: 250mm;
    object-fit: contain;
  }
}
</style>
