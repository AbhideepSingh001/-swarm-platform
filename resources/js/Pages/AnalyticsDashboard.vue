<!-- Example: resources/js/Pages/AnalyticsDashboard.vue -->
<template>
  <div class="analytics-dashboard">
    <h1>Analytics Dashboard</h1>
    
    <MetricsPanel />
    
    <div class="split-view">
      <div class="left">
        <ExecutionHistory 
          :task-id="selectedTaskId" 
          @view="showDetail"
          @logs="showLogs"
        />
      </div>
      <div class="right" v-if="selectedResultId">
        <ResultDetail :result-id="selectedResultId" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { ExecutionHistory, ResultDetail, MetricsPanel } from '@/components/Analytics'

const selectedTaskId = ref(1) // or from route param
const selectedResultId = ref(null)

const showDetail = (id) => { selectedResultId.value = id }
const showLogs = (id) => { selectedResultId.value = id }
</script>