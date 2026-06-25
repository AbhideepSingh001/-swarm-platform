<template>
  <div class="execution-panel">
    <!-- Header -->
    <div class="panel-header">
      <div class="status-badge" :class="statusClass">
        {{ statusLabel }}
      </div>
      <div class="meta" v-if="task">
        <span>Driver: {{ task.driver }}</span>
        <span>Attempts: {{ task.attempts }}/{{ maxAttempts }}</span>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="progress-section">
      <div class="progress-bar">
        <div class="progress-fill" :style="{ width: progress + '%' }"></div>
      </div>
      <span class="progress-text">{{ progress }}%</span>
    </div>

    <!-- Stats -->
    <div class="stats-bar" v-if="stats.length">
      <span v-for="(stat, i) in stats" :key="i" class="stat">
        {{ stat.label }}: {{ stat.value }}
      </span>
    </div>

    <!-- Output Stream -->
    <div class="output-container" ref="outputContainer">
      <div class="output-header">
        <span>Output</span>
        <button @click="clearOutput" class="btn-clear">Clear</button>
      </div>
      <pre class="output-stream" :class="{ 'has-content': output }">{{ output || 'Waiting for output...' }}</pre>
    </div>

    <!-- Error -->
    <div v-if="error" class="error-panel">
      <div class="error-header">Error</div>
      <pre class="error-content">{{ error }}</pre>
    </div>

    <!-- Retry Notification -->
    <div v-if="retryInfo" class="retry-banner">
      <span class="retry-icon">↻</span>
      Retry {{ retryInfo.attempt }}/{{ retryInfo.maxAttempts }} in {{ retryInfo.delay }}s
      <span class="retry-error">{{ retryInfo.error }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import Echo from 'laravel-echo';

const props = defineProps({
  taskId: { type: Number, required: true },
  task: { type: Object, default: null },
  maxAttempts: { type: Number, default: 5 },
});

const output = ref('');
const error = ref('');
const progress = ref(0);
const status = ref('pending');
const tokenCount = ref(0);
const durationMs = ref(null);
const retryInfo = ref(null);
const outputContainer = ref(null);

const statusLabel = computed(() => {
  const labels = {
    pending: 'Pending',
    running: 'Running',
    completed: 'Completed',
    failed: 'Failed',
    cancelled: 'Cancelled',
    retrying: 'Retrying',
  };
  return labels[status.value] || status.value;
});

const statusClass = computed(() => `status-${status.value}`);

const stats = computed(() => {
  const s = [];
  if (tokenCount.value > 0) s.push({ label: 'Tokens', value: tokenCount.value });
  if (durationMs.value) s.push({ label: 'Duration', value: `${(durationMs.value / 1000).toFixed(2)}s` });
  if (props.task?.driver) s.push({ label: 'Driver', value: props.task.driver });
  return s;
});

let channel = null;

const scrollToBottom = () => {
  nextTick(() => {
    if (outputContainer.value) {
      outputContainer.value.scrollTop = outputContainer.value.scrollHeight;
    }
  });
};

const clearOutput = () => {
  output.value = '';
  error.value = '';
};

const subscribe = () => {
  channel = Echo.private(`tasks.${props.taskId}`)
    .listen('.task.started', () => {
      status.value = 'running';
      progress.value = 0;
      retryInfo.value = null;
    })
    .listen('.task.progress', (e) => {
      progress.value = e.percent;
      status.value = 'running';
    })
    .listen('.task.output.chunked', (e) => {
      output.value += e.chunk;
      tokenCount.value = e.token_count;
      scrollToBottom();
    })
    .listen('.task.retrying', (e) => {
      status.value = 'retrying';
      retryInfo.value = {
        attempt: e.attempt,
        maxAttempts: e.max_attempts,
        delay: e.delay_seconds,
        error: e.error,
      };
    })
    .listen('.task.completed', (e) => {
      status.value = 'completed';
      progress.value = 100;
      durationMs.value = e.result?.metadata?.duration_ms;
      retryInfo.value = null;
    })
    .listen('.task.failed', (e) => {
      status.value = 'failed';
      error.value = e.error;
      durationMs.value = e.metadata?.duration_ms;
      retryInfo.value = null;
    });
};

onMounted(() => {
  subscribe();
  // Set initial status if task provided
  if (props.task) {
    status.value = props.task.status;
    progress.value = props.task.progress_percent || 0;
    output.value = props.task.output || '';
    error.value = props.task.error || '';
  }
});

onUnmounted(() => {
  if (channel) {
    Echo.leave(`private-tasks.${props.taskId}`);
  }
});

watch(() => props.taskId, (newId, oldId) => {
  if (newId !== oldId) {
    if (channel) Echo.leave(`private-tasks.${oldId}`);
    clearOutput();
    subscribe();
  }
});
</script>

<style scoped>
.execution-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: #0f172a;
  border-radius: 8px;
  color: #e2e8f0;
  font-family: 'JetBrains Mono', monospace;
  height: 100%;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-pending { background: #475569; }
.status-running { background: #3b82f6; animation: pulse 2s infinite; }
.status-completed { background: #10b981; }
.status-failed { background: #ef4444; }
.status-retrying { background: #f59e0b; animation: pulse 1.5s infinite; }
.status-cancelled { background: #6b7280; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.meta {
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: #94a3b8;
}

.progress-section {
  display: flex;
  align-items: center;
  gap: 12px;
}

.progress-bar {
  flex: 1;
  height: 8px;
  background: #1e293b;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #3b82f6, #8b5cf6);
  border-radius: 4px;
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 12px;
  color: #94a3b8;
  min-width: 36px;
  text-align: right;
}

.stats-bar {
  display: flex;
  gap: 16px;
  font-size: 11px;
  color: #64748b;
}

.stat {
  background: #1e293b;
  padding: 2px 8px;
  border-radius: 4px;
}

.output-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 200px;
  background: #020617;
  border-radius: 6px;
  border: 1px solid #1e293b;
  overflow: hidden;
}

.output-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: #0f172a;
  border-bottom: 1px solid #1e293b;
  font-size: 12px;
  color: #94a3b8;
}

.btn-clear {
  background: transparent;
  border: 1px solid #334155;
  color: #94a3b8;
  padding: 2px 8px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 11px;
}

.btn-clear:hover {
  background: #334155;
}

.output-stream {
  flex: 1;
  padding: 12px;
  margin: 0;
  overflow-y: auto;
  font-size: 13px;
  line-height: 1.5;
  color: #e2e8f0;
  white-space: pre-wrap;
  word-break: break-word;
}

.output-stream:not(.has-content) {
  color: #475569;
  font-style: italic;
}

.error-panel {
  background: #450a0a;
  border: 1px solid #7f1d1d;
  border-radius: 6px;
  padding: 12px;
}

.error-header {
  font-size: 12px;
  font-weight: 600;
  color: #fca5a5;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.error-content {
  margin: 0;
  font-size: 12px;
  color: #fecaca;
  white-space: pre-wrap;
}

.retry-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #451a03;
  border: 1px solid #92400e;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 12px;
  color: #fbbf24;
}

.retry-icon {
  animation: spin 1s linear infinite;
}

.retry-error {
  color: #fcd34d;
  font-style: italic;
  margin-left: auto;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>