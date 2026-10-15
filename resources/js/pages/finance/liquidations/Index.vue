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
import {
    clear,
    create,
    edit,
    index,
    print,
} from '@/routes/finance/liquidations';
import { show as showVoucher } from '@/routes/finance/vouchers';
import type {
    Liquidation,
    LiquidationFilters,
    LiquidationStatus,
    Paginated,
    SelectOption,
    Voucher,
} from '@/types';

const SEARCH_DELAY_MS = 300;
const UNLIQUIDATED_PAGE = 'unliquidated_page';

const props = defineProps<{
    unliquidated: Paginated<Voucher>;
    liquidations: Paginated<Liquidation>;
    filters: LiquidationFilters;
    statuses: SelectOption<LiquidationStatus>[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.liquidations'), href: index() }],
    },
});

const { can, feature } = useAccess();
const state = reactive({
    search: props.filters.search,
    status: props.filters.status,
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

function clearLiquidation(liquidation: Liquidation): void {
    if (
        window.confirm(
            t('finance.liquidations.clear_confirm', {
                number: liquidation.liquidation_number,
            }),
        )
    ) {
        router.post(clear.url(liquidation.id), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('finance.liquidations.title')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="t('finance.liquidations.title')"
            :description="t('finance.liquidations.description')"
        />

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-3">
            <Input
                v-model="state.search"
                :placeholder="t('finance.liquidations.search')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('documents.all_statuses')"
                @update:model-value="apply"
            />
        </div>

        <section class="flex flex-col gap-2">
            <h2 class="font-semibold">
                {{ t('finance.liquidations.unliquidated') }}
            </h2>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="p-3">
                                {{ t('finance.vouchers.number') }}
                            </th>
                            <th class="p-3">
                                {{ t('finance.vouchers.date') }}
                            </th>
                            <th class="p-3">
                                {{ t('finance.vouchers.payee') }}
                            </th>
                            <th class="p-3">
                                {{ t('finance.vouchers.particulars') }}
                            </th>
                            <th class="p-3 text-right">
                                {{ t('finance.vouchers.total') }}
                            </th>
                            <th class="p-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="voucher in unliquidated.data"
                            :key="voucher.id"
                            class="border-t"
                        >
                            <td class="p-3 font-mono">
                                <Link
                                    :href="showVoucher(voucher.id)"
                                    class="hover:underline"
                                    >{{ voucher.voucher_number }}</Link
                                >
                            </td>
                            <td class="p-3">
                                {{ longDate(voucher.voucher_date) }}
                            </td>
                            <td class="p-3">{{ voucher.payee_name }}</td>
                            <td class="p-3">{{ voucher.particulars }}</td>
                            <td class="p-3 text-right tabular-nums">
                                {{ voucher.total }}
                            </td>
                            <td class="p-3 text-right">
                                <Button
                                    v-if="can(Permission.LiquidationsManage)"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="create(voucher.id)">{{
                                        t('finance.liquidations.liquidate')
                                    }}</Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="unliquidated.data.length === 0">
                            <td colspan="6" class="p-3 text-muted-foreground">
                                {{
                                    t('finance.liquidations.none_unliquidated')
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination
                :meta="unliquidated.meta"
                :page-name="UNLIQUIDATED_PAGE"
            />
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="font-semibold">
                {{ t('finance.liquidations.reports') }}
            </h2>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="p-3">
                                {{ t('finance.liquidations.number') }}
                            </th>
                            <th class="p-3">
                                {{ t('finance.vouchers.number') }}
                            </th>
                            <th class="p-3">
                                {{ t('finance.vouchers.payee') }}
                            </th>
                            <th class="p-3">
                                {{ t('finance.liquidations.date') }}
                            </th>
                            <th class="p-3 text-right">
                                {{ t('finance.liquidations.advanced') }}
                            </th>
                            <th class="p-3 text-right">
                                {{ t('finance.liquidations.actual') }}
                            </th>
                            <th class="p-3 text-right">
                                {{ t('finance.liquidations.refund') }}
                            </th>
                            <th class="p-3 text-right">
                                {{ t('finance.liquidations.reimbursement') }}
                            </th>
                            <th class="p-3">
                                {{ t('finance.liquidations.or_number') }}
                            </th>
                            <th class="p-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="liquidation in liquidations.data"
                            :key="liquidation.id"
                            class="border-t"
                        >
                            <td class="p-3 font-mono">
                                {{ liquidation.liquidation_number }}
                                <StatusPill
                                    :label="liquidation.status_label"
                                    :muted="liquidation.status === 'cleared'"
                                />
                            </td>
                            <td class="p-3 font-mono">
                                <Link
                                    :href="showVoucher(liquidation.voucher_id)"
                                    class="hover:underline"
                                    >{{ liquidation.voucher_number }}</Link
                                >
                            </td>
                            <td class="p-3">{{ liquidation.payee_name }}</td>
                            <td class="p-3">
                                {{ longDate(liquidation.liquidated_on) }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ liquidation.advanced }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ liquidation.actual }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ liquidation.refund }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ liquidation.reimbursement }}
                            </td>
                            <td class="p-3 font-mono">
                                {{ liquidation.or_number }}
                            </td>
                            <td class="p-3">
                                <div class="flex justify-end gap-2">
                                    <template
                                        v-if="
                                            liquidation.status ===
                                            'for-clearing'
                                        "
                                    >
                                        <Button
                                            v-if="
                                                feature(
                                                    FeatureFlag.LiquidationClearing,
                                                ) &&
                                                can(
                                                    Permission.LiquidationsClear,
                                                )
                                            "
                                            size="sm"
                                            @click="
                                                clearLiquidation(liquidation)
                                            "
                                            >{{
                                                t('finance.liquidations.clear')
                                            }}</Button
                                        >
                                        <Button
                                            v-if="
                                                can(
                                                    Permission.LiquidationsManage,
                                                )
                                            "
                                            size="sm"
                                            variant="outline"
                                            as-child
                                        >
                                            <Link
                                                :href="edit(liquidation.id)"
                                                >{{ t('common.edit') }}</Link
                                            >
                                        </Button>
                                    </template>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        as-child
                                    >
                                        <a
                                            :href="print.url(liquidation.id)"
                                            target="_blank"
                                            >{{ t('documents.print') }}</a
                                        >
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="liquidations.data.length === 0">
                            <td colspan="10" class="p-3 text-muted-foreground">
                                {{ t('finance.liquidations.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="liquidations.meta" />
        </section>
    </div>
</template>
