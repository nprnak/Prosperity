<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    // The Gregorian (AD) date this picker ultimately produces, 'YYYY-MM-DD'.
    modelValue: { type: String, default: '' },
    // Server-computed BS parts for the current modelValue, so editing an
    // existing record pre-fills the picker without a round trip on mount.
    initialBs: { type: Object, default: null },
    required: { type: Boolean, default: false },
    minBsYear: { type: Number, default: 2000 },
    maxBsYear: { type: Number, default: 2090 },
});

const emit = defineEmits(['update:modelValue']);

const NEPALI_MONTHS = [
    'Baishakh', 'Jestha', 'Ashadh', 'Shrawan', 'Bhadra', 'Ashwin',
    'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra',
];
const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

// The confirmed selection — only these change form.date_of_birth (via the
// emitted AD date). Separate from the calendar's "currently browsed" month,
// so paging through months doesn't touch the value until a day is clicked.
const selectedYear = ref(props.initialBs?.year ?? null);
const selectedMonth = ref(props.initialBs?.month ?? null);
const selectedDay = ref(props.initialBs?.day ?? null);

const open = ref(false);
const viewYear = ref(selectedYear.value ?? props.maxBsYear);
const viewMonth = ref(selectedMonth.value ?? 1);
const daysInViewMonth = ref(32);
const leadingBlanks = ref(0);
const converting = ref(false);
const error = ref('');
const root = ref(null);

const years = computed(() => {
    const list = [];
    for (let y = props.maxBsYear; y >= props.minBsYear; y--) list.push(y);
    return list;
});

const dayCells = computed(() => [
    ...Array.from({ length: leadingBlanks.value }, () => null),
    ...Array.from({ length: daysInViewMonth.value }, (_, i) => i + 1),
]);

const displayLabel = computed(() => {
    if (!selectedYear.value || !selectedMonth.value || !selectedDay.value) return '';
    return `${NEPALI_MONTHS[selectedMonth.value - 1]} ${selectedDay.value}, ${selectedYear.value} B.S.`;
});

// The calendar grid needs to know which weekday the month's 1st falls on —
// derived from its A.D. equivalent rather than guessed, since the B.S./A.D.
// day offset isn't a fixed number.
const refreshGrid = async () => {
    try {
        const [daysRes, firstDayRes] = await Promise.all([
            window.axios.get(route('nepali-date.days-in-month'), {
                params: { year: viewYear.value, month: viewMonth.value },
            }),
            window.axios.get(route('nepali-date.to-english'), {
                params: { year: viewYear.value, month: viewMonth.value, day: 1 },
            }),
        ]);

        daysInViewMonth.value = daysRes.data.days || 30;
        leadingBlanks.value = new Date(firstDayRes.data.date + 'T00:00:00').getDay();
    } catch {
        daysInViewMonth.value = 30;
        leadingBlanks.value = 0;
    }
};

watch([viewYear, viewMonth], refreshGrid);

const changeMonth = (delta) => {
    let month = viewMonth.value + delta;
    let year = viewYear.value;

    if (month > 12) { month = 1; year++; }
    if (month < 1) { month = 12; year--; }

    if (year < props.minBsYear || year > props.maxBsYear) return;

    viewYear.value = year;
    viewMonth.value = month;
};

const pickDay = async (day) => {
    if (!day) return;

    converting.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get(route('nepali-date.to-english'), {
            params: { year: viewYear.value, month: viewMonth.value, day },
        });
        selectedYear.value = viewYear.value;
        selectedMonth.value = viewMonth.value;
        selectedDay.value = day;
        emit('update:modelValue', data.date);
        open.value = false;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not convert that date.';
    } finally {
        converting.value = false;
    }
};

const toggleOpen = () => {
    open.value = !open.value;
    if (open.value) {
        viewYear.value = selectedYear.value ?? viewYear.value;
        viewMonth.value = selectedMonth.value ?? viewMonth.value;
        refreshGrid();
    }
};

const onDocumentClick = (event) => {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
};

onMounted(async () => {
    document.addEventListener('mousedown', onDocumentClick);

    if (!selectedYear.value && props.modelValue) {
        try {
            const { data } = await window.axios.get(route('nepali-date.to-nepali'), {
                params: { date: props.modelValue },
            });
            selectedYear.value = data.year;
            selectedMonth.value = data.month;
            selectedDay.value = data.day;
            viewYear.value = data.year;
            viewMonth.value = data.month;
        } catch {
            // Leave the picker empty — the A.D. value already stored on the
            // record is untouched, so nothing is lost, just not shown in B.S.
        }
    }
});

onBeforeUnmount(() => document.removeEventListener('mousedown', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-lg border border-gray-300 px-3 py-2 text-left text-sm"
            @click="toggleOpen"
        >
            <span :class="displayLabel ? 'text-gray-900' : 'text-gray-400'">
                {{ displayLabel || 'Select a date (B.S.)' }}
            </span>
            <span aria-hidden="true">📅</span>
        </button>
        <div
            v-if="open"
            class="absolute z-20 mt-1 w-72 rounded-lg border border-gray-200 bg-white p-3 shadow-lg"
        >
            <div class="flex items-center justify-between gap-2">
                <button type="button" class="rounded p-1 text-gray-500 hover:bg-gray-100" @click="changeMonth(-1)">‹</button>
                <div class="flex flex-1 gap-1">
                    <select v-model.number="viewYear" class="w-1/2 rounded border border-gray-300 px-1 py-1 text-sm">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                    <select v-model.number="viewMonth" class="w-1/2 rounded border border-gray-300 px-1 py-1 text-sm">
                        <option v-for="(name, i) in NEPALI_MONTHS" :key="name" :value="i + 1">{{ name }}</option>
                    </select>
                </div>
                <button type="button" class="rounded p-1 text-gray-500 hover:bg-gray-100" @click="changeMonth(1)">›</button>
            </div>

            <div class="mt-2 grid grid-cols-7 gap-1 text-center text-xs font-medium text-gray-500">
                <span v-for="day in WEEKDAYS" :key="day">{{ day }}</span>
            </div>
            <div class="mt-1 grid grid-cols-7 gap-1">
                <button
                    v-for="(day, index) in dayCells"
                    :key="index"
                    type="button"
                    :disabled="!day"
                    class="rounded py-1 text-sm"
                    :class="[
                        !day ? 'invisible' : 'hover:bg-brand-50',
                        day && day === selectedDay && viewYear === selectedYear && viewMonth === selectedMonth
                            ? 'bg-brand text-white hover:bg-brand'
                            : 'text-gray-700',
                    ]"
                    @click="pickDay(day)"
                >
                    {{ day }}
                </button>
            </div>

            <p class="mt-2 text-xs text-slate-500">
                <span v-if="converting">Converting…</span>
                <span v-else-if="error" class="text-red-600">{{ error }}</span>
                <span v-else-if="modelValue">AD: {{ modelValue }}</span>
            </p>
        </div>
    </div>
</template>
