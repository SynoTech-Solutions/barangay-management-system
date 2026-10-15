<script setup lang="ts">
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import type { DocumentPayment } from '@/types';

defineProps<{
    document: DocumentPayment & {
        clearance_number: string;
        control_number: string;
    };
}>();
</script>

<template>
    <dl class="grid grid-cols-[auto_1fr] gap-x-3 text-[9pt]">
        <dt>{{ t('documents.certificate_number') }}</dt>
        <dd class="font-mono">{{ document.clearance_number }}</dd>
        <dt>{{ t('documents.control_number') }}</dt>
        <dd class="font-mono">{{ document.control_number }}</dd>
        <dt>{{ t('documents.or_number') }}</dt>
        <dd>{{ document.or_number ?? '' }}</dd>
        <dt>{{ t('documents.amount') }}</dt>
        <dd>{{ document.amount }}</dd>
        <template v-if="document.doc_stamp !== null">
            <dt>{{ t('documents.doc_stamp') }}</dt>
            <dd>{{ document.doc_stamp }}</dd>
            <dt>{{ t('documents.total') }}</dt>
            <dd>{{ document.total }}</dd>
        </template>
        <template v-if="document.ctc">
            <dt>{{ t('documents.ctc_number') }}</dt>
            <dd>{{ document.ctc.number ?? '' }}</dd>
            <dt>{{ t('documents.ctc_place') }}</dt>
            <dd>{{ document.ctc.place ?? '' }}</dd>
            <dt>{{ t('documents.ctc_date') }}</dt>
            <dd>{{ longDate(document.ctc.date) }}</dd>
        </template>
    </dl>
</template>
