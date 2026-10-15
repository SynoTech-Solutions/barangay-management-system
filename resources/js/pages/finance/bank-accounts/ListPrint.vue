<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportSheet from '@/components/finance/ReportSheet.vue';
import { t } from '@/composables/useI18n';
import type { BankAccount, PrintHeader } from '@/types';

defineProps<{
    header: PrintHeader;
    accounts: BankAccount[];
    limit: number;
    printedAt: string;
}>();
</script>

<template>
    <Head :title="t('finance.bank_accounts.print_title')" />

    <ReportSheet
        :header="header"
        :title="t('finance.bank_accounts.print_title')"
        :printed-at="printedAt"
        :truncated-at="accounts.length >= limit ? limit : null"
    >
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-1">
                        {{ t('finance.bank_accounts.account_number') }}
                    </th>
                    <th class="py-1">
                        {{ t('finance.bank_accounts.account_name') }}
                    </th>
                    <th class="py-1">
                        {{ t('finance.bank_accounts.depository_bank') }}
                    </th>
                    <th class="py-1">
                        {{ t('finance.bank_accounts.branch_address') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="account in accounts"
                    :key="account.id"
                    class="border-b"
                >
                    <td class="py-1 font-mono">{{ account.account_number }}</td>
                    <td class="py-1">{{ account.account_name }}</td>
                    <td class="py-1">{{ account.depository_bank }}</td>
                    <td class="py-1">{{ account.branch_address }}</td>
                </tr>
            </tbody>
        </table>
    </ReportSheet>
</template>
