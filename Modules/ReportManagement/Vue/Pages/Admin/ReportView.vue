<script setup>
import PanelLayout from '@/Layouts/PanelLayout.vue';
import ReportTabs from '@/Components/ReportTabs.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * Renders any report the registry can build. Everything on screen comes from
 * the report's own description of itself — filters, columns, rows — so the five
 * prescribed formats share one page and one column picker.
 */
const props = defineProps({
  report: { type: Object, required: true },
  reports: { type: Array, default: () => [] },
  filterDefinitions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  columns: { type: Array, default: () => [] },
  visibleColumns: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({}) },
  // Nepali formats label the totals row जम्मा rather than "Total".
  totalsLabel: { type: String, default: 'Total' },
  org: { type: Object, default: () => ({ name: '', address: '' }) },
});

// A multiselect filter always binds to an array — seeded from whatever the
// server sent back (or empty, meaning "all") rather than null, which a
// native multi-select can't represent.
const seedForm = () => {
  const seeded = { ...props.filters };

  props.filterDefinitions.forEach((definition) => {
    if (definition.type === 'multiselect' && !Array.isArray(seeded[definition.key])) {
      seeded[definition.key] = [];
    }
  });

  return seeded;
};

const form = ref(seedForm());
const chosen = ref([...props.visibleColumns]);
const showColumns = ref(false);

// A fresh report has its own filters and columns; re-seed rather than carrying
// the previous report's selection across.
watch(() => props.report.key, () => {
  form.value = seedForm();
  chosen.value = [...props.visibleColumns];
});

const label = computed(() => props.report.titleNp || props.report.title);

const shownColumns = computed(() => props.columns.filter((column) => chosen.value.includes(column.key)));

// Options of a dependent filter (offering) are narrowed by its parent (company).
const optionsFor = (definition) => {
  const options = definition.options || [];
  const parent = definition.dependsOn ? form.value[definition.dependsOn] : null;

  return parent ? options.filter((option) => String(option.parent) === String(parent)) : options;
};

const query = () => {
  const params = {};

  Object.entries(form.value).forEach(([key, value]) => {
    // A multiselect's array goes over the wire as a comma-joined string, the
    // same convention the column picker below already uses — simpler and
    // more predictable than relying on axios/qs to serialise a bracketed
    // array param the same way on every request.
    if (Array.isArray(value)) {
      if (value.length) params[key] = value.join(',');
      return;
    }

    if (value !== null && value !== '' && value !== undefined) params[key] = value;
  });

  // Only send the column list when it differs from the report's own default,
  // so a shared URL stays short and the default can change server-side.
  const isDefault = chosen.value.length === props.visibleColumns.length
    && chosen.value.every((key) => props.visibleColumns.includes(key));

  if (!isDefault) params.columns = chosen.value.join(',');

  return params;
};

const apply = () => router.get(route('admin.reports.show', props.report.key), query(), {
  preserveState: true,
  preserveScroll: true,
});

const clearFilters = () => {
  props.filterDefinitions.forEach((definition) => {
    form.value[definition.key] = definition.type === 'multiselect' ? [] : null;
  });
  apply();
};

// Clearing a parent filter strands the dependent selection, so drop it too —
// for a multiselect, only the choices that are no longer valid, not the
// whole array.
watch(() => props.filterDefinitions.map((definition) => form.value[definition.key]).join('|'), () => {
  props.filterDefinitions.forEach((definition) => {
    if (!definition.dependsOn) return;

    const parent = form.value[definition.dependsOn];
    const value = form.value[definition.key];

    if (!parent) return;

    const valid = optionsFor(definition);

    if (Array.isArray(value)) {
      const kept = value.filter((v) => valid.some((option) => String(option.value) === String(v)));

      if (kept.length !== value.length) form.value[definition.key] = kept;

      return;
    }

    if (!value) return;

    const stillValid = valid.some((option) => String(option.value) === String(value));

    if (!stillValid) form.value[definition.key] = null;
  });
});

const exportUrl = (format) => {
  const params = new URLSearchParams({ ...query(), format });

  // Downloads must honour the picker even when it matches the default.
  params.set('columns', chosen.value.join(','));

  return `${route('admin.reports.view.export', props.report.key)}?${params.toString()}`;
};

const alignClass = (column) => (column.align === 'right' ? 'text-right' : 'text-left');
</script>

<template>
  <Head :title="`Report — ${report.title}`" />
  <PanelLayout>
    <div class="space-y-5">
      <ReportTabs :reports="reports" :current="report.key" />

      <div class="rounded-lg bg-white p-5 shadow">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <!-- Two type sizes only: the issuing organisation and the note read
               as one small block, the report name carries the page. -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
              {{ org.name }}<span v-if="org.address" class="font-normal normal-case tracking-normal"> · {{ org.address }}</span>
            </p>
            <h2 class="mt-1.5 text-2xl font-semibold text-gray-900">{{ label }}</h2>
            <p class="mt-1 max-w-[80ch] text-xs text-gray-600">{{ report.description }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <a :href="exportUrl('xlsx')" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</a>
            <a :href="exportUrl('csv')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">CSV</a>
            <a
              :href="exportUrl('pdf')"
              class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
            >PDF</a>
          </div>
        </div>

        <p v-if="$page.props.errors.format" class="mt-3 rounded bg-red-50 px-4 py-2 text-sm text-red-700">
          {{ $page.props.errors.format }}
        </p>

        <div v-if="filterDefinitions.length" class="mt-5 border-t border-gray-100 pt-4">
          <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-4">
            <div v-for="definition in filterDefinitions" :key="definition.key">
              <label class="mb-1 block text-xs font-medium text-gray-700">{{ definition.label }}</label>
              <select
                v-if="definition.type === 'select'"
                v-model="form[definition.key]"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              >
                <option :value="null">{{ definition.placeholder || 'All' }}</option>
                <option v-for="option in optionsFor(definition)" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <select
                v-else-if="definition.type === 'multiselect'"
                v-model="form[definition.key]"
                multiple
                class="h-28 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              >
                <option v-for="option in optionsFor(definition)" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <input
                v-else
                v-model="form[definition.key]"
                :type="definition.type === 'date' ? 'date' : 'text'"
                :placeholder="definition.placeholder || ''"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              />
              <p v-if="definition.type === 'multiselect'" class="mt-1 text-xs text-gray-500">
                {{ form[definition.key]?.length ? `${form[definition.key].length} selected` : (definition.placeholder || 'All') }} — Ctrl/Cmd-click to pick several.
              </p>
            </div>
          </div>

          <div class="mt-4 flex flex-wrap items-center gap-2">
            <button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" @click="apply">
              Apply
            </button>
            <button class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="clearFilters">
              Clear
            </button>
            <button
              class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="showColumns = !showColumns"
            >
              Columns ({{ chosen.length }}/{{ columns.length }})
            </button>
          </div>

          <div v-if="showColumns" class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">
              Columns to show — applies to the screen and to downloads
            </p>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              <label v-for="column in columns" :key="column.key" class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="chosen" type="checkbox" :value="column.key" class="rounded border-gray-300 text-blue-600" />
                {{ column.labelNp || column.label }}
              </label>
            </div>
            <button class="mt-3 rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" @click="apply">
              Apply columns
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-xs uppercase text-gray-500">
              <th
                v-for="column in shownColumns"
                :key="column.key"
                class="whitespace-nowrap px-4 py-3"
                :class="alignClass(column)"
              >
                {{ column.labelNp || column.label }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="(row, index) in rows" :key="index" class="hover:bg-gray-50">
              <!-- whitespace-pre-line so composite cells break where the
                   report put a newline, matching the PDF and the spreadsheet. -->
              <td
                v-for="column in shownColumns"
                :key="column.key"
                class="whitespace-pre-line px-4 py-3 align-top text-gray-900"
                :class="alignClass(column)"
              >
                {{ row[column.key] }}
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td :colspan="shownColumns.length || 1" class="px-4 py-8 text-center text-sm text-gray-500">
                No rows match the selected filters.
              </td>
            </tr>
          </tbody>
          <tfoot v-if="Object.keys(totals).length && rows.length">
            <tr class="border-t bg-gray-50 font-semibold text-gray-900">
              <td
                v-for="(column, index) in shownColumns"
                :key="column.key"
                class="px-4 py-3"
                :class="alignClass(column)"
              >
                {{ index === 0 ? totalsLabel : (totals[column.key] ?? '') }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <p class="text-xs text-gray-500">{{ rows.length.toLocaleString() }} row(s)</p>
    </div>
  </PanelLayout>
</template>
