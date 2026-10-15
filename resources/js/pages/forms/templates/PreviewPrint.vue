<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PrintSheet from '@/components/documents/PrintSheet.vue';
import { t } from '@/composables/useI18n';
import type { PrintHeader } from '@/types';

defineProps<{
    template: { id: number; name: string; version: number };
    html: string;
    missing: string[];
    header: PrintHeader;
}>();
</script>

<template>
    <Head :title="`${t('forms.templates.preview')} ${template.name}`" />

    <div class="bg-amber-50 p-2 text-center text-sm text-black print:hidden">
        {{
            t('forms.templates.preview_banner', {
                name: template.name,
                version: template.version,
            })
        }}
        <span v-if="missing.length" class="block text-red-700">{{
            t('forms.templates.missing', { tokens: missing.join(', ') })
        }}</span>
    </div>
    <PrintSheet :header="header" :print-count="0" :is-reprint="false">
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="form-document" v-html="html" />
    </PrintSheet>
</template>
