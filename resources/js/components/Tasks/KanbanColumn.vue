<template>
  <div 
    class="kanban-column"
    @dragover.prevent
    @drop.prevent="$emit('drop', status)"
  >
    <div class="column-header" :class="`header-${status}`">
      <h3>{{ title }}</h3>
      <span class="count">{{ tasks.length }}</span>
    </div>
    
    <div class="task-list">
      <TaskCard
        v-for="task in sortedTasks"
        :key="task.id"
        :task="task"
        draggable="true"
        @dragstart="$emit('dragstart', task)"
        @click="$emit('taskclick', task)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import TaskCard from './TaskCard.vue';

const props = defineProps({
  title: String,
  status: String,
  tasks: Array,
});

defineEmits(['dragstart', 'drop', 'taskclick']);

const sortedTasks = computed(() => {
  return [...props.tasks].sort((a, b) => {
    const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
    return priorityOrder[a.priority] - priorityOrder[b.priority];
  });
});
</script>

<style scoped>
.kanban-column {
  background: #e2e8f0;
  border-radius: 0.5rem;
  min-height: 500px;
  display: flex;
  flex-direction: column;
}

.column-header {
  padding: 0.75rem;
  border-radius: 0.5rem 0.5rem 0 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 600;
}

.header-pending { background: #fef3c7; color: #92400e; }
.header-assigned { background: #dbeafe; color: #1e40af; }
.header-in_progress { background: #bfdbfe; color: #1e3a8a; }
.header-review { background: #e9d5ff; color: #6b21a8; }
.header-blocked { background: #fee2e2; color: #991b1b; }
.header-completed { background: #dcfce7; color: #166534; }
.header-failed { background: #fecaca; color: #991b1b; }

.count {
  background: rgba(255,255,255,0.5);
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.875rem;
}

.task-list {
  padding: 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  flex: 1;
}
</style>