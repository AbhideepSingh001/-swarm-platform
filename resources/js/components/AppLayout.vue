<template>
  <div class="app-layout">
    <nav class="sidebar">
      <a 
        href="#" 
        :class="{ active: currentView === 'presence' }" 
        @click.prevent="currentView = 'presence'"
      >
        👥 Presence
      </a>
      <a 
        href="#" 
        :class="{ active: currentView === 'tasks' }" 
        @click.prevent="currentView = 'tasks'"
      >
        📋 Tasks
      </a>
    </nav>
    
    <main class="main-content">
      <PresenceMonitor v-if="currentView === 'presence'" />
      <TaskBoard v-else-if="currentView === 'tasks'" />
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import PresenceMonitor from './PresenceMonitor.vue';
import TaskBoard from './Tasks/TaskBoard.vue';

const currentView = ref('presence');
</script>

<style scoped>
.app-layout {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 200px;
  background: #1e293b;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.sidebar a {
  color: #94a3b8;
  text-decoration: none;
  padding: 0.5rem 0.75rem;
  border-radius: 0.375rem;
  transition: background 0.2s;
  font-size: 0.875rem;
}

.sidebar a:hover,
.sidebar a.active {
  background: #334155;
  color: white;
}

.main-content {
  flex: 1;
  overflow: auto;
}
</style>