<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import StatusPill from '@/components/finance/StatusPill.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import { index as checksIndex } from '@/routes/finance/checks';
import { index as liquidationsIndex } from '@/routes/finance/liquidations';
import { create, index, show } from '@/routes/finance/vouchers';
import type {
    Paginated,
    PaymentType,
    SelectOption,
    Voucher,
    VoucherFilters,
    VoucherStatus,
} from '@/types';

const SEARCH_DELAY_MS = 300;

const props = defineProps<{
    vouchers: Paginated<Voucher>;
    filters: VoucherFilters;
    statuses: SelectOption<VoucherStatus>[];
    paymentTypes: SelectOption<PaymentType>[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.vouchers'), href: index() }] },
});

const { can, feature } = useAccess();
const state = reactive({
    search: props.filters.search,
    status: props.filters.status,
    payment_type: props.filters.payment_type,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const query = computed(
    () =>
        Object.fromEntries(
            Object.entries(state).filter(
                ([, value]) => value !== null && value !== '',
            ),
        ) as Record<string, string>,
);

function apply(): void {
    router.get(index.url(), query.value, {
        preserveState: true,
        replace: true,
    });
}

const applySearch = useDebounceFn(apply, SEARCH_DELAY_MS);
</script>

<template>
    <Head :title="t('finance.vouchers.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('finance.vouchers.title')"
                :description="t('finance.vouchers.description')"
            />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="checksIndex()">{{ t('nav.checks') }}</Link>
                </Button>
                <Button
                    v-if="feature(FeatureFlag.CashAdvanceLiquidation)"
                    variant="outline"
                    as-child
                >
                    <Link :href="liquidationsIndex()">{{
                        t('nav.liquidations')
                    }}</Link>
                </Button>
                <Button v-if="can(Permission.VouchersCreate)" as-child>
                    <Link :href="create()">{{
                        t('finance.vouchers.new')
                    }}</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-5">
            <Input
                v-model="state.search"
                :placeholder="t('finance.vouchers.search')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('documents.all_statuses')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.payment_type"
                :options="paymentTypes"
                :placeholder="t('finance.vouchers.all_payment_types')"
                @update:model-value="apply"
            />
            <Input
                v-model="state.from"
                type="date"
                :aria-label="t('finance.vouchers.from')"
                @change="apply"
            />
            <Input
                v-model="state.to"
                type="date"
                :aria-label="t('finance.vouchers.to')"
                @change="apply"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('finance.vouchers.number') }}</th>
                        <th class="p-3">{{ t('finance.vouchers.date') }}</th>
                        <th class="p-3">{{ t('finance.vouchers.payee') }}</th>
                        <th class="p-3">
                            {{ t('finance.vouchers.payment_type') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.vouchers.particulars') }}
                        </th>
                        <th class="p-3">{{ t('finance.vouchers.check') }}</th>
                        <th class="p-3 text-right">
                            {{ t('finance.vouchers.total') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="voucher in vouchers.data"
                        :key="voucher.id"
                        class="border-t"
                        :class="{
                            'text-muted-foreground':
                                voucher.status === 'cancelled',
                        }"
                    >
                        <td class="p-3 font-mono">
                            <Link
                                :href="show(voucher.id)"
                                class="hover:underline"
                                >{{ voucher.voucher_number }}</Link
                            >
                            <StatusPill
                                :label="voucher.status_label"
                                :muted="voucher.status === 'cancelled'"
                            />
                        </td>
                        <td class="p-3">
                            {{ longDate(voucher.voucher_date) }}
                        </td>
                        <td class="p-3">{{ voucher.payee_name }}</td>
                        <td class="p-3">{{ voucher.payment_type_label }}</td>
                        <td class="p-3">{{ voucher.particulars }}</td>
                        <td class="p-3 font-mono">
                            {{ voucher.check?.check_number }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ voucher.total }}
                        </td>
                    </tr>
                    <tr v-if="vouchers.data.length === 0">
                        <td colspan="7" class="p-3 text-muted-foreground">
                            {{ t('finance.vouchers.empty') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="vouchers.meta" />
    </div>
</template>
