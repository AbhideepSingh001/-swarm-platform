<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <header class="modal-header">
        <div>
          <span class="status-badge" :class="`status-${task.status}`">
            {{ task.status }}
          </span>
          <h2>{{ task.title }}</h2>
        </div>
        <button @click="$emit('close')" class="close-btn">&times;</button>
      </header>

      <div class="modal-body">
        <section class="task-info">
          <div class="info-grid">
            <div>
              <label>Type</label>
              <span>{{ task.task_type }}</span>
            </div>
            <div>
              <label>Priority</label>
              <span :class="`priority-${task.priority}`">{{ task.priority }}</span>
            </div>
            <div>
              <label>Progress</label>
              <span>{{ task.progress_percent }}%</span>
            </div>
            <div v-if="task.deadline_at">
              <label>Deadline</label>
              <span :class="{ 'overdue': task.is_overdue }">
                {{ new Date(task.deadline_at).toLocaleString() }}
              </span>
            </div>
          </div>
          
          <p class="description">{{ task.description }}</p>
          
          <div v-if="task.payload" class="payload">
            <label>Payload</label>
            <pre>{{ JSON.stringify(task.payload, null, 2) }}</pre>
          </div>
        </section>

        <section class="task-timeline">
          <h3>Activity</h3>
          <div class="timeline">
            <div 
              v-for="comment in task.comments" 
              :key="comment.id"
              class="timeline-item"
              :class="`type-${comment.type}`"
            >
              <div class="timeline-marker"></div>
              <div class="timeline-content">
                <div class="timeline-header">
                  <strong>{{ comment.commentable?.name || 'System' }}</strong>
                  <span class="timestamp">{{ formatTime(comment.created_at) }}</span>
                </div>
                <p>{{ comment.content }}</p>
              </div>
            </div>
          </div>
        </section>

        <section class="task-actions">
          <div v-if="task.status === 'pending'" class="action-group">
            <button @click="assignTask" class="btn-primary">Assign to Agent</button>
          </div>
          
          <div v-if="task.status === 'assigned'" class="action-group">
            <button @click="acceptTask" class="btn-success">Accept Task</button>
          </div>
          
          <div v-if="task.status === 'in_progress'" class="action-group">
            <input 
              v-model="progressInput" 
              type="range" 
              min="0" 
              max="100" 
              class="progress-slider"
            />
            <button @click="updateProgress" class="btn-primary">
              Update Progress ({{ progressInput }}%)
            </button>
            <button @click="completeTask" class="btn-success">Complete</button>
            <button @click="failTask" class="btn-danger">Mark Failed</button>
          </div>
        </section>

        <section class="add-comment">
          <textarea 
            v-model="newComment" 
            placeholder="Add a comment..."
            rows="3"
          ></textarea>
          <button @click="submitComment" class="btn-primary">Add Comment</button>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  task: Object,
});

const emit = defineEmits(['close', 'comment']);

const progressInput = ref(props.task.progress_percent);
const newComment = ref('');

watch(() => props.task, (newTask) => {
  progressInput.value = newTask.progress_percent;
});

const formatTime = (date) => {
  return new Date(date).toLocaleString();
};

const assignTask = async () => {
  await axios.post(`/api/tasks/${props.task.id}/assign`);
};

const acceptTask = async () => {
  await axios.post(`/api/tasks/${props.task.id}/accept`, { agent_id: 1 });
};

const updateProgress = async () => {
  await axios.post(`/api/tasks/${props.task.id}/progress`, {
    percent: progressInput.value,
  });
};

const completeTask = async () => {
  await axios.post(`/api/tasks/${props.task.id}/complete`);
  emit('close');
};

const failTask = async () => {
  const reason = prompt('Reason for failure?');
  if (reason) {
    await axios.post(`/api/tasks/${props.task.id}/fail`, { reason });
    emit('close');
  }
};

const submitComment = () => {
  if (!newComment.value.trim()) return;
  emit('comment', newComment.value);
  newComment.value = '';
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal-content {
  background: white;
  border-radius: 0.75rem;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.modal-header h2 {
  margin-top: 0.5rem;
  font-size: 1.25rem;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #64748b;
}

.modal-body {
  padding: 1.5rem;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}

.info-grid label {
  display: block;
  font-size: 0.75rem;
  color: #64748b;
  text-transform: uppercase;
  margin-bottom: 0.25rem;
}

.description {
  color: #374151;
  line-height: 1.5;
  margin-bottom: 1rem;
}

.payload pre {
  background: #f1f5f9;
  padding: 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  overflow-x: auto;
}

.timeline {
  position: relative;
  padding-left: 1.5rem;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 0.375rem;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #e2e8f0;
}

.timeline-item {
  position: relative;
  margin-bottom: 1rem;
}

.timeline-marker {
  position: absolute;
  left: -1.125rem;
  top: 0.375rem;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background: #3b82f6;
}

.type-system .timeline-marker { background: #8b5cf6; }
.type-blocker .timeline-marker { background: #ef4444; }
.type-result .timeline-marker { background: #22c55e; }

.timeline-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.25rem;
}

.timestamp {
  font-size: 0.75rem;
  color: #94a3b8;
}

.action-group {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.btn-primary { background: #3b82f6; color: white; }
.btn-success { background: #22c55e; color: white; }
.btn-danger { background: #ef4444; color: white; }

button {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  border: none;
  cursor: pointer;
  font-weight: 500;
}

.progress-slider {
  width: 100%;
  margin-bottom: 0.5rem;
}

.add-comment {
  margin-top: 1.5rem;
  border-top: 1px solid #e2e8f0;
  padding-top: 1rem;
}

.add-comment textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
  margin-bottom: 0.5rem;
  resize: vertical;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-assigned { background: #dbeafe; color: #1e40af; }
.status-in_progress { background: #bfdbfe; color: #1e3a8a; }
.status-review { background: #e9d5ff; color: #6b21a8; }
.status-blocked { background: #fee2e2; color: #991b1b; }
.status-completed { background: #dcfce7; color: #166534; }
.status-failed { background: #fecaca; color: #991b1b; }
</style>