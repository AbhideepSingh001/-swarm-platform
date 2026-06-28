<!-- resources/js/components/Analytics/ResultDetail.vue -->
<template>
  <div class="result-detail">
    <div v-if="loading" class="loading">Loading...</div>
    <div v-else-if="!result" class="empty">Result not found</div>
    <div v-else>
      <header>
        <h2>Result #{{ result.id }}</h2>
        <span class="badge" :class="result.status">{{ result.status }}</span>
      </header>

      <section class="meta">
        <div><label>Task:</label> {{ result.task?.name ?? result.task_id }}</div>
        <div><label>Agent:</label> {{ result.agent?.name ?? result.agent_id ?? '-' }}</div>
        <div><label>Started:</label> {{ formatDate(result.started_at) }}</div>
        <div><label>Completed:</label> {{ formatDate(result.completed_at) }}</div>
        <div><label>Duration:</label> {{ formatDuration(result.duration_ms) }}</div>
      </section>

      <section v-if="result.error_message" class="error">
        <h3>Error</h3>
        <pre>{{ result.error_message }}</pre>
      </section>

      <section v-if="result.output" class="output">
        <h3>Output</h3>
        <pre>{{ JSON.stringify(result.output, null, 2) }}</pre>
      </section>

      <section v-if="result.metadata" class="metadata">
        <h3>Metadata</h3>
        <pre>{{ JSON.stringify(result.metadata, null, 2) }}</pre>
      </section>

      <section class="logs">
        <h3>Execution Logs ({{ logs.length }})</h3>
        <div v-if="logsLoading" class="loading">Loading logs...</div>
        <div v-else-if="logs.length === 0" class="empty">No logs</div>
        <div v-else class="log-list">
          <div
            v-for="log in logs"
            :key="log.id"
            class="log-entry"
            :class="`level-${log.level}`"
          >
            <span class="timestamp">{{ formatDate(log.logged_at) }}</span>
            <span class="level">{{ log.level }}</span>
            <span v-if="log.phase" class="phase">{{ log.phase }}</span>
            <span class="message">{{ log.message }}</span>
          </div>
        </div>
      </section>

      <section class="artifacts">
        <h3>Artifacts ({{ artifacts.length }})</h3>
        <div v-if="artifacts.length === 0" class="empty">No artifacts</div>
        <div v-else class="artifact-list">
          <div v-for="artifact in artifacts" :key="artifact.id" class="artifact-card">
            <div class="artifact-info">
              <strong>{{ artifact.name }}</strong>
              <span class="type">{{ artifact.type }}</span>
              <span class="size">{{ formatBytes(artifact.size_bytes) }}</span>
            </div>
            <a :href="`/api/results/${result.id}/artifacts/${artifact.id}/download`" target="_blank">
              Download
            </a>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
  resultId: { type: Number, required: true },
})

const loading = ref(false)
const logsLoading = ref(false)
const result = ref(null)
const logs = ref([])
const artifacts = ref([])

const fetchResult = async () => {
  loading.value = true
  try {
    const res = await fetch(`/api/results/${props.resultId}`)
    const json = await res.json()
    result.value = json.data
    await Promise.all([fetchLogs(), fetchArtifacts()])
  } catch (e) {
    console.error('Failed to fetch result:', e)
  } finally {
    loading.value = false
  }
}

const fetchLogs = async () => {
  logsLoading.value = true
  try {
    const res = await fetch(`/api/results/${props.resultId}/logs`)
    const json = await res.json()
    logs.value = json.data
  } catch (e) {
    console.error('Failed to fetch logs:', e)
  } finally {
    logsLoading.value = false
  }
}

const fetchArtifacts = async () => {
  try {
    const res = await fetch(`/api/results/${props.resultId}/artifacts`)
    const json = await res.json()
    artifacts.value = json.data
  } catch (e) {
    console.error('Failed to fetch artifacts:', e)
  }
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

const formatBytes = (bytes) => {
  if (!bytes) return '-'
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`
}

watch(() => props.resultId, fetchResult)
onMounted(fetchResult)
</script>

<style scoped>
.result-detail { font-family: system-ui, sans-serif; max-width: 900px; }
header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
.badge.completed { background: #dcfce7; color: #166534; }
.badge.failed { background: #fee2e2; color: #991b1b; }
.badge.running { background: #dbeafe; color: #1e40af; }
.meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; margin-bottom: 20px; padding: 16px; background: #f8fafc; border-radius: 8px; }
.meta label { font-weight: 600; color: #64748b; margin-right: 6px; }
section { margin-bottom: 24px; }
section h3 { font-size: 14px; text-transform: uppercase; color: #64748b; margin-bottom: 10px; letter-spacing: 0.05em; }
pre { background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 8px; overflow-x: auto; font-size: 13px; line-height: 1.5; }
.error pre { background: #450a0a; color: #fecaca; }
.log-list { display: flex; flex-direction: column; gap: 6px; }
.log-entry { display: flex; gap: 12px; padding: 8px 12px; border-radius: 6px; font-size: 13px; align-items: center; }
.log-entry.level-error { background: #fef2f2; }
.log-entry.level-warning { background: #fffbeb; }
.log-entry.level-debug { background: #f8fafc; color: #64748b; }
.timestamp { font-family: monospace; font-size: 12px; color: #94a3b8; min-width: 140px; }
.level { text-transform: uppercase; font-size: 11px; font-weight: 600; min-width: 50px; }
.phase { background: #e2e8f0; padding: 1px 8px; border-radius: 4px; font-size: 11px; }
.message { flex: 1; }
.artifact-list { display: flex; flex-direction: column; gap: 8px; }
.artifact-card { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px; }
.artifact-info { display: flex; align-items: center; gap: 12px; }
.type { background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
.size { color: #64748b; font-size: 13px; }
.empty { color: #94a3b8; font-style: italic; padding: 20px; text-align: center; }
</style>