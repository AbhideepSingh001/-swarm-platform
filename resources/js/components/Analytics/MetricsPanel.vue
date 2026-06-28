<!-- resources/js/components/Analytics/MetricsPanel.vue -->
<template>
  <div class="metrics-panel">
    <div class="period-selector">
      <button
        v-for="p in periods"
        :key="p.value"
        :class="{ active: period === p.value }"
        @click="setPeriod(p.value)"
      >
        {{ p.label }}
      </button>
    </div>

    <div v-if="loading" class="loading">Loading metrics...</div>

    <template v-else>
      <div class="summary-cards">
        <div class="card">
          <div class="value">{{ summary.total_executions }}</div>
          <div class="label">Total Executions</div>
        </div>
        <div class="card">
          <div class="value" :class="summary.success_rate >= 90 ? 'good' : summary.success_rate >= 70 ? 'warn' : 'bad'">
            {{ summary.success_rate }}%
          </div>
          <div class="label">Success Rate</div>
        </div>
        <div class="card">
          <div class="value">{{ formatDuration(summary.avg_duration_ms) }}</div>
          <div class="label">Avg Duration</div>
        </div>
        <div class="card">
          <div class="value">{{ summary.active_tasks }}</div>
          <div class="label">Active Tasks</div>
        </div>
      </div>

      <div class="chart-section">
        <h3>Executions Over Time</h3>
        <canvas ref="chartCanvas" height="120"></canvas>
      </div>

      <div class="recent-failures" v-if="summary.recent_failures?.length">
        <h3>Recent Failures</h3>
        <table>
          <thead>
            <tr><th>Result</th><th>Task</th><th>Error</th><th>Time</th></tr>
          </thead>
          <tbody>
            <tr v-for="f in summary.recent_failures" :key="f.id">
              <td>#{{ f.id }}</td>
              <td>{{ f.task_id }}</td>
              <td class="error-msg">{{ truncate(f.error_message, 60) }}</td>
              <td>{{ formatDate(f.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'

const loading = ref(false)
const period = ref('7d')
const summary = ref({})
const timeSeries = ref([])
const chartCanvas = ref(null)

const periods = [
  { label: '24h', value: '1d' },
  { label: '7d', value: '7d' },
  { label: '30d', value: '30d' },
]

const setPeriod = (p) => {
  period.value = p
  fetchAll()
}

const fetchAll = async () => {
  loading.value = true
  await Promise.all([fetchSummary(), fetchTimeSeries()])
  loading.value = false
  nextTick(() => drawChart())
}

const fetchSummary = async () => {
  const from = getPeriodStart(period.value)
  try {
    const res = await fetch(`/api/analytics/dashboard?from=${from.toISOString()}&to=${new Date().toISOString()}`)
    const json = await res.json()
    summary.value = json.data
  } catch (e) {
    console.error('Failed to fetch summary:', e)
  }
}

const fetchTimeSeries = async () => {
  const from = getPeriodStart(period.value)
  try {
    const res = await fetch(`/api/analytics/time-series?metric=executions&group_by=day&from=${from.toISOString()}&to=${new Date().toISOString()}`)
    const json = await res.json()
    timeSeries.value = json.data.data
  } catch (e) {
    console.error('Failed to fetch time series:', e)
  }
}

const getPeriodStart = (p) => {
  const now = new Date()
  switch (p) {
    case '1d': return new Date(now - 86400000)
    case '7d': return new Date(now - 7 * 86400000)
    case '30d': return new Date(now - 30 * 86400000)
    default: return new Date(now - 7 * 86400000)
  }
}

const drawChart = () => {
  const canvas = chartCanvas.value
  if (!canvas || timeSeries.value.length === 0) return

  const ctx = canvas.getContext('2d')
  const dpr = window.devicePixelRatio || 1
  const rect = canvas.getBoundingClientRect()
  canvas.width = rect.width * dpr
  canvas.height = rect.height * dpr
  ctx.scale(dpr, dpr)

  const data = timeSeries.value
  const max = Math.max(...data.map(d => d.executions), 1)
  const barWidth = (rect.width / data.length) * 0.7
  const gap = (rect.width / data.length) * 0.3

  ctx.clearRect(0, 0, rect.width, rect.height)

  data.forEach((d, i) => {
    const h = (d.executions / max) * (rect.height - 24)
    const x = i * (barWidth + gap) + gap / 2
    const y = rect.height - h - 20

    // Bar
    const grad = ctx.createLinearGradient(0, y, 0, rect.height - 20)
    grad.addColorStop(0, '#3b82f6')
    grad.addColorStop(1, '#1d4ed8')
    ctx.fillStyle = grad
    ctx.fillRect(x, y, barWidth, h)

    // Label
    ctx.fillStyle = '#64748b'
    ctx.font = '10px system-ui'
    ctx.textAlign = 'center'
    const label = d.period.slice(5) // MM-DD
    ctx.fillText(label, x + barWidth / 2, rect.height - 4)
  })
}

const formatDuration = (ms) => {
  if (!ms) return '-'
  if (ms < 1000) return `${Math.round(ms)}ms`
  return `${(ms / 1000).toFixed(1)}s`
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleString()
}

const truncate = (str, len) => {
  if (!str) return ''
  return str.length > len ? str.slice(0, len) + '...' : str
}

onMounted(() => {
  fetchAll()
  window.addEventListener('resize', drawChart)
})
</script>

<style scoped>
.metrics-panel { font-family: system-ui, sans-serif; }
.period-selector { display: flex; gap: 8px; margin-bottom: 20px; }
.period-selector button { padding: 6px 16px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer; font-size: 13px; }
.period-selector button.active { background: #1e40af; color: white; border-color: #1e40af; }
.summary-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; text-align: center; }
.card .value { font-size: 32px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
.card .value.good { color: #16a34a; }
.card .value.warn { color: #ca8a04; }
.card .value.bad { color: #dc2626; }
.card .label { font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.chart-section { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
.chart-section h3 { font-size: 14px; color: #64748b; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em; }
canvas { width: 100%; }
.recent-failures { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; }
.recent-failures h3 { font-size: 14px; color: #64748b; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em; }
.recent-failures table { width: 100%; font-size: 13px; border-collapse: collapse; }
.recent-failures th, .recent-failures td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f1f5f9; }
.recent-failures th { color: #64748b; font-weight: 600; }
.error-msg { color: #dc2626; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.loading { text-align: center; padding: 40px; color: #64748b; }
</style>