<template>
  <div 
    class="task-card"
    :class="{ 'overdue': task.is_overdue, 'dragging': isDragging }"
    @dragstart="isDragging = true"
    @dragend="isDragging = false"
  >
    <div class="card-header">
      <span class="priority-badge" :class="`priority-${task.priority}`">
        {{ task.priority }}
      </span>
      <span class="type-badge">{{ task.task_type }}</span>
    </div>
    
    <h4 class="task-title">{{ task.title }}</h4>
    
    <div class="task-meta">
      <div class="progress-bar" v-if="task.progress_percent > 0">
        <div class="progress-fill" :style="{ width: `${task.progress_percent}%` }"></div>
      </div>
      
      <div class="assignees" v-if="task.assignments?.length">
        <AgentAvatar 
          v-for="assignment in task.assignments" 
          :key="assignment.id"
          :agent="assignment.assignable"
          :role="assignment.role"
        />
      </div>
      
      <div class="deadline" v-if="task.deadline_at">
        <span>⏰ {{ formatDeadline(task.deadline_at) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import AgentAvatar from '../Agents/AgentAvatar.vue';

defineProps({
  task: Object,
});

const isDragging = ref(false);

const formatDeadline = (date) => {
  const d = new Date(date);
  const now = new Date();
  const diff = d - now;
  const days = Math.ceil(diff / (1000 * 60 * 60 * 24));
  
  if (days < 0) return 'Overdue';
  if (days === 0) return 'Today';
  if (days === 1) return 'Tomorrow';
  return `${days}d`;
};
</script>

<style scoped>
.task-card {
  background: white;
  border-radius: 0.375rem;
  padding: 0.75rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  cursor: grab;
  transition: transform 0.2s, box-shadow 0.2s;
}

.task-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.task-card.overdue {
  border-left: 3px solid #ef4444;
}

.task-card.dragging {
  opacity: 0.5;
}

.card-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.priority-badge {
  font-size: 0.625rem;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  text-transform: uppercase;
  font-weight: 600;
}

.priority-critical { background: #fee2e2; color: #991b1b; }
.priority-high { background: #ffedd5; color: #9a3412; }
.priority-medium { background: #fef3c7; color: #92400e; }
.priority-low { background: #ecfccb; color: #3f6212; }

.type-badge {
  font-size: 0.625rem;
  color: #64748b;
  text-transform: uppercase;
}

.task-title {
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 0.5rem;
  line-height: 1.25;
}

.progress-bar {
  height: 4px;
  background: #e2e8f0;
  border-radius: 2px;
  margin-bottom: 0.5rem;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #3b82f6;
  border-radius: 2px;
  transition: width 0.3s ease;
}

.assignees {
  display: flex;
  gap: -0.25rem;
}

.deadline {
  font-size: 0.75rem;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.25rem;
}
</style>