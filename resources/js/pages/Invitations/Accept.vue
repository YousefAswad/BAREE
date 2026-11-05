<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/composables/useLocale';

const { t: $t } = useI18n();
const { dir } = useLocale();

const props = defineProps<{ valid: boolean; email: string | null; name: string | null; token: string | null }>();
const form = useForm({ name: props.name ?? '', password: '', password_confirmation: '', accept_policy: false });
</script>

<template>
  <Head :title="$t('فعّل حسابك')" />
  <main :dir="dir" class="flex min-h-screen items-center justify-center bg-sand p-6">
    <section class="w-full max-w-md rounded-3xl border border-stone bg-white p-8">
      <p dir="ltr" class="font-display text-right text-xl font-extrabold tracking-wider">BAREE</p>
      <template v-if="valid">
        <h1 class="font-display mt-4 text-3xl font-extrabold">{{ $t('أهلًا فيك') }}</h1>
        <p class="mt-1 text-subtle">{{ $t('فعّل حسابك:') }} <span dir="ltr">{{ email }}</span></p>
        <form class="mt-6 flex flex-col gap-4" @submit.prevent="form.post(`/invitations/${token}`)">
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الاسم الكامل') }}<input v-model="form.name" required autocomplete="name" class="min-h-[48px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('كلمة المرور (١٢ حرف على الأقل، أحرف كبيرة وصغيرة وأرقام)') }}<input v-model="form.password" type="password" required minlength="12" autocomplete="new-password" class="min-h-[48px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('أعد كلمة المرور') }}<input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="min-h-[48px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex items-start gap-2 text-sm"><input v-model="form.accept_policy" type="checkbox" required class="mt-1" />{{ $t('موافق على سياسة الاستخدام والخصوصية بمؤسستي.') }}</label>
          <p v-for="(e, k) in form.errors" :key="k" role="alert" class="text-sm text-[#9B1C1C]">{{ e }}</p>
          <button type="submit" :disabled="form.processing" class="min-h-[52px] rounded-2xl bg-ink font-bold text-white">{{ $t('فعّل الحساب') }}</button>
        </form>
      </template>
      <template v-else>
        <h1 class="font-display mt-4 text-2xl font-extrabold">{{ $t('الرابط مش صالح') }}</h1>
        <p class="mt-2 leading-7 text-subtle">{{ $t('ممكن يكون انتهى أو انستخدم قبل. اطلب دعوة جديدة من مسؤول المنصة بمؤسستك.') }}</p>
      </template>
    </section>
  </main>
</template>
