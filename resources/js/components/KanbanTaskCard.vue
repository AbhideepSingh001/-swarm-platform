<template>
  <div 
    class="kanban-card" 
    :class="`status-${task.status}`"
    @click="showExecutionPanel = true"
  >
    <div class="card-header">
      <span class="task-id">#{{ task.id }}</span>
      <span class="driver-badge">{{ task.driver }}</span>
    </div>
    
    <h4 class="task-title">{{ task.title }}</h4>
    
    <div class="task-meta">
      <div class="progress-mini" v-if="task.progress_percent > 0">
        <div class="progress-mini-fill" :style="{ width: task.progress_percent + '%' }"></div>
      </div>
      <span class="attempts" v-if="task.attempts > 0">
        {{ task.attempts }} attempt{{ task.attempts > 1 ? 's' : '' }}
      </span>
    </div>

    <!-- Execution Panel Modal -->
    <Teleport to="body">
      <div v-if="showExecutionPanel" class="modal-overlay" @click.self="showExecutionPanel = false">
        <div class="modal-content">
          <button class="modal-close" @click="showExecutionPanel = false">×</button>
          <TaskExecutionPanel 
            :task-id="task.id" 
            :task="task"
            :max-attempts="5"
          />
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import TaskExecutionPanel from './TaskExecutionPanel.vue';

defineProps({
  task: { type: Object, required: true },
});

const showExecutionPanel = ref(false);
</script>

<style scoped>
.kanban-card {
  background: #1e293b;
  border-radius: 8px;
  padding: 12px;
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;
  border-left: 3px solid transparent;
}

.kanban-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.status-running { border-left-color: #3b82f6; }
.status-completed { border-left-color: #10b981; }
.status-failed { border-left-color: #ef4444; }

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.task-id {
  font-size: 11px;
  color: #64748b;
}

.driver-badge {
  font-size: 10px;
  background: #0f172a;
  padding: 2px 6px;
  border-radius: 4px;
  color: #94a3b8;
  text-transform: uppercase;
}

.task-title {
  font-size: 14px;
  font-weight: 500;
  color: #e2e8f0;
  margin: 0 0 8px;
}

.task-meta {
  display: flex;
  align-items: center;
  gap: 8px;
}

.progress-mini {
  flex: 1;
  height: 4px;
  background: #334155;
  border-radius: 2px;
  overflow: hidden;
}

.progress-mini-fill {
  height: 100%;
  background: #3b82f6;
  border-radius: 2px;
  transition: width 0.3s;
}

.attempts {
  font-size: 11px;
  color: #64748b;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 24px;
}

.modal-content {
  width: 100%;
  max-width: 900px;
  height: 80vh;
  background: #0f172a;
  border-radius: 12px;
  overflow: hidden;
  position: relative;
}

.modal-close {
  position: absolute;
  top: 12px;
  right: 12px;
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 24px;
  cursor: pointer;
  z-index: 10;
}

.modal-close:hover {
  color: #e2e8f0;
}
</style>