<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

withDefaults(defineProps<{ variant?: 'solid' | 'outline' | 'ghost' | 'block' }>(), { variant: 'outline' });
const { t: $t } = useI18n();
const busy = ref(false);

/** Fortify logout: POST /logout (CSRF handled by Inertia/axios XSRF cookie). A hard navigation afterwards clears all client state. */
function logout() {
    if (busy.value) return;
    busy.value = true;
    router.flushAll();
    router.clearHistory();
    router.post('/logout', {}, {
        onSuccess: () => window.location.replace('/login'),
        onError: () => window.location.replace('/login'),
        onFinish: () => { busy.value = false; },
    });
}
</script>

<template>
  <button
    type="button"
    data-test="logout-button"
    :disabled="busy"
    :aria-busy="busy"
    class="inline-flex min-h-[44px] items-center justify-center gap-2 whitespace-nowrap rounded-xl px-3 py-2 text-sm font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo disabled:cursor-wait disabled:opacity-60"
    :class="{
      'border border-stone bg-white text-ink hover:bg-sand': variant === 'outline',
      'bg-ink text-white hover:bg-indigo-deep': variant === 'solid',
      'text-ink hover:bg-sand': variant === 'ghost',
      'w-full border border-white/20 bg-white/10 text-white hover:bg-white/20': variant === 'block',
    }"
    @click="logout"
  >
    <LogOut class="size-4 rtl:-scale-x-100" aria-hidden="true" />
    <span>{{ $t('تسجيل الخروج') }}</span>
  </button>
</template>
