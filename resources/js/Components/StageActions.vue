<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * The one place a stage acts on a record. Used by both review chains so the
 * KYC queue and the three application dashboards share a vocabulary.
 *
 * Remarks are inline rather than behind a modal: the reviewer is reading the
 * record while writing them, and every action requires them, so hiding the
 * field behind a dialog would add a step to the common path.
 */
const props = defineProps({
    actionUrl: { type: String, required: true },
    // False at the first stage, where the only way back is to the applicant.
    canSendBack: { type: Boolean, default: false },
    approveLabel: { type: String, default: 'Approve' },
});

const form = useForm({ action: null, remarks: '' });

const hasRemarks = computed(() => form.remarks.trim().length >= 3);
const busy = computed(() => form.processing);

const submit = (action) => {
    if (!hasRemarks.value || busy.value) return;

    form.action = action;
    form.transform((data) => ({ ...data, remarks: data.remarks.trim() }))
        .post(props.actionUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
};

// Which button is mid-flight, so only that one shows the pending state.
const pending = (action) => busy.value && form.action === action;
</script>

<template>
    <div class="space-y-2">
        <label class="block">
            <span class="sr-only">Remarks</span>
            <textarea
                v-model="form.remarks"
                rows="2"
                :disabled="busy"
                placeholder="Remarks — required, and recorded against your name"
                class="block w-full resize-y rounded-lg border-gray-300 text-sm text-gray-900 placeholder:text-gray-500 focus:border-blue-500 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-50"
            />
        </label>

        <p v-if="form.errors.remarks" class="text-sm font-medium text-red-700">
            {{ form.errors.remarks }}
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                :disabled="!hasRemarks || busy"
                class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white transition duration-150 hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 active:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                @click="submit('approve')"
            >
                {{ pending('approve') ? 'Working…' : approveLabel }}
            </button>

            <button
                type="button"
                :disabled="!hasRemarks || busy"
                class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-900 transition duration-150 hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 active:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-50"
                @click="submit('return_to_applicant')"
            >
                {{ pending('return_to_applicant') ? 'Working…' : 'Return to Applicant' }}
            </button>

            <button
                v-if="canSendBack"
                type="button"
                :disabled="!hasRemarks || busy"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition duration-150 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 active:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
                @click="submit('send_back')"
            >
                {{ pending('send_back') ? 'Working…' : 'Send Back a Stage' }}
            </button>
        </div>

        <p class="text-xs text-gray-600">
            Returning sends it to the applicant to correct. Sending back returns it one stage for another look.
        </p>
    </div>
</template>
