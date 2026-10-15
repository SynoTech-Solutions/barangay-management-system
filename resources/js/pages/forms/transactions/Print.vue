<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PrintSheet from '@/components/documents/PrintSheet.vue';
import { t } from '@/composables/useI18n';
import type { PrintHeader } from '@/types';

defineProps<{
    document: {
        id: number;
        transaction_number: string;
        template: string | null;
        version: number | null;
        html: string;
        missing: string[];
        print_count: number;
        is_reprint: boolean;
    };
    header: PrintHeader;
}>();
</script>

<template>
    <Head
        :title="`${document.template ?? ''} ${document.transaction_number}`"
    />

    <PrintSheet
        :header="header"
        :print-count="document.print_count"
        :is-reprint="document.is_reprint"
    >
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="form-document" v-html="document.html" />
        <p class="mt-auto text-[8pt]">
            {{
                t('forms.transactions.print_footer', {
                    number: document.transaction_number,
                    version: document.version ?? '',
                })
            }}
        </p>
    </PrintSheet>
</template>
