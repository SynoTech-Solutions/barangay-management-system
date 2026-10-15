<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import type { PrintHeader } from '@/types';

type Paper = 'letter' | 'a4';

defineProps<{
    header: PrintHeader;
    printCount: number;
    isReprint: boolean;
}>();

const PAPERS: Paper[] = ['letter', 'a4'];

const paper = ref<Paper>('letter');

function printPage(): void {
    window.print();
}
</script>

<template>
    <div class="min-h-screen bg-white p-6 text-black print:p-0">
        <div class="mb-4 flex flex-wrap items-center gap-2 print:hidden">
            <Button @click="printPage">{{ t('documents.print') }}</Button>
            <Button
                v-for="option in PAPERS"
                :key="option"
                size="sm"
                :variant="paper === option ? 'default' : 'outline'"
                @click="paper = option"
                >{{ t(`documents.paper_${option}`) }}</Button
            >
            <span class="text-sm text-muted-foreground">{{
                t('documents.prints', { count: printCount })
            }}</span>
        </div>

        <article
            class="document-sheet relative mx-auto flex flex-col gap-4 border p-[18mm] font-serif text-[11pt] leading-snug print:border-0"
            :class="paper === 'a4' ? 'sheet-a4' : 'sheet-letter'"
        >
            <p
                v-if="isReprint"
                class="absolute top-[8mm] right-[12mm] rounded border border-black px-2 font-sans text-[9pt] font-bold uppercase"
            >
                {{ t('documents.reprint') }}
            </p>
            <header class="text-center leading-tight">
                <p>{{ t('print.republic') }}</p>
                <p v-if="header.province">{{ header.province }}</p>
                <p>{{ header.municipality }}</p>
                <p class="text-[13pt] font-bold uppercase">
                    {{ header.barangay }}
                </p>
                <p v-if="header.telephone" class="text-[9pt]">
                    {{ t('documents.telephone', { number: header.telephone }) }}
                </p>
            </header>
            <slot />
        </article>
    </div>
</template>

<style>
.sheet-letter {
    width: 215.9mm;
    min-height: 279.4mm;
}

.sheet-a4 {
    width: 210mm;
    min-height: 297mm;
}

@page letter-sheet {
    size: letter portrait;
    margin: 0;
}

@page a4-sheet {
    size: A4 portrait;
    margin: 0;
}

@media print {
    .sheet-letter {
        page: letter-sheet;
    }

    .sheet-a4 {
        page: a4-sheet;
    }
}
</style>
