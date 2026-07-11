<script setup>
import { onMounted, ref } from 'vue';
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend);

const props = defineProps({ points: Array });
const canvas = ref(null);

onMounted(() => {
  if (!canvas.value) return;
  const labels = props.points.map((p) => p.date);
  const values = props.points.map((p) => Number(p.amount));

  new Chart(canvas.value, {
    type: 'line',
    data: {
      labels,
      datasets: [{ label: 'Capital Raised', data: values, borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,0.2)' }],
    },
    options: { responsive: true, maintainAspectRatio: false },
  });
});
</script>

<template>
  <div style="height: 320px;"><canvas ref="canvas" /></div>
</template>
