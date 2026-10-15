<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PrintSheet from '@/components/documents/PrintSheet.vue';
import SignatureBlock from '@/components/documents/SignatureBlock.vue';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import type { Liquidation, PrintHeader } from '@/types';

const BLANK_OR_NUMBER = '________';
const BLANK_OR_DATE = '___________________';

defineProps<{
    header: PrintHeader;
    liquidation: Liquidation;
    voucher: {
        voucher_number: string;
        voucher_date: string;
        payee_name: string;
        responsibility_center: string | null;
    } | null;
    descriptions: Record<
        number,
        { description: string; original_in_words: string }
    >;
    signatories: { treasurer: string | null; punong_barangay: string | null };
}>();
</script>

<template>
    <Head
        :title="`${t('finance.liquidations.print_title')} ${liquidation.liquidation_number}`"
    />

    <PrintSheet
        :header="header"
        :print-count="liquidation.print_count"
        :is-reprint="liquidation.print_count > 1"
    >
        <h1 class="text-center text-[15pt] font-bold tracking-wide uppercase">
            {{ t('finance.liquidations.print_title') }}
        </h1>
        <div class="flex justify-between text-[10pt]">
            <p>
                {{ t('finance.liquidations.number') }}
                <span class="font-mono font-semibold">{{
                    liquidation.liquidation_number
                }}</span>
            </p>
            <p>
                {{ t('finance.liquidations.date') }}
                {{ longDate(liquidation.liquidated_on) }}
            </p>
        </div>
        <table v-if="voucher" class="w-full border-collapse text-[10pt]">
            <tbody>
                <tr>
                    <th class="w-1/4 border p-1 text-left">
                        {{ t('finance.vouchers.number') }}
                    </th>
                    <td class="border p-1 font-mono">
                        {{ voucher.voucher_number }}
                    </td>
                    <th class="border p-1 text-left">
                        {{ t('finance.vouchers.date') }}
                    </th>
                    <td class="border p-1">
                        {{ longDate(voucher.voucher_date) }}
                    </td>
                </tr>
                <tr>
                    <th class="border p-1 text-left">
                        {{ t('finance.vouchers.payee') }}
                    </th>
                    <td class="border p-1">{{ voucher.payee_name }}</td>
                    <th class="border p-1 text-left">
                        {{ t('finance.vouchers.responsibility_center') }}
                    </th>
                    <td class="border p-1">
                        {{ voucher.responsibility_center }}
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="w-full border-collapse text-[10pt]">
            <thead>
                <tr>
                    <th class="border p-1 text-left">
                        {{ t('finance.vouchers.particulars') }}
                    </th>
                    <th class="w-32 border p-1 text-right">
                        {{ t('finance.liquidations.actual') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="item in liquidation.items"
                    :key="item.voucher_item_id"
                >
                    <td class="border p-1">
                        {{
                            t('finance.liquidations.print_line', {
                                payee: voucher?.payee_name ?? '',
                                number: voucher?.voucher_number ?? '',
                                date: longDate(voucher?.voucher_date ?? null),
                                description:
                                    descriptions[item.voucher_item_id]
                                        ?.description ?? '',
                                words:
                                    descriptions[item.voucher_item_id]
                                        ?.original_in_words ?? '',
                                amount: item.original_amount,
                            })
                        }}
                    </td>
                    <td class="border p-1 text-right tabular-nums">
                        {{ item.actual_amount }}
                    </td>
                </tr>
            </tbody>
        </table>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-[10pt]">
            <dt>{{ t('finance.liquidations.advanced') }}</dt>
            <dd class="text-right tabular-nums">{{ liquidation.advanced }}</dd>
            <dt>{{ t('finance.liquidations.actual') }}</dt>
            <dd class="text-right tabular-nums">{{ liquidation.actual }}</dd>
            <dt>{{ t('finance.liquidations.refund') }}</dt>
            <dd class="text-right tabular-nums">{{ liquidation.refund }}</dd>
            <dt>{{ t('finance.liquidations.reimbursement') }}</dt>
            <dd class="text-right tabular-nums">
                {{ liquidation.reimbursement }}
            </dd>
            <dt>{{ t('finance.liquidations.or_number') }}</dt>
            <dd class="text-right">
                {{ liquidation.or_number ?? BLANK_OR_NUMBER }}
            </dd>
            <dt>{{ t('finance.liquidations.or_date') }}</dt>
            <dd class="text-right">
                {{
                    liquidation.or_date
                        ? longDate(liquidation.or_date)
                        : BLANK_OR_DATE
                }}
            </dd>
        </dl>
        <div class="mt-8 grid grid-cols-2 gap-10 text-[10pt]">
            <SignatureBlock
                :caption="t('finance.liquidations.submitted_by')"
                :name="signatories.treasurer"
                :title="t('finance.vouchers.treasurer_title')"
            />
            <SignatureBlock
                :caption="t('finance.liquidations.noted_by')"
                :name="signatories.punong_barangay"
                :title="t('documents.default_captain_title')"
            />
        </div>
    </PrintSheet>
</template>
