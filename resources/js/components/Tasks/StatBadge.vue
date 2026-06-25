<template>
  <div class="stat-badge" :class="`color-${color}`">
    <span class="stat-count">{{ count }}</span>
    <span class="stat-label">{{ label }}</span>
  </div>
</template>

<script setup>
defineProps({
  label: String,
  count: Number,
  color: String,
});
</script>

<style scoped>
.stat-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  background: white;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-count {
  font-size: 1.25rem;
  font-weight: 700;
}

.stat-label {
  font-size: 0.875rem;
  color: #64748b;
}

.color-blue .stat-count { color: #3b82f6; }
.color-yellow .stat-count { color: #eab308; }
.color-green .stat-count { color: #22c55e; }
.color-red .stat-count { color: #ef4444; }
</style>