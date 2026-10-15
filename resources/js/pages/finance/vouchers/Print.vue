<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PrintSheet from '@/components/documents/PrintSheet.vue';
import SignatureBlock from '@/components/documents/SignatureBlock.vue';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import type { PaymentType, PrintHeader, Voucher } from '@/types';

const PAYMENT_TYPES: PaymentType[] = [
    'cash-advance',
    'reimbursement',
    'others',
];

const props = defineProps<{
    header: PrintHeader;
    voucher: Voucher;
    check: {
        check_number: string;
        check_date: string;
        bank: string | null;
    } | null;
    amountInWords: string;
    signatories: {
        treasurer: string | null;
        bookkeeper: string | null;
        punong_barangay: string | null;
        head_of_appropriations: string | null;
    };
    minRows: number;
}>();

const blankRows = computed(() =>
    Math.max(0, props.minRows - props.voucher.items.length),
);
</script>

<template>
    <Head
        :title="`${t('finance.vouchers.print_title')} ${voucher.voucher_number}`"
    />

    <PrintSheet
        :header="header"
        :print-count="voucher.print_count"
        :is-reprint="voucher.print_count > 1"
    >
        <h1 class="text-center text-[16pt] font-bold tracking-wide uppercase">
            {{ t('finance.vouchers.print_title') }}
        </h1>
        <div class="flex justify-between text-[10pt]">
            <p>
                {{ t('finance.vouchers.number') }}
                <span class="font-mono font-semibold">{{
                    voucher.voucher_number
                }}</span>
            </p>
            <p>
                {{ t('finance.vouchers.date') }}
                {{ longDate(voucher.voucher_date) }}
            </p>
        </div>
        <div class="flex gap-6 text-[10pt]">
            <span v-for="type in PAYMENT_TYPES" :key="type">
                [{{ voucher.payment_type === type ? 'X' : ' ' }}]
                {{ t(`finance.payment_types.${type}`) }}
            </span>
        </div>
        <table class="w-full border-collapse text-[10pt]">
            <tbody>
                <tr>
                    <th class="w-1/4 border p-1 text-left">
                        {{ t('finance.vouchers.payee') }}
                    </th>
                    <td class="border p-1">{{ voucher.payee_name }}</td>
                    <th class="border p-1 text-left">
                        {{ t('finance.vendors.tin') }}
                    </th>
                    <td class="border p-1">{{ voucher.payee_tin }}</td>
                </tr>
                <tr>
                    <th class="border p-1 text-left">
                        {{ t('finance.vendors.address') }}
                    </th>
                    <td colspan="3" class="border p-1">
                        {{ voucher.payee_address }}
                    </td>
                </tr>
                <tr>
                    <th class="border p-1 text-left">
                        {{ t('finance.vouchers.responsibility_center') }}
                    </th>
                    <td class="border p-1">
                        {{ voucher.responsibility_center }}
                    </td>
                    <th class="border p-1 text-left">
                        {{ t('finance.vouchers.office_unit') }}
                    </th>
                    <td class="border p-1">{{ voucher.office_unit }}</td>
                </tr>
            </tbody>
        </table>
        <table class="w-full border-collapse text-[10pt]">
            <thead>
                <tr>
                    <th class="border p-1 text-left">
                        {{ t('finance.vouchers.particulars') }}
                    </th>
                    <th class="border p-1 text-left">
                        {{ t('finance.vouchers.fund_source') }}
                    </th>
                    <th class="w-32 border p-1 text-right">
                        {{ t('finance.vouchers.amount') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in voucher.items" :key="item.id">
                    <td class="border p-1">{{ item.description }}</td>
                    <td class="border p-1">{{ item.account }}</td>
                    <td class="border p-1 text-right tabular-nums">
                        {{ item.amount }}
                    </td>
                </tr>
                <tr v-for="row in blankRows" :key="`blank-${row}`">
                    <td class="h-[6mm] border" />
                    <td class="border" />
                    <td class="border" />
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="border p-1 text-right font-semibold">
                        {{ t('finance.vouchers.total') }}
                    </td>
                    <td
                        class="border p-1 text-right font-semibold tabular-nums"
                    >
                        {{ voucher.total }}
                    </td>
                </tr>
            </tfoot>
        </table>
        <p class="text-[10pt] italic">{{ amountInWords }}</p>
        <p v-if="check" class="text-[10pt]">
            {{
                t('finance.vouchers.print_check_line', {
                    number: check.check_number,
                    bank: check.bank ?? '',
                    date: longDate(check.check_date),
                })
            }}
        </p>
        <div class="mt-6 grid grid-cols-2 gap-x-10 gap-y-8 text-[10pt]">
            <SignatureBlock
                :caption="t('finance.vouchers.sign_treasurer')"
                :name="signatories.treasurer"
                :title="t('finance.vouchers.treasurer_title')"
            />
            <SignatureBlock
                :caption="t('finance.vouchers.sign_bookkeeper')"
                :name="signatories.bookkeeper"
                :title="t('finance.vouchers.bookkeeper_title')"
            />
            <SignatureBlock
                :caption="t('finance.vouchers.sign_head')"
                :name="signatories.head_of_appropriations"
                :title="t('finance.vouchers.head_title')"
            />
            <SignatureBlock
                :caption="t('finance.vouchers.sign_captain')"
                :name="signatories.punong_barangay"
                :title="t('documents.default_captain_title')"
            />
        </div>
    </PrintSheet>
</template>
