<script setup>
/**
 * A jar-code entry strip usable two ways: a USB/Bluetooth barcode scanner
 * behaves like a keyboard (types the code fast, then Enter), so a plain
 * autofocused text input already supports it with no extra code. The camera
 * button is only for phone/tablet users without a dedicated scanner — it
 * loads html5-qrcode lazily so pages that never open it never pay for it.
 */
import { ref, nextTick, onBeforeUnmount } from 'vue';
import { CameraIcon, QrCodeIcon } from '@heroicons/vue/24/outline';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  placeholder: { type: String, default: 'Scan or type jar code, then press Enter' },
  autofocus: { type: Boolean, default: true },
  allowCamera: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['scan']);

const value = ref('');
const input = ref(null);
const cameraOpen = ref(false);
const cameraError = ref('');
let scanner = null;

const focus = () => input.value?.focus();
defineExpose({ focus });

const submit = () => {
  const code = value.value.trim().toUpperCase();
  if (!code || props.disabled) return;
  emit('scan', code);
  value.value = '';
};

const openCamera = async () => {
  cameraOpen.value = true;
  cameraError.value = '';
  await nextTick();

  try {
    const { Html5Qrcode } = await import('html5-qrcode');
    scanner = new Html5Qrcode('jar-scan-camera-reader');
    await scanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 250, height: 150 } },
      (decodedText) => {
        value.value = decodedText;
        closeCamera();
        submit();
      },
      () => {},
    );
  } catch (e) {
    cameraError.value = 'Could not start the camera. Check permissions, or type the code instead.';
  }
};

const closeCamera = async () => {
  if (scanner) {
    try {
      await scanner.stop();
      scanner.clear();
    } catch (e) {
      // Scanner was already stopped/torn down — nothing to clean up.
    }
    scanner = null;
  }
  cameraOpen.value = false;
};

onBeforeUnmount(closeCamera);
</script>

<template>
  <div class="flex items-center gap-2">
    <div class="relative flex-1">
      <QrCodeIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
      <input
        ref="input"
        v-model="value"
        type="text"
        :placeholder="placeholder"
        :disabled="disabled"
        :autofocus="autofocus"
        class="w-full rounded-md border-gray-300 pl-9 shadow-sm focus:border-brand focus:ring-brand disabled:bg-gray-100"
        @keydown.enter.prevent="submit"
      />
    </div>
    <button
      v-if="allowCamera"
      type="button"
      :disabled="disabled"
      class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
      @click="openCamera"
    >
      <CameraIcon class="h-4 w-4" /> Scan
    </button>
  </div>

  <Modal :show="cameraOpen" max-width="sm" @close="closeCamera">
    <div class="p-4">
      <h3 class="mb-2 text-sm font-semibold text-gray-900">Camera scan</h3>
      <div id="jar-scan-camera-reader" class="overflow-hidden rounded-md bg-black" />
      <p v-if="cameraError" class="mt-2 text-xs text-red-600">{{ cameraError }}</p>
      <button
        type="button"
        class="mt-3 w-full rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
        @click="closeCamera"
      >
        Close
      </button>
    </div>
  </Modal>
</template>
