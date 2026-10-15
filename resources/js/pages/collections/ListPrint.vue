<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportSheet from '@/components/finance/ReportSheet.vue';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import type {
    OtherCollection,
    OtherCollectionFilters,
    PrintHeader,
} from '@/types';

defineProps<{
    header: PrintHeader;
    collections: OtherCollection[];
    count: number;
    total: string;
    limit: number;
    filters: OtherCollectionFilters;
    printedAt: string;
}>();
</script>

<template>
    <Head :title="t('collections.print_title')" />

    <ReportSheet
        :header="header"
        :title="t('collections.print_title')"
        :subtitle="
            filters.from || filters.to
                ? t('finance.date_range', {
                      from: longDate(filters.from),
                      to: longDate(filters.to),
                  })
                : t('finance.all_dates')
        "
        :printed-at="printedAt"
        :truncated-at="count > collections.length ? limit : null"
    >
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-1">{{ t('collections.number') }}</th>
                    <th class="py-1">{{ t('collections.paid_on') }}</th>
                    <th class="py-1">{{ t('collections.payer') }}</th>
                    <th class="py-1">{{ t('collections.type') }}</th>
                    <th class="py-1">{{ t('collections.or_number') }}</th>
                    <th class="py-1 text-right">
                        {{ t('collections.amount') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="collection in collections"
                    :key="collection.id"
                    class="border-b"
                    :class="{ 'line-through': collection.is_cancelled }"
                >
                    <td class="py-1 font-mono">
                        {{ collection.collection_number }}
                    </td>
                    <td class="py-1">{{ collection.paid_on }}</td>
                    <td class="py-1">{{ collection.payer_name }}</td>
                    <td class="py-1">{{ collection.collection_type }}</td>
                    <td class="py-1 font-mono">{{ collection.or_number }}</td>
                    <td class="py-1 text-right tabular-nums">
                        {{ collection.amount }}
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
