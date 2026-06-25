<template>
  <div class="task-board">
    <div class="board-header">
      <h2>Task Orchestration Board</h2>
      
      <div class="stats-bar">
        <StatBadge label="Active" :count="stats.active" color="blue" />
        <StatBadge label="Pending" :count="stats.pending" color="yellow" />
        <StatBadge label="Completed" :count="stats.completed" color="green" />
        <StatBadge label="Overdue" :count="stats.overdue" color="red" />
      </div>
      
      <button @click="showCreateModal = true" class="btn-primary">
        + New Task
      </button>
    </div>

    <div class="kanban-board">
      <KanbanColumn
        v-for="status in columns"
        :key="status"
        :title="formatStatus(status)"
        :status="status"
        :tasks="tasksByStatus[status] || []"
        @dragstart="onDragStart"
        @drop="onDrop"
        @taskclick="openTaskDetail"
      />
    </div>

    <TaskDetailModal
      v-if="selectedTask"
      :task="selectedTask"
      @close="selectedTask = null"
      @comment="addComment"
    />

    <CreateTaskModal
      v-if="showCreateModal"
      @close="showCreateModal = false"
      @created="onTaskCreated"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';
import KanbanColumn from './KanbanColumn.vue';
import TaskDetailModal from './TaskDetailModal.vue';
import CreateTaskModal from './CreateTaskModal.vue';
import StatBadge from './StatBadge.vue';

const tasks = ref([]);
const stats = ref({ active: 0, pending: 0, completed: 0, overdue: 0 });
const selectedTask = ref(null);
const showCreateModal = ref(false);
const draggedTask = ref(null);

const columns = [
  'pending', 'assigned', 'in_progress', 'review', 'blocked', 'completed', 'failed'
];

const tasksByStatus = computed(() => {
  return tasks.value.reduce((acc, task) => {
    if (!acc[task.status]) acc[task.status] = [];
    acc[task.status].push(task);
    return acc;
  }, {});
});

const formatStatus = (status) => {
  return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const fetchTasks = async () => {
  const { data } = await axios.get('/api/tasks');
  tasks.value = data.data;
};

const fetchStats = async () => {
  const { data } = await axios.get('/api/tasks/stats/overview');
  stats.value = {
    active: data.active || 0,
    pending: data.by_status?.pending || 0,
    completed: data.by_status?.completed || 0,
    overdue: data.overdue || 0,
  };
};

const onDragStart = (task) => {
  draggedTask.value = task;
};

const onDrop = async (status) => {
  if (!draggedTask.value || draggedTask.value.status === status) return;

  const task = draggedTask.value;
  const oldStatus = task.status;

  // Optimistic update
  task.status = status;
  
  try {
    if (status === 'completed') {
      await axios.post(`/api/tasks/${task.id}/complete`);
    } else if (status === 'failed') {
      await axios.post(`/api/tasks/${task.id}/fail`, { reason: 'Manually marked as failed' });
    } else {
      await axios.patch(`/api/tasks/${task.id}`, { status });
    }
  } catch (error) {
    task.status = oldStatus;
    console.error('Status update failed:', error);
  }

  draggedTask.value = null;
};

const openTaskDetail = async (task) => {
  const { data } = await axios.get(`/api/tasks/${task.id}`);
  selectedTask.value = data.task;
};

const addComment = async (content) => {
  await axios.post(`/api/tasks/${selectedTask.value.id}/comments`, {
    content,
    type: 'note'
  });
  openTaskDetail(selectedTask.value);
};

const onTaskCreated = (task) => {
  tasks.value.unshift(task);
  showCreateModal.value = false;
};

// WebSocket setup
let echo;

onMounted(() => {
  fetchTasks();
  fetchStats();

  // Initialize Echo with Reverb
  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
  });

  // Global task channel
  echo.channel('tasks')
    .listen('.task.created', (e) => {
      const exists = tasks.value.find(t => t.id === e.task.id);
      if (!exists) {
        tasks.value.unshift(e.task);
      }
      fetchStats();
    })
    .listen('.task.status.changed', (e) => {
      const idx = tasks.value.findIndex(t => t.id === e.task_id);
      if (idx !== -1) {
        tasks.value[idx] = { ...tasks.value[idx], ...e.task };
      }
      fetchStats();
    })
    .listen('.task.assigned', (e) => {
      const idx = tasks.value.findIndex(t => t.id === e.task.id);
      if (idx !== -1) {
        tasks.value[idx] = e.task;
      } else {
        tasks.value.push(e.task);
      }
      fetchStats();
    });

  // Stats refresh every 30s as fallback
  const statsInterval = setInterval(fetchStats, 30000);

  onUnmounted(() => {
    clearInterval(statsInterval);
    if (echo) {
      echo.disconnect();
    }
  });
});
</script>

<style scoped>
.task-board {
  padding: 1.5rem;
  background: #f8fafc;
  min-height: 100vh;
}

.board-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.stats-bar {
  display: flex;
  gap: 1rem;
}

.kanban-board {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1rem;
  overflow-x: auto;
}

.btn-primary {
  background: #3b82f6;
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  border: none;
  cursor: pointer;
  font-weight: 500;
}

.btn-primary:hover {
  background: #2563eb;
}

@media (max-width: 1200px) {
  .kanban-board {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media (max-width: 768px) {
  .kanban-board {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>