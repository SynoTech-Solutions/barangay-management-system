<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import { index, summary } from '@/routes/finance/budgets';
import type { BudgetSummary, DisbursementRow } from '@/types';

defineProps<{
    budget: BudgetSummary;
    account: { id: number; code: string; name: string };
    records: DisbursementRow[];
    total: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.budgets'), href: index() }] },
});
</script>

<template>
    <Head :title="account.name" />

    <div class="flex max-w-6xl flex-col gap-4 p-4">
        <Heading
            :title="account.name"
            :description="
                t('finance.disbursements.description', {
                    code: account.code,
                    year: budget.fiscal_year,
                })
            "
        />
        <Link :href="summary(budget.id)" class="text-sm hover:underline">{{
            t('finance.budgets.summary')
        }}</Link>

        <p v-if="records.length === 0" class="text-sm text-muted-foreground">
            {{ t('finance.disbursements.empty') }}
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">
                            {{ t('finance.disbursements.voucher') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.disbursements.date') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.disbursements.payee') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.disbursements.particulars') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.disbursements.check') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.disbursements.liquidation') }}
                        </th>
                        <th class="p-3 text-right">
                            {{ t('finance.budgets.amount') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="record in records"
                        :key="`${record.voucher_number}-${record.particulars}`"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">
                            {{ record.voucher_number }}
                        </td>
                        <td class="p-3">{{ longDate(record.date) }}</td>
                        <td class="p-3">{{ record.payee }}</td>
                        <td class="p-3">{{ record.particulars }}</td>
                        <td class="p-3">{{ record.check_number }}</td>
                        <td class="p-3">{{ record.liquidation_number }}</td>
                        <td class="p-3 text-right tabular-nums">
                            {{ record.amount }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t bg-muted/30 font-medium">
                    <tr>
                        <td colspan="6" class="p-3 text-right">
                            {{ t('documents.totals') }}
                        </td>
                        <td class="p-3 text-right tabular-nums">{{ total }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
