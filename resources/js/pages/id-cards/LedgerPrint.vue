<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import type { IdCard, IdCardLedgerFilters } from '@/types';

const props = defineProps<{
    cards: IdCard[];
    total: number;
    limit: number;
    filters: IdCardLedgerFilters;
    barangay: string;
    printedAt: string;
}>();

const title = computed(() =>
    t(`id_cards.ledger_title_${props.filters.status ?? 'all'}`),
);

const amountTotal = computed(() =>
    props.cards
        .filter((card) => card.status !== 'cancelled')
        .reduce((sum, card) => sum + Number(card.amount), 0)
        .toFixed(2),
);

function printPage(): void {
    window.print();
}
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto max-w-5xl bg-white p-8 text-sm text-black print:p-0">
        <div class="mb-4 flex justify-end print:hidden">
            <Button @click="printPage">{{ t('reports.print') }}</Button>
        </div>
        <header class="mb-4 text-center">
            <p class="font-semibold">{{ barangay }}</p>
            <h1 class="text-lg font-bold uppercase">{{ title }}</h1>
            <p v-if="filters.from || filters.to">
                {{
                    t('id_cards.ledger_range', {
                        from: filters.from ?? '',
                        to: filters.to ?? '',
                    })
                }}
            </p>
        </header>
        <p v-if="total > cards.length" class="mb-2">
            {{ t('id_cards.ledger_truncated', { limit, total }) }}
        </p>
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-1">{{ t('id_cards.card_number') }}</th>
                    <th class="py-1">{{ t('id_cards.resident') }}</th>
                    <th class="py-1">{{ t('id_cards.status') }}</th>
                    <th class="py-1">{{ t('id_cards.or_number') }}</th>
                    <th class="py-1 text-right">{{ t('id_cards.amount') }}</th>
                    <th class="py-1">{{ t('id_cards.issued_at') }}</th>
                    <th class="py-1">{{ t('id_cards.issued_by') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="card in cards" :key="card.id" class="border-b">
                    <td class="py-1 font-mono">{{ card.card_number }}</td>
                    <td class="py-1">{{ card.resident?.full_name }}</td>
                    <td class="py-1">{{ card.status_label }}</td>
                    <td class="py-1">{{ card.or_number ?? '' }}</td>
                    <td class="py-1 text-right">{{ card.amount }}</td>
                    <td class="py-1">
                        {{ new Date(card.issued_at).toLocaleDateString() }}
                    </td>
                    <td class="py-1">{{ card.issued_by ?? '' }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="py-1 font-semibold">
                        {{ t('common.total', { total }) }}
                    </td>
                    <td class="py-1 text-right font-semibold">
                        {{ amountTotal }}
                    </td>
                    <td colspan="2" />
                </tr>
            </tfoot>
        </table>
        <p class="mt-4 text-xs">
            {{
                t('reports.printed_at', {
                    date: new Date(printedAt).toLocaleString(),
                })
            }}
        </p>
    </div>
</template>
