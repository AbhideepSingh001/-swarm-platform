<template>
  <div class="presence-monitor">
    <h2>Agent Presence Monitor</h2>
    
    <div class="status">
      <p>Connection: <span :class="connectionStatus">{{ connectionStatus }}</span></p>
      <p>Online Agents: {{ onlineAgents.length }}</p>
    </div>

    <div class="online-agents">
      <h3>Online Agents</h3>
      <ul v-if="onlineAgents.length">
        <li v-for="agent in onlineAgents" :key="agent.agent_id">
          {{ agent.name }} ({{ agent.role }}) 
          <span class="channel">{{ agent.current_channel }}</span>
        </li>
      </ul>
      <p v-else>No agents online</p>
    </div>

    <div class="events-log">
      <h3>Events Log</h3>
      <div v-for="(event, index) in events" :key="index" class="event">
        <span class="timestamp">{{ event.time }}</span>
        <span class="type">{{ event.type }}</span>
        <span class="data">{{ JSON.stringify(event.data) }}</span>
      </div>
      <p v-if="!events.length">No events yet</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const connectionStatus = ref('connecting');
const onlineAgents = ref([]);
const events = ref([]);

let presenceChannel = null;
let agentChannel = null;

const addEvent = (type, data) => {
  events.value.unshift({
    time: new Date().toLocaleTimeString(),
    type,
    data,
  });
  if (events.value.length > 20) events.value.pop();
};

const fetchOnlineAgents = async () => {
  try {
    const response = await axios.get('/api/presence/online');
    onlineAgents.value = response.data.online_agents;
  } catch (error) {
    addEvent('fetch_error', error.message);
  }
};

onMounted(() => {
  if (!window.Echo) {
    connectionStatus.value = 'error';
    addEvent('error', 'Echo is not initialized');
    return;
  }

  presenceChannel = window.Echo.channel('swarm.presence')
    .subscribed(() => {
      connectionStatus.value = 'connected';
      addEvent('subscribed', { channel: 'swarm.presence' });
    })
    .error((error) => {
      connectionStatus.value = 'error';
      addEvent('error', error);
    })
    .listen('.agent.online', (e) => {
      addEvent('agent.online', e);
      fetchOnlineAgents();
    })
    .listen('.agent.offline', (e) => {
      addEvent('agent.offline', e);
      fetchOnlineAgents();
    })
    .listen('.agent.presence.updated', (e) => {
      addEvent('agent.presence.updated', e);
      fetchOnlineAgents();
    });

  agentChannel = window.Echo.channel('agent.1')
    .listen('.agent.message.received', (e) => {
      addEvent('agent.message.received', e);
    });

  fetchOnlineAgents();
});

onUnmounted(() => {
  if (presenceChannel) presenceChannel.unsubscribe();
  if (agentChannel) agentChannel.unsubscribe();
});
</script>

<style scoped>
.presence-monitor {
  padding: 20px;
  max-width: 800px;
  margin: 0 auto;
  font-family: Arial, sans-serif;
}

.status {
  background: #f0f0f0;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.connected { color: green; font-weight: bold; }
.connecting { color: orange; font-weight: bold; }
.error { color: red; font-weight: bold; }

.online-agents ul {
  list-style: none;
  padding: 0;
}

.online-agents li {
  padding: 8px;
  background: #e8f5e9;
  margin-bottom: 5px;
  border-radius: 4px;
}

.channel {
  font-size: 0.8em;
  color: #666;
  margin-left: 10px;
}

.events-log {
  margin-top: 20px;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 15px;
  max-height: 400px;
  overflow-y: auto;
}

.event {
  padding: 8px;
  border-bottom: 1px solid #eee;
  font-family: monospace;
  font-size: 0.85em;
}

.timestamp { color: #999; margin-right: 10px; }
.type { color: #2196f3; font-weight: bold; margin-right: 10px; }
</style>