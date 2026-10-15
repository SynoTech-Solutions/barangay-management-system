<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';

defineProps<{
    check: {
        id: number;
        check_number: string;
        payee_name: string;
        check_date: string;
        amount: string;
        amount_in_words: string;
        print_count: number;
    };
}>();

const THOUSANDS = /\B(?=(\d{3})+(?!\d))/g;

function grouped(amount: string): string {
    const [pesos, cents = '00'] = amount.split('.');

    return `${pesos.replace(THOUSANDS, ',')}.${cents}`;
}

function guarded(value: string): string {
    return `*** ${value} ***`;
}

function printPage(): void {
    window.print();
}
</script>

<template>
    <Head :title="`${t('finance.checks.print_title')} ${check.check_number}`" />

    <div class="min-h-screen bg-white p-6 text-black print:p-0">
        <div class="mb-4 flex flex-wrap items-center gap-2 print:hidden">
            <Button @click="printPage">{{ t('documents.print') }}</Button>
            <span class="text-sm text-muted-foreground">{{
                t('finance.checks.print_help', { count: check.print_count })
            }}</span>
        </div>

        <article class="check-stock font-mono text-[11pt]">
            <p class="check-field check-date">
                {{ longDate(check.check_date) }}
            </p>
            <p class="check-field check-payee">
                {{ guarded(check.payee_name) }}
            </p>
            <p class="check-field check-amount">
                {{ guarded(grouped(check.amount)) }}
            </p>
            <p class="check-field check-words">
                {{ guarded(check.amount_in_words) }}
            </p>
        </article>
    </div>
</template>

<style>
.check-stock {
    --check-width: 203mm;
    --check-height: 76mm;
    --check-date-top: 10mm;
    --check-date-left: 150mm;
    --check-payee-top: 22mm;
    --check-payee-left: 25mm;
    --check-payee-width: 120mm;
    --check-amount-top: 22mm;
    --check-amount-left: 150mm;
    --check-words-top: 31mm;
    --check-words-left: 18mm;
    --check-words-width: 175mm;

    position: relative;
    width: var(--check-width);
    height: var(--check-height);
    border: 1px dashed #999;
}

.check-field {
    position: absolute;
    margin: 0;
    white-space: nowrap;
}

.check-date {
    top: var(--check-date-top);
    left: var(--check-date-left);
}

.check-payee {
    top: var(--check-payee-top);
    left: var(--check-payee-left);
    width: var(--check-payee-width);
    overflow: hidden;
}

.check-amount {
    top: var(--check-amount-top);
    left: var(--check-amount-left);
}

.check-words {
    top: var(--check-words-top);
    left: var(--check-words-left);
    width: var(--check-words-width);
    white-space: normal;
}

@page check-sheet {
    size: 203mm 76mm;
    margin: 0;
}

@media print {
    .check-stock {
        page: check-sheet;
        border: 0;
    }
}
</style>
