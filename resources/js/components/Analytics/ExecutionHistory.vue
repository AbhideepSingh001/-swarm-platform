<!-- resources/js/components/Analytics/ExecutionHistory.vue -->
<template>
  <div class="execution-history">
    <div class="filters">
      <select v-model="filters.status" @change="fetchResults">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="running">Running</option>
        <option value="completed">Completed</option>
        <option value="failed">Failed</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <input v-model="filters.from" type="date" @change="fetchResults" />
      <input v-model="filters.to" type="date" @change="fetchResults" />
      <button @click="resetFilters">Reset</button>
    </div>

    <div v-if="loading" class="loading">Loading...</div>

    <table v-else class="results-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Task</th>
          <th>Agent</th>
          <th>Status</th>
          <th>Duration</th>
          <th>Started</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="result in results" :key="result.id" :class="`status-${result.status}`">
          <td>{{ result.id }}</td>
          <td>{{ result.task?.name ?? result.task_id }}</td>
          <td>{{ result.agent?.name ?? result.agent_id ?? '-' }}</td>
          <td>
            <span class="badge" :class="result.status">{{ result.status }}</span>
          </td>
          <td>{{ formatDuration(result.duration_ms) }}</td>
          <td>{{ formatDate(result.started_at) }}</td>
          <td>
            <button @click="$emit('view', result.id)">View</button>
            <button @click="$emit('logs', result.id)">Logs</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div class="pagination">
      <button :disabled="page === 1" @click="prevPage">← Prev</button>
      <span>Page {{ page }} of {{ lastPage }}</span>
      <button :disabled="page >= lastPage" @click="nextPage">Next →</button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue'

const props = defineProps({
  taskId: { type: Number, required: true },
  perPage: { type: Number, default: 20 },
})

const emit = defineEmits(['view', 'logs'])

const loading = ref(false)
const results = ref([])
const page = ref(1)
const lastPage = ref(1)

const filters = reactive({
  status: '',
  from: '',
  to: '',
})

const fetchResults = async () => {
  loading.value = true
  const params = new URLSearchParams({
    task_id: props.taskId,
    per_page: props.perPage,
    page: page.value,
    ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v)),
  })

  try {
    const res = await fetch(`/api/results?${params}`)
    const json = await res.json()
    results.value = json.data
    lastPage.value = json.meta.last_page
  } catch (e) {
    console.error('Failed to fetch results:', e)
  } finally {
    loading.value = false
  }
}

const prevPage = () => { page.value--; fetchResults() }
const nextPage = () => { page.value++; fetchResults() }

const resetFilters = () => {
  filters.status = ''
  filters.from = ''
  filters.to = ''
  page.value = 1
  fetchResults()
}

const formatDuration = (ms) => {
  if (!ms) return '-'
  if (ms < 1000) return `${ms}ms`
  return `${(ms / 1000).toFixed(2)}s`
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleString()
}

watch(() => props.taskId, () => { page.value = 1; fetchResults() })
onMounted(fetchResults)
</script>

<style scoped>
.execution-history { font-family: system-ui, sans-serif; }
.filters { display: flex; gap: 12px; margin-bottom: 16px; }
.filters select, .filters input { padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; }
.results-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.results-table th, .results-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
.badge { padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; text-transform: uppercase; }
.badge.completed { background: #dcfce7; color: #166534; }
.badge.failed { background: #fee2e2; color: #991b1b; }
.badge.running { background: #dbeafe; color: #1e40af; }
.badge.pending { background: #fef3c7; color: #92400e; }
.badge.cancelled { background: #f3f4f6; color: #4b5563; }
.status-failed { background: #fef2f2; }
.pagination { display: flex; align-items: center; gap: 16px; margin-top: 16px; justify-content: center; }
.pagination button { padding: 6px 14px; cursor: pointer; }
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
</style>