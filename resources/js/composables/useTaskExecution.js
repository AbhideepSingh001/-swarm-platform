import { ref, onMounted, onUnmounted } from 'vue';
import Echo from 'laravel-echo';

export function useTaskExecution(taskId) {
  const output = ref('');
  const error = ref('');
  const progress = ref(0);
  const status = ref('pending');
  const metadata = ref({});
  const isRunning = ref(false);

  let channel = null;

  const subscribe = () => {
    channel = Echo.private(`tasks.${taskId}`)
      .listen('.task.started', () => {
        status.value = 'running';
        isRunning.value = true;
        progress.value = 0;
      })
      .listen('.task.progress', (e) => {
        progress.value = e.percent;
      })
      .listen('.task.output.chunked', (e) => {
        output.value += e.chunk;
      })
      .listen('.task.completed', (e) => {
        status.value = 'completed';
        isRunning.value = false;
        progress.value = 100;
        metadata.value = e.result?.metadata || {};
      })
      .listen('.task.failed', (e) => {
        status.value = 'failed';
        isRunning.value = false;
        error.value = e.error;
        metadata.value = e.metadata || {};
      });
  };

  const unsubscribe = () => {
    if (channel) {
      Echo.leave(`private-tasks.${taskId}`);
      channel = null;
    }
  };

  const reset = () => {
    output.value = '';
    error.value = '';
    progress.value = 0;
    status.value = 'pending';
    metadata.value = {};
    isRunning.value = false;
  };

  onMounted(subscribe);
  onUnmounted(unsubscribe);

  return {
    output,
    error,
    progress,
    status,
    metadata,
    isRunning,
    reset,
    unsubscribe,
  };
}