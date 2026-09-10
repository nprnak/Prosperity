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
      datasets: [{
        label: 'Capital Raised',
        data: values,
        borderColor: '#0a2140',
        backgroundColor: 'rgba(10,33,64,0.08)',
        pointBackgroundColor: '#0a2140',
        pointRadius: 3,
        tension: 0.25,
        fill: true,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } },
    },
  });
});
</script>

<template>
  <div style="height: 320px;"><canvas ref="canvas" /></div>
</template>
