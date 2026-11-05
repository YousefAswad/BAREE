import { useEcho } from '@laravel/echo-vue';
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Live text for a run or workflow over the user's private Reverb channel.
 * Text arrives in redacted form ([PERSON_1]); the final restored output is fetched from the server.
 */
export function useRunStream(streamId: () => string | null) {
  const userId = (usePage().props.auth as { user: { id: number } }).user.id;
  const text = ref<Record<string, string>>({});
  const finished = ref<{ status: string; runId: string | null; message?: string } | null>(null);

  useEcho<{ streamId: string; text: string; step: string | null }>(`users.${userId}.runs`, '.run.delta', (e) => {
    if (e.streamId !== streamId()) return;
    const key = e.step ?? 'main';
    text.value[key] = (text.value[key] ?? '') + e.text;
  });

  useEcho<{ runId: string | null; status: string; message?: string }>(`users.${userId}.runs`, '.run.finished', (e) => {
    finished.value = { status: e.status, runId: e.runId, message: e.message };
  });

  return { text, finished };
}
