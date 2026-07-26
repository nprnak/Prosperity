<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  receipt: { type: Object, required: true },
  downloadUrl: { type: String, required: true },
});

const print = () => window.print();
</script>

<template>
  <Head :title="`Receipt ${receipt.receiptNumber || receipt.voucherNumber}`" />
  <PanelLayout>
    <div class="mx-auto max-w-5xl space-y-4">
      <div class="no-print flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-2xl font-bold text-gray-900">Payment Receipt</h2>
          <p class="text-sm text-gray-600">
            Application {{ receipt.applicationNumber }} · Voucher {{ receipt.voucherNumber }}
          </p>
        </div>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="print"
          >
            Print
          </button>
          <a
            :href="downloadUrl"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
          >
            Download PDF
          </a>
        </div>
      </div>

      <!-- The receipt itself. Laid out to match the printed book: signature
           and stamp are left as blank ruled lines, to be signed by hand. -->
      <div class="print-area bg-white p-8 shadow-sm ring-1 ring-gray-200">
        <header class="text-center">
          <img v-if="receipt.logoDataUri" :src="receipt.logoDataUri" alt="" class="mx-auto mb-1 h-14" />
          <div class="text-2xl font-bold uppercase tracking-widest text-gray-900">{{ receipt.companyName }}</div>
          <div v-if="receipt.companyAddress" class="text-sm font-semibold text-gray-800">
            {{ receipt.companyAddress }}
          </div>
        </header>

        <div class="mt-5 flex items-start justify-between gap-6">
          <div class="space-y-2 text-sm">
            <div>Receipt No.: <span class="fill">{{ receipt.receiptNumber || '—' }}</span></div>
            <div>Date: <span class="fill">{{ receipt.issuedOn || '—' }}</span></div>
          </div>
          <div class="text-right text-xs text-gray-600">
            Voucher No: <strong>{{ receipt.voucherNumber }}</strong><br />
            Application No: <strong>{{ receipt.applicationNumber }}</strong>
          </div>
        </div>

        <h3 class="my-4 text-center text-lg font-bold tracking-wide text-gray-900">PAYMENT RECEIPT</h3>

        <p class="text-sm leading-loose text-gray-900">
          We hereby acknowledge the receipt of payment amounting to
          Rs. <span class="fill">{{ receipt.amount }}/-</span>
          (In Words: Nepalese Rupees <span class="fill">{{ receipt.amountInWords }}</span> Only)
          for the purpose of <span class="fill">Share Capital of our Company</span>
          from Mr./Mrs./Ms. <span class="fill">{{ receipt.applicantName || '—' }}</span>,
          holding ID No. <span class="fill">{{ receipt.holdingIdNo || '................' }}</span>
          ID Type <span class="fill">{{ receipt.holdingIdLabel || '................' }}</span>.
        </p>

        <div class="mt-5 space-y-3 text-sm text-gray-900">
          <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
            <strong>Mode of Payment:</strong>
            <span v-for="(label, mode) in receipt.printedModes" :key="mode" class="whitespace-nowrap">
              <span class="cb">{{ receipt.tickedMode === mode ? '✕' : '' }}</span>{{ label }}
            </span>
          </div>
          <div>Payment Reference No: <span class="fill">{{ receipt.referenceLine || '................' }}</span></div>
          <div>Date of Payment: <span class="fill">{{ receipt.paymentDateLine || '................' }}</span></div>
        </div>

        <!-- Signed and stamped by hand, as on the paper original. -->
        <div class="mt-16 grid grid-cols-3 items-end gap-6 text-sm">
          <div><span class="sig">Issued By:</span></div>
          <div class="text-center font-semibold text-gray-600">Company Stamp</div>
          <div class="text-right"><span class="sig">Approved By:</span></div>
        </div>

        <div v-if="receipt.verificationQr" class="mt-10 flex items-center gap-3 text-xs text-gray-600">
          <img :src="receipt.verificationQr" alt="Verification QR" class="h-16 w-16" />
          <div>
            Verification Code: <strong>{{ receipt.verificationCode }}</strong><br />
            Verify this receipt at {{ receipt.verificationUrl }}
          </div>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>

<style scoped>
.fill {
  display: inline-block;
  min-width: 4rem;
  border-bottom: 1px solid #111827;
  padding: 0 0.4rem 1px;
  font-weight: 600;
}

.cb {
  display: inline-block;
  width: 0.85rem;
  height: 0.85rem;
  margin-right: 0.35rem;
  border: 1.4px solid #111827;
  text-align: center;
  font-size: 0.7rem;
  line-height: 0.8rem;
  font-weight: 700;
}

.sig {
  display: inline-block;
  min-width: 10rem;
  border-top: 1px dotted #111827;
  padding-top: 3px;
  font-weight: 600;
}
</style>

<style>
/* Follows the print treatment already used by the applicant's form: hide the
   app chrome, then reveal only the receipt. Landscape, because the receipt
   book is. */
@media print {
  @page {
    size: A4 landscape;
    margin: 12mm;
  }

  nav,
  .no-print {
    display: none !important;
  }

  html,
  body {
    height: auto !important;
    overflow: visible !important;
  }

  .min-h-screen {
    min-height: 0 !important;
  }

  body * {
    visibility: hidden;
  }

  .print-area,
  .print-area * {
    visibility: visible;
  }

  .print-area {
    position: absolute;
    inset: 0 auto auto 0;
    width: 100%;
    margin: 0;
    padding: 0;
    box-shadow: none;
  }
}
</style>
