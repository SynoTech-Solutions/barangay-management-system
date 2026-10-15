<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import StatusPill from '@/components/finance/StatusPill.vue';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import { create as liquidate } from '@/routes/finance/liquidations';
import {
    approve,
    cancel,
    edit,
    index,
    print,
    verify,
} from '@/routes/finance/vouchers';
import { create as issueCheck } from '@/routes/finance/vouchers/checks';
import type { Voucher } from '@/types';

const props = defineProps<{
    voucher: Voucher;
    requiresCheck: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.vouchers'), href: index() }] },
});

const { can, feature } = useAccess();

const editable = computed(() =>
    ['prepared', 'verified'].includes(props.voucher.status),
);
const printable = computed(
    () =>
        props.voucher.status !== 'cancelled' &&
        (!feature(FeatureFlag.DvPrintRequiresCheck) ||
            !props.requiresCheck ||
            props.voucher.check !== null),
);
const cancellable = computed(() =>
    ['prepared', 'verified', 'approved'].includes(props.voucher.status),
);

const steps = computed(() => [
    {
        label: t('finance.vouchers.prepared'),
        by: props.voucher.prepared_by,
        at: props.voucher.prepared_at,
    },
    {
        label: t('finance.vouchers.verified'),
        by: props.voucher.verified_by,
        at: props.voucher.verified_at,
    },
    {
        label: t('finance.vouchers.approved'),
        by: props.voucher.approved_by,
        at: props.voucher.approved_at,
    },
    {
        label: t('finance.vouchers.released'),
        by: null,
        at: props.voucher.released_at,
    },
]);

function post(url: string, message: string): void {
    if (window.confirm(message)) {
        router.post(url, {}, { preserveScroll: true });
    }
}

function cancelVoucher(): void {
    const reason = window.prompt(t('finance.vouchers.cancel_prompt'));

    if (reason !== null && reason.trim() !== '') {
        router.post(
            cancel.url(props.voucher.id),
            { reason },
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head :title="voucher.voucher_number" />

    <div class="flex max-w-5xl flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-2">
                <Heading
                    :title="voucher.voucher_number"
                    :description="voucher.particulars"
                />
                <StatusPill
                    :label="voucher.status_label"
                    :muted="voucher.status === 'cancelled'"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="editable && can(Permission.VouchersCreate)"
                    variant="outline"
                    as-child
                >
                    <Link :href="edit(voucher.id)">{{ t('common.edit') }}</Link>
                </Button>
                <Button
                    v-if="
                        voucher.status === 'prepared' &&
                        can(Permission.VouchersVerify)
                    "
                    @click="
                        post(
                            verify.url(voucher.id),
                            t('finance.vouchers.verify_confirm'),
                        )
                    "
                    >{{ t('finance.vouchers.verify') }}</Button
                >
                <Button
                    v-if="
                        voucher.status === 'verified' &&
                        can(Permission.VouchersApprove)
                    "
                    @click="
                        post(
                            approve.url(voucher.id),
                            requiresCheck
                                ? t('finance.vouchers.approve_confirm')
                                : t('finance.vouchers.approve_release_confirm'),
                        )
                    "
                    >{{ t('finance.vouchers.approve') }}</Button
                >
                <Button
                    v-if="
                        voucher.status === 'approved' &&
                        requiresCheck &&
                        voucher.check === null &&
                        can(Permission.ChecksIssue)
                    "
                    as-child
                >
                    <Link :href="issueCheck(voucher.id)">{{
                        t('finance.checks.issue')
                    }}</Link>
                </Button>
                <Button
                    v-if="
                        voucher.status === 'released' &&
                        voucher.payment_type === 'cash-advance' &&
                        feature(FeatureFlag.CashAdvanceLiquidation) &&
                        can(Permission.LiquidationsManage)
                    "
                    as-child
                >
                    <Link :href="liquidate(voucher.id)">{{
                        t('finance.liquidations.liquidate')
                    }}</Link>
                </Button>
                <Button v-if="printable" variant="outline" as-child>
                    <a :href="print.url(voucher.id)" target="_blank">{{
                        t('documents.print')
                    }}</a>
                </Button>
                <Button
                    v-if="cancellable && can(Permission.VouchersCancel)"
                    variant="outline"
                    @click="cancelVoucher"
                    >{{ t('finance.vouchers.cancel') }}</Button
                >
            </div>
        </div>

        <p
            v-if="!printable && voucher.status !== 'cancelled'"
            class="text-sm text-muted-foreground"
        >
            {{ t('finance.vouchers.print_needs_check') }}
        </p>
        <p
            v-if="voucher.status === 'cancelled'"
            class="rounded-lg border p-3 text-sm"
        >
            {{
                t('finance.vouchers.cancelled_line', {
                    by: voucher.cancelled_by ?? '',
                    reason: voucher.cancel_reason ?? '',
                })
            }}
        </p>

        <dl class="grid gap-3 rounded-lg border p-4 text-sm md:grid-cols-3">
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.vouchers.date') }}
                </dt>
                <dd>{{ longDate(voucher.voucher_date) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.vouchers.payee') }}
                </dt>
                <dd>{{ voucher.payee_name }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.vouchers.payment_type') }}
                </dt>
                <dd>{{ voucher.payment_type_label }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.vouchers.responsibility_center') }}
                </dt>
                <dd>{{ voucher.responsibility_center }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.vouchers.office_unit') }}
                </dt>
                <dd>{{ voucher.office_unit }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.vouchers.check') }}
                </dt>
                <dd>
                    <template v-if="voucher.check">
                        <span class="font-mono">{{
                            voucher.check.check_number
                        }}</span>
                        <StatusPill :label="voucher.check.status_label" />
                    </template>
                    <span v-else-if="!requiresCheck">{{
                        t('finance.vouchers.no_check_needed')
                    }}</span>
                </dd>
            </div>
            <div v-if="voucher.liquidation_number">
                <dt class="text-muted-foreground">
                    {{ t('finance.liquidations.number') }}
                </dt>
                <dd class="font-mono">{{ voucher.liquidation_number }}</dd>
            </div>
        </dl>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">
                            {{ t('finance.vouchers.fund_source') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.vouchers.description') }}
                        </th>
                        <th class="p-3 text-right">
                            {{ t('finance.vouchers.amount') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in voucher.items"
                        :key="item.id"
                        class="border-t"
                    >
                        <td class="p-3">{{ item.account }}</td>
                        <td class="p-3">{{ item.description }}</td>
                        <td class="p-3 text-right tabular-nums">
                            {{ item.amount }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t bg-muted/30 font-medium">
                    <tr>
                        <td colspan="2" class="p-3 text-right">
                            {{ t('finance.vouchers.total') }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ voucher.total }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <ol class="grid gap-2 text-sm md:grid-cols-4">
            <li
                v-for="step in steps"
                :key="step.label"
                class="rounded-lg border p-3"
                :class="{ 'text-muted-foreground': !step.at }"
            >
                <p class="font-medium">{{ step.label }}</p>
                <p v-if="step.at">
                    {{ new Date(step.at).toLocaleString() }}
                </p>
                <p v-if="step.by">{{ step.by }}</p>
            </li>
        </ol>
    </div>
</template>
