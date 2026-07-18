<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
  application: Object,
  amountInWords: String,
  sharesInWords: String,
  photoUrl: String,
  voucherImageUrl: String,
});

const applicant = computed(() => props.application?.applicant || {});
const permanent = computed(() => applicant.value.permanent_address || {});
const temporary = computed(() => applicant.value.temporary_address || {});
const nominee = computed(() => applicant.value.nominees?.[0] || {});
const experiences = computed(() => {
  const list = applicant.value.experiences || [];
  return [list[0] || {}, list[1] || {}];
});
const payment = computed(() => props.application?.payment_transactions?.[0] || {});

const boid = computed(() => (applicant.value.boid || '').replace(/\D/g, ''));
const dpIdCells = computed(() => Array.from({ length: 8 }, (_, i) => boid.value[i] || ''));
const clientIdCells = computed(() => Array.from({ length: 8 }, (_, i) => boid.value[i + 8] || ''));

const formatDate = (value) => (value ? String(value).slice(0, 10) : '');

const submittedDate = computed(() => formatDate(props.application?.submitted_at));

const titleNp = computed(() => {
  const title = (applicant.value.title || '').toLowerCase().replace('.', '');
  if (title === 'mrs') return 'श्रीमती';
  if (title === 'ms' || title === 'miss') return 'सुश्री';
  return 'श्री';
});

const titleEn = computed(() => {
  const title = (applicant.value.title || '').toLowerCase().replace('.', '');
  if (title === 'mrs') return 'Mrs.';
  if (title === 'ms' || title === 'miss') return 'Ms.';
  return 'Mr.';
});

const maritalNp = computed(() => {
  const status = (applicant.value.marital_status || '').toLowerCase();
  if (!status) return 'विवाहित/अविवाहित';
  return status === 'married' ? 'विवाहित' : 'अविवाहित';
});

const sourceLabels = {
  salary: 'पारिश्रमिक',
  dividend: 'लाभांश',
  property_sale: 'सम्पत्ति विक्री',
  house_rent: 'घर बहाल',
  share_trading: 'शेयर कारोवार',
  other: 'अन्य',
};

const selectedSources = computed(
  () => (applicant.value.sources_of_funds || []).map((source) => source.source_type),
);

const otherSourceText = computed(
  () => (applicant.value.sources_of_funds || []).find((source) => source.source_type === 'other')?.description || '',
);

const paymentModeLabel = computed(() => {
  const mode = (payment.value.payment_mode || '').toLowerCase();
  const labels = {
    ips: 'IPS',
    connect_ips: 'IPS',
    mobile_banking: 'मोबाईल बैंकिङ्',
    cheque: 'चेक',
    bank_deposit: 'बैंक भौचर',
    cash: 'नगद',
  };
  return labels[mode] || payment.value.payment_mode || '';
});

const showPhoto = ref(Boolean(props.photoUrl));

const print = () => window.print();

onMounted(() => {
  if (new URLSearchParams(window.location.search).has('print')) {
    setTimeout(() => window.print(), 400);
  }
});
</script>

<template>
  <Head :title="`Application ${application.application_number}`" />
  <PanelLayout>
    <div class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <Link :href="route('applications.wizard')" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
          &larr; Back to Applications
        </Link>
        <button
          @click="print"
          class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
        >
          🖨️ Print Form
        </button>
      </div>

      <!-- Printable share application form (replica of the official PHL form) -->
      <div class="print-area mx-auto max-w-4xl bg-white p-6 text-[13px] leading-snug text-black shadow-sm ring-1 ring-gray-200 sm:p-8 print:max-w-none print:p-0 print:shadow-none print:ring-0">
        <!-- Header -->
        <div class="flex items-start justify-between gap-4">
          <div class="shrink-0">
            <div class="text-xl font-extrabold tracking-wide text-indigo-900">PROSPERITY</div>
            <div class="text-[11px] font-medium tracking-widest text-gray-600">— Holdings Limited —</div>
          </div>
          <div class="pt-2 text-center">
            <h1 class="inline-block border-b-2 border-black text-lg font-bold">संस्थापक शेयर खरीद दरखास्त फाराम</h1>
          </div>
          <div class="flex h-32 w-28 shrink-0 flex-col items-center justify-center border border-black p-1 text-center text-[10px] leading-tight">
            <img v-if="showPhoto" :src="photoUrl" alt="Applicant photo" class="max-h-full max-w-full object-contain" @error="showPhoto = false" />
            <template v-else>
              <span>आवेदकको हालसालै</span>
              <span>खिचिएको फोटो /</span>
              <span>(Stamp- for company)</span>
            </template>
          </div>
        </div>

        <div class="mt-2 flex items-end justify-between">
          <div>सि.नं. <span class="inline-block w-16 border-b border-dotted border-black"></span></div>
          <div class="text-right">
            <div>मिति: <span class="font-semibold">{{ submittedDate || '................' }}</span></div>
            <div>दरखास्त नं: <span class="font-semibold">{{ application.application_number }}</span></div>
          </div>
        </div>

        <div class="mt-2 flex items-start justify-between gap-4">
          <div>
            श्री सञ्चालक समिति,<br />
            प्रोस्पेरिटी होल्डिङ्स लिमिटेड<br />
            का.म.न.पा.- ११, काठमाडौँ।
          </div>
          <!-- Beneficiary (demat) account boxes -->
          <table class="border-collapse text-center">
            <tbody>
              <tr>
                <td rowspan="2" class="border border-black px-2 font-semibold">हितग्राही<br />खाता नं</td>
                <td class="border border-black px-2 text-left font-semibold">DP ID</td>
                <td v-for="(cell, i) in dpIdCells" :key="'dp' + i" class="h-7 w-7 border border-black font-mono">{{ cell }}</td>
              </tr>
              <tr>
                <td class="border border-black px-2 text-left font-semibold">Client ID</td>
                <td v-for="(cell, i) in clientIdCells" :key="'cl' + i" class="h-7 w-7 border border-black font-mono">{{ cell }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p class="mt-3 text-center font-bold"><span class="border-b-2 border-black">विषय: संस्थापक शेयर खरीद गर्ने सम्बन्धमा ।</span></p>

        <p class="mt-3">महोदय,</p>
        <p class="mt-1 text-justify indent-8">
          उपरोक्त सम्बन्धमा त्यस कम्पनीको संस्थापक समुहको शेयरमा लगानी गर्न इच्छुक भएकोले प्रति शेयर रू. {{ application.amount_per_share || '१००' }}/- का दरले हुन आउने शेयर कित्ता
          <span class="border-b border-dotted border-black px-2 font-semibold">{{ application.shares_applied }}</span>
          वापत रकम रू. <span class="border-b border-dotted border-black px-2 font-semibold">{{ application.total_amount_declared }}</span>
          (अक्षरेपि <span class="border-b border-dotted border-black px-2 font-semibold">{{ amountInWords }}</span>) जम्मा गरेको
          रसिद यसै साथ संलग्न गरी तल उल्लेखित विवरण सहित यो निवेदन पेश गरेको छु/छौँ । मेरो नाममा शेयर जारी गरिदिनु हुन अनुरोध गर्दछु/ गर्दछौँ।
        </p>

        <!-- Main details grid -->
        <table class="mt-3 w-full border-collapse">
          <tbody>
            <tr>
              <td class="w-1/4 border border-black px-2 py-1 font-semibold">आवेदन गरेको शेयर संख्या</td>
              <td class="w-1/4 border border-black px-2 py-1">{{ application.shares_applied }}</td>
              <td class="w-[12%] border border-black px-2 py-1 font-semibold">अक्षरमा</td>
              <td class="border border-black px-2 py-1">{{ sharesInWords }}</td>
            </tr>
            <tr>
              <td class="border border-black px-2 py-1 font-semibold">भुक्तानी गरेको रकम रू.</td>
              <td class="border border-black px-2 py-1">{{ application.total_amount_declared }}</td>
              <td class="border border-black px-2 py-1 font-semibold">अक्षरेपि</td>
              <td class="border border-black px-2 py-1">{{ amountInWords }}</td>
            </tr>
            <tr>
              <td colspan="2" class="border border-black px-2 py-1">
                <span class="font-semibold">नाम:</span> प्रोस्पेरिटी होल्डिङ्स लिमिटेड / PROSPERITY HOLDINGS LIMITED
              </td>
              <td colspan="2" class="border border-black px-2 py-1">
                <span class="font-semibold">बैंकको नाम:</span> सिद्धार्थ बैंक लिमिटेड, ईमाडोल शाखा
              </td>
            </tr>
            <tr>
              <td colspan="2" class="border border-black px-2 py-1"><span class="font-semibold">चल्ती खाता नं:</span> ५५५११४५३८४१</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">भुक्तानी मिति:</span> {{ formatDate(payment.payment_date) }}</td>
              <td class="border border-black px-2 py-1">
                <span class="font-semibold">भुक्तानी प्रकार:</span>
                <span v-if="paymentModeLabel" class="font-semibold underline">{{ paymentModeLabel }}</span>
                <span v-else>IPS / मोबाईल बैंकिङ् / चेक</span>
              </td>
            </tr>
            <tr>
              <td colspan="2" class="border border-black px-2 py-1">
                <span class="font-semibold">चेक खिचिएको/भुक्तानी गरिएको बैंक:</span> {{ payment.bank_name }}
              </td>
              <td colspan="2" class="border border-black px-2 py-1">
                <span class="font-semibold">कारोबार कोड / चेक नं:</span> {{ payment.payment_reference_no || payment.cheque_no || application.asba_reference }}
              </td>
            </tr>
            <tr>
              <td rowspan="2" class="border border-black px-2 py-1 text-center font-semibold">आवेदकको<br />नाम, थर</td>
              <td class="border border-black px-2 py-1">
                <span class="font-semibold">नेपालीमा {{ titleNp }}:</span> {{ applicant.full_name_np }}
              </td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">उमेर(बर्ष):</span> {{ applicant.age }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">राष्ट्रियता:</span> {{ applicant.nationality || 'नेपाली' }}</td>
            </tr>
            <tr>
              <td class="border border-black px-2 py-1">
                <span class="font-semibold">In English {{ titleEn }}:</span>
                <span class="uppercase">{{ applicant.full_name_en }}</span>
              </td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">जन्म मिति:</span> {{ formatDate(applicant.date_of_birth) }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">पेशा:</span> {{ applicant.occupation }}</td>
            </tr>
            <tr>
              <td colspan="2" class="border border-black px-2 py-1"><span class="font-semibold">बुबाको नाम, थर:</span> {{ applicant.father_name }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">बाजेको नाम, थर:</span> {{ applicant.grandfather_name }}</td>
              <td class="border border-black px-2 py-1">{{ maritalNp }}</td>
            </tr>
            <tr>
              <td colspan="2" class="border border-black px-2 py-1"><span class="font-semibold">पति/पत्नीको नाम, थर:</span> {{ applicant.spouse_name }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">शैक्षिक योग्यता:</span> {{ applicant.education }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">पेशा:</span> {{ applicant.occupation }}</td>
            </tr>
            <tr>
              <td class="border border-black px-2 py-1 font-semibold">स्थायी ठेगाना<br /><span class="text-[11px] font-normal">(As per NID)</span></td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">जिल्ला:</span> {{ permanent.district }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">गा.पा./न.पा.:</span> {{ permanent.local_level }}</td>
              <td class="border border-black px-2 py-1">
                <span class="font-semibold">वडा नं:</span> {{ permanent.ward_no }}
                <span class="ml-3 font-semibold">टोल/मार्ग:</span> {{ permanent.tole || permanent.street }}
              </td>
            </tr>
            <tr>
              <td class="border border-black px-2 py-1 font-semibold">अस्थायी ठेगाना</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">जिल्ला:</span> {{ temporary.district }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">गा.पा./न.पा.:</span> {{ temporary.local_level }}</td>
              <td class="border border-black px-2 py-1">
                <span class="font-semibold">वडा नं:</span> {{ temporary.ward_no }}
                <span class="ml-3 font-semibold">टोल/मार्ग:</span> {{ temporary.tole || temporary.street }}
              </td>
            </tr>
            <tr>
              <td class="border border-black px-2 py-1"><span class="font-semibold">ना.प्रा.प.नं:</span> {{ applicant.citizenship_number }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">जारी जिल्ला:</span> {{ applicant.citizenship_issued_district }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">जारी मिति:</span> {{ formatDate(applicant.citizenship_issued_date) }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">मोबाईल नं:</span> {{ applicant.mobile }}</td>
            </tr>
            <tr>
              <td colspan="2" class="border border-black px-2 py-1"><span class="font-semibold">राष्ट्रिय परिचय पत्र नं:</span> {{ applicant.national_id_number }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">PAN:</span> {{ applicant.pan_number }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">इमेल:</span> {{ applicant.email }}</td>
            </tr>
            <tr>
              <td colspan="4" class="border border-black px-2 py-1">
                <span class="font-semibold">लगानीको स्रोत:</span>
                <template v-for="(label, key, index) in sourceLabels" :key="key">
                  <span v-if="index" class="mx-1">/</span>
                  <span :class="selectedSources.includes(key) ? 'font-bold underline' : ''">{{ label }}</span>
                </template>
                <template v-if="otherSourceText"> (उल्लेख गर्ने): <span class="font-semibold underline">{{ otherSourceText }}</span></template>
              </td>
            </tr>
            <tr>
              <td colspan="2" class="border border-black px-2 py-1"><span class="font-semibold">शेयर हकवालाको नाम, थर:</span> {{ nominee.full_name }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">नाता:</span> {{ nominee.relationship }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">मोबाईल नं:</span> {{ nominee.mobile }}</td>
            </tr>
            <tr>
              <td colspan="4" class="border border-black px-2 py-1 font-semibold">व्यावसायीक वा कार्य-अनुभव:</td>
            </tr>
            <tr v-for="(experience, i) in experiences" :key="'exp' + i">
              <td colspan="2" class="border border-black px-2 py-1"><span class="font-semibold">संस्थाको नाम:</span> {{ experience.organization_name }}</td>
              <td class="border border-black px-2 py-1"><span class="font-semibold">ठेगाना:</span> {{ experience.address }}</td>
              <td class="border border-black px-2 py-1">
                <span class="font-semibold">पद:</span> {{ experience.position }}
                <span class="ml-3 font-semibold">बर्ष:</span> {{ experience.years }}
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Declarations -->
        <p class="mt-2 text-justify text-[11.5px] leading-snug">
          <span class="font-bold">स्व:घोषणा:</span> म/हामीले लगानी गरेको रकम कुनै पनि प्रचलित ऐन वा कानुनको बर्खिलाप हुने गरि आर्जन नगरेको, म/हामी कालो सूचीमा नपरेको र सो शेयर खरिद गर्न योग्य
          रहेको घोषणा गर्दछु/गर्दछौँ । साथै, धितोपत्रमा गरिने लगानीमा जोखिमपूर्ण हुन सक्ने भएकोले यस्तो जोखिम म/हामी स्वयंले लिने गरी यो मन्जुरी गरेको घोषणा गर्दछु/गर्दछौँ ।
        </p>
        <ul class="mt-1 list-inside text-[11px] leading-snug">
          <li>- व्यक्तिको हकमा: नागरीकता, राष्ट्रिय परिचयपत्र, PAN र १ प्रति फोटो अनिवार्य संलग्न हुनुपर्नेछ । साथै, उल्लेखित विवरणहरू अनिवार्य भर्नुपर्नेछ ।</li>
          <li>- कम्पनीको हकमा: कम्पनी दर्ता प्रमाणपत्र, प्यान, शेयरमा लगानी गर्ने सम्बन्धी सञ्चालक समितिको निर्णय/अख्तियारनामा र कम्पनीको प्रवन्धपत्र तथा नियमावलीको प्रतिलिपि।</li>
        </ul>
        <p class="mt-1 text-justify text-[11.5px] leading-snug">
          मैले/हामीले यस दरखास्त फारम राम्ररी पढी जानी बुझी यसै साथ संलग्न गरेका कागजातहरू स्वयंले प्रमाणीत गरी फारममा खुलाएको विवरण साँचो हो । साथै, शेयर खरिद तथा बाँडफाँड सम्बन्धमा
          सञ्चालक समितिको निर्णय अनुसार हुने मन्जुर गर्दछु/गर्दछौँ । सो विवरण गलत ठहरिएमा प्रचलित नेपालको कानून बमोजिम पालन गर्न मेरो/हाम्रो मन्जुरी छ ।
        </p>

        <div class="mt-8 flex items-end justify-between">
          <div>सम्पर्क सञ्चालक/ कम्पनी प्रतिनिधि : .............................................</div>
          <div class="text-center">
            ..................................<br />
            निवेदकको दस्तखत
          </div>
        </div>

        <!-- Attached bank voucher (screen only, excluded from print) -->
        <div v-if="voucherImageUrl" class="voucher-attachment mt-8 border-t border-dashed border-gray-400 pt-4">
          <p class="font-bold">संलग्न: भुक्तानी रसिद / Bank Voucher — {{ application.application_number }}</p>
          <img :src="voucherImageUrl" alt="Bank voucher" class="mt-2 max-h-[900px] max-w-full border border-gray-300 object-contain" />
        </div>
      </div>
    </div>
  </PanelLayout>
</template>

<style>
@media print {
  @page {
    size: A4 portrait;
    margin: 8mm;
  }

  /* Remove the app chrome entirely so it occupies no printed space. */
  nav {
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

  /* Hide everything, then reveal only the form. */
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
    /* Shrink the whole form uniformly so it fits a single A4 page,
       matching the one-page official PDF. */
    zoom: 0.78;
  }

  .voucher-attachment,
  .voucher-attachment * {
    display: none !important;
    visibility: hidden !important;
  }
}
</style>
