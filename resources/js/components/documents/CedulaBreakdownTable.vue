<script setup lang="ts">
import { computed } from 'vue';
import { t } from '@/composables/useI18n';
import type { CommunityTaxBreakdown } from '@/types';

const BASIS_POINTS_PER_PERCENT = 100;

const props = defineProps<{ breakdown: CommunityTaxBreakdown }>();

const rows = computed<Array<[string, string, string]>>(() => [
    [
        props.breakdown.exempt
            ? t('cedulas.breakdown.basic_exempt')
            : t('cedulas.breakdown.basic'),
        '',
        props.breakdown.basic,
    ],
    [
        t('cedulas.breakdown.business'),
        props.breakdown.inputs.business_gross_receipts,
        props.breakdown.business,
    ],
    [
        t('cedulas.breakdown.salary'),
        props.breakdown.inputs.salaries,
        props.breakdown.salary,
    ],
    [
        t('cedulas.breakdown.real_property'),
        props.breakdown.inputs.real_property_income,
        props.breakdown.real_property,
    ],
]);
</script>

<template>
    <table class="w-full text-sm">
        <thead class="text-left text-muted-foreground">
            <tr>
                <th class="py-1">{{ t('cedulas.breakdown.item') }}</th>
                <th class="py-1 text-right">
                    {{ t('cedulas.breakdown.taxable') }}
                </th>
                <th class="py-1 text-right">
                    {{ t('cedulas.breakdown.tax') }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="[label, taxable, tax] in rows" :key="label">
                <td class="py-1">{{ label }}</td>
                <td class="py-1 text-right tabular-nums">{{ taxable }}</td>
                <td class="py-1 text-right tabular-nums">{{ tax }}</td>
            </tr>
            <tr class="border-t">
                <td class="py-1" colspan="2">
                    {{ t('cedulas.breakdown.additional') }}
                    <span
                        v-if="breakdown.additional_capped"
                        class="text-muted-foreground"
                        >({{ t('cedulas.breakdown.capped') }})</span
                    >
                </td>
                <td class="py-1 text-right tabular-nums">
                    {{ breakdown.additional }}
                </td>
            </tr>
            <tr v-if="breakdown.interest_applied">
                <td class="py-1" colspan="2">
                    {{
                        t('cedulas.breakdown.interest', {
                            months: breakdown.interest_months,
                            percent:
                                breakdown.interest_basis_points /
                                BASIS_POINTS_PER_PERCENT,
                        })
                    }}
                </td>
                <td class="py-1 text-right tabular-nums">
                    {{ breakdown.interest }}
                </td>
            </tr>
            <tr class="border-t font-semibold">
                <td class="py-1" colspan="2">
                    {{ t('cedulas.breakdown.total') }}
                </td>
                <td class="py-1 text-right tabular-nums">
                    {{ breakdown.total }}
                </td>
            </tr>
        </tbody>
    </table>
</template>
