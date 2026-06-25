<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <h2>Create New Task</h2>
      
      <form @submit.prevent="submit">
        <div class="form-group">
          <label>Title</label>
          <input v-model="form.title" required />
        </div>
        
        <div class="form-group">
          <label>Description</label>
          <textarea v-model="form.description" rows="3"></textarea>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Priority</label>
            <select v-model="form.priority">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>
          
          <div class="form-group">
            <label>Type</label>
            <select v-model="form.task_type">
              <option value="custom">Custom</option>
              <option value="code_generation">Code Generation</option>
              <option value="code_review">Code Review</option>
              <option value="testing">Testing</option>
              <option value="documentation">Documentation</option>
              <option value="research">Research</option>
              <option value="data_processing">Data Processing</option>
              <option value="communication">Communication</option>
            </select>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Deadline</label>
            <input type="datetime-local" v-model="form.deadline_at" />
          </div>
          
          <div class="form-group">
            <label>Estimated Duration (min)</label>
            <input type="number" v-model="form.estimated_duration_minutes" />
          </div>
        </div>
        
        <div class="form-group">
          <label>
            <input type="checkbox" v-model="form.auto_assign" />
            Auto-assign to agent
          </label>
        </div>
        
        <div class="form-actions">
          <button type="button" @click="$emit('close')" class="btn-secondary">Cancel</button>
          <button type="submit" class="btn-primary">Create Task</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import axios from 'axios';

const emit = defineEmits(['close', 'created']);

const form = reactive({
  title: '',
  description: '',
  priority: 'medium',
  task_type: 'custom',
  deadline_at: '',
  estimated_duration_minutes: null,
  auto_assign: false,
});

const submit = async () => {
  const { data } = await axios.post('/api/tasks', form);
  emit('created', data.task);
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
  max-width: 500px;
  padding: 1.5rem;
}

h2 {
  margin-bottom: 1rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 0.25rem;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}

.btn-primary { background: #3b82f6; color: white; }
.btn-secondary { background: #e2e8f0; color: #374151; }

button {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  border: none;
  cursor: pointer;
}
</style>