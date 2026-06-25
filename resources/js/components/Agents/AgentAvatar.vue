<template>
  <div class="agent-avatar" :title="`${agent?.name || 'Unknown'} (${role})`">
    <div class="avatar-circle" :style="{ backgroundColor: stringToColor(agent?.name || 'A') }">
      {{ (agent?.name || 'A')[0].toUpperCase() }}
    </div>
    <span v-if="role === 'primary'" class="role-indicator">★</span>
  </div>
</template>

<script setup>
defineProps({
  agent: Object,
  role: String,
});

const stringToColor = (str) => {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash);
  }
  const c = (hash & 0x00FFFFFF).toString(16).padStart(6, '0');
  return `#${c}`;
};
</script>

<style scoped>
.agent-avatar {
  position: relative;
  display: inline-block;
}

.avatar-circle {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.625rem;
  font-weight: 600;
}

.role-indicator {
  position: absolute;
  bottom: -0.25rem;
  right: -0.25rem;
  font-size: 0.5rem;
  color: #eab308;
}
</style>