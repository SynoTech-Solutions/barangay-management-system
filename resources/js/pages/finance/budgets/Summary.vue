<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import { disbursements, index, show } from '@/routes/finance/budgets';
import type { BalanceRow, BudgetSummary } from '@/types';

const INDENT_REM_PER_LEVEL = 1.25;

const props = defineProps<{
    budget: BudgetSummary;
    asOf: string;
    rows: BalanceRow[];
    totals: { appropriation: string; consumed: string; available: string };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.budgets'), href: index() }] },
});

const showZero = ref(false);
const visibleRows = computed(() =>
    props.rows.filter((row) => showZero.value || !row.is_zero),
);
</script>

<template>
    <Head :title="t('finance.summary.title', { year: budget.fiscal_year })" />

    <div class="flex max-w-6xl flex-col gap-4 p-4">
        <Heading
            :title="t('finance.summary.title', { year: budget.fiscal_year })"
            :description="t('finance.summary.as_of', { date: longDate(asOf) })"
        />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Link :href="show(budget.id)" class="text-sm hover:underline">{{
                budget.title
            }}</Link>
            <CheckboxField
                v-model="showZero"
                :label="t('finance.summary.show_zero')"
            />
        </div>
        <p v-if="budget.is_draft" class="text-sm text-muted-foreground">
            {{ t('finance.summary.not_approved') }}
        </p>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('finance.accounts.code') }}</th>
                        <th class="p-3">
                            {{ t('finance.budgets.particulars') }}
                        </th>
                        <th class="p-3 text-right">
                            {{ t('finance.summary.appropriation') }}
                        </th>
                        <th class="p-3 text-right">
                            {{ t('finance.summary.consumed') }}
                        </th>
                        <th class="p-3 text-right">
                            {{ t('finance.summary.available') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in visibleRows"
                        :key="row.id"
                        class="border-t"
                        :class="{
                            'bg-muted/30 font-semibold': !row.is_posting,
                        }"
                    >
                        <td class="p-2 font-mono">{{ row.code }}</td>
                        <td class="p-2">
                            <span
                                :style="{
                                    paddingLeft: `${row.level * INDENT_REM_PER_LEVEL}rem`,
                                }"
                            >
                                <Link
                                    v-if="row.is_posting"
                                    :href="disbursements([budget.id, row.id])"
                                    class="hover:underline"
                                    >{{ row.name }}</Link
                                >
                                <template v-else>{{ row.name }}</template>
                            </span>
                        </td>
                        <td class="p-2 text-right tabular-nums">
                            {{ row.appropriation }}
                        </td>
                        <td class="p-2 text-right tabular-nums">
                            {{ row.consumed }}
                        </td>
                        <td
                            class="p-2 text-right tabular-nums"
                            :class="{ 'text-red-700': row.is_overdrawn }"
                        >
                            {{ row.available }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t bg-muted/30 font-medium">
                    <tr>
                        <td colspan="2" class="p-3 text-right">
                            {{ t('finance.summary.expense_totals') }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ totals.appropriation }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ totals.consumed }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ totals.available }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
