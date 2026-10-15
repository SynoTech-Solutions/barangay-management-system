<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportSheet from '@/components/finance/ReportSheet.vue';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import type {
    FormTransaction,
    FormTransactionFilters,
    PrintHeader,
} from '@/types';

defineProps<{
    header: PrintHeader;
    rows: Array<FormTransaction & { field_values: Record<string, string> }>;
    fieldColumns: Array<{ key: string; label: string }>;
    count: number;
    total: string;
    limit: number;
    filters: FormTransactionFilters;
    printedAt: string;
}>();
</script>

<template>
    <Head :title="t('forms.transactions.print_title')" />

    <ReportSheet
        :header="header"
        :title="t('forms.transactions.print_title')"
        :subtitle="
            filters.from || filters.to
                ? t('finance.date_range', {
                      from: longDate(filters.from),
                      to: longDate(filters.to),
                  })
                : t('finance.all_dates')
        "
        :printed-at="printedAt"
        :truncated-at="count > rows.length ? limit : null"
    >
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-1">{{ t('documents.status') }}</th>
                    <th class="py-1">{{ t('forms.transactions.number') }}</th>
                    <th class="py-1">{{ t('forms.transactions.resident') }}</th>
                    <th class="py-1">{{ t('forms.transactions.template') }}</th>
                    <th class="py-1">{{ t('documents.or_number') }}</th>
                    <th class="py-1 text-right">{{ t('documents.amount') }}</th>
                    <th
                        v-for="column in fieldColumns"
                        :key="column.key"
                        class="py-1"
                    >
                        {{ column.label }}
                    </th>
                    <th class="py-1">{{ t('documents.issued_at') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.id" class="border-b">
                    <td class="py-1">{{ row.status_label }}</td>
                    <td class="py-1 font-mono">
                        {{ row.transaction_number }}
                    </td>
                    <td class="py-1">{{ row.resident?.full_name }}</td>
                    <td class="py-1">{{ row.template }}</td>
                    <td class="py-1 font-mono">{{ row.or_number }}</td>
                    <td class="py-1 text-right tabular-nums">
                        {{ row.amount }}
                    </td>
                    <td
                        v-for="column in fieldColumns"
                        :key="column.key"
                        class="py-1"
                    >
                        {{ row.field_values[column.key] }}
                    </td>
                    <td class="py-1">
                        {{ new Date(row.issued_at).toLocaleDateString() }}
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <td colspan="5" class="py-1 text-right">
                        {{ t('documents.totals') }}
                    </td>
                    <td class="py-1 text-right tabular-nums">{{ total }}</td>
                </tr>
            </tfoot>
        </table>
    </ReportSheet>
</template>
