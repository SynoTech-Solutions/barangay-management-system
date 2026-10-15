<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import type { PrintHeader } from '@/types';

defineProps<{
    header: PrintHeader;
    title: string;
    subtitle?: string;
    printedAt: string;
    truncatedAt?: number | null;
}>();

function printPage(): void {
    window.print();
}
</script>

<template>
    <div class="mx-auto max-w-5xl bg-white p-8 text-sm text-black print:p-0">
        <div class="mb-4 flex justify-end print:hidden">
            <Button @click="printPage">{{ t('documents.print') }}</Button>
        </div>
        <header class="mb-4 text-center leading-tight">
            <p v-if="header.province">{{ header.province }}</p>
            <p>{{ header.municipality }}</p>
            <p class="font-semibold uppercase">{{ header.barangay }}</p>
            <h1 class="mt-2 text-lg font-bold uppercase">{{ title }}</h1>
            <p v-if="subtitle">{{ subtitle }}</p>
        </header>
        <p v-if="truncatedAt" class="mb-2">
            {{ t('finance.print_truncated', { limit: truncatedAt }) }}
        </p>
        <slot />
        <p class="mt-6 text-xs text-gray-600">
            {{
                t('finance.printed_on', {
                    date: new Date(printedAt).toLocaleString(),
                })
            }}
        </p>
    </div>
</template>
