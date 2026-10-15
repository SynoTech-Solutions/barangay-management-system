<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import { fromCentavos, toCentavos } from '@/lib/money';
import { index, store, update } from '@/routes/finance/liquidations';
import type { Liquidation, Voucher } from '@/types';

const props = defineProps<{
    liquidation: Liquidation | null;
    voucher: Voucher;
    today: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.liquidations'), href: index() }],
    },
});

const liquidation = props.liquidation;
const actuals = new Map(
    (liquidation?.items ?? []).map((item) => [
        item.voucher_item_id,
        item.actual_amount,
    ]),
);

const form = useForm({
    liquidated_on: liquidation?.liquidated_on ?? props.today,
    or_number: liquidation?.or_number ?? '',
    or_date: liquidation?.or_date ?? '',
    items: props.voucher.items.map((item) => ({
        voucher_item_id: item.id,
        actual_amount: actuals.get(item.id) ?? item.amount,
    })),
});
const errors = form.errors as Record<string, string>;

const advanced = computed(() =>
    props.voucher.items.reduce((sum, item) => sum + toCentavos(item.amount), 0),
);
const actual = computed(() =>
    form.items.reduce((sum, item) => sum + toCentavos(item.actual_amount), 0),
);
const refund = computed(() => Math.max(0, advanced.value - actual.value));
const reimbursement = computed(() =>
    Math.max(0, actual.value - advanced.value),
);

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        or_number: refund.value > 0 ? data.or_number : null,
        or_date: refund.value > 0 ? data.or_date : null,
    }));

    if (liquidation === null) {
        request.post(store.url(props.voucher.id));
    } else {
        request.put(update.url(liquidation.id));
    }
}
</script>

<template>
    <Head :title="t('finance.liquidations.form_title')" />

    <form class="flex max-w-4xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="t('finance.liquidations.form_title')"
            :description="
                t('finance.liquidations.form_description', {
                    number: voucher.voucher_number,
                    payee: voucher.payee_name,
                    date: longDate(voucher.voucher_date),
                })
            "
        />
        <p v-if="liquidation" class="font-mono text-sm">
            {{ liquidation.liquidation_number }}
        </p>

        <div class="grid gap-2 md:w-1/3">
            <Label for="liquidated_on">{{
                t('finance.liquidations.date')
            }}</Label>
            <Input
                id="liquidated_on"
                v-model="form.liquidated_on"
                type="date"
                :max="today"
                required
            />
            <InputError :message="errors.liquidated_on" />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-2">
                            {{ t('finance.vouchers.description') }}
                        </th>
                        <th class="p-2 text-right">
                            {{ t('finance.liquidations.advanced') }}
                        </th>
                        <th class="w-48 p-2 text-right">
                            {{ t('finance.liquidations.actual') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, position) in voucher.items"
                        :key="item.id"
                        class="border-t"
                    >
                        <td class="p-2">{{ item.description }}</td>
                        <td class="p-2 text-right tabular-nums">
                            {{ item.amount }}
                        </td>
                        <td class="p-2">
                            <Input
                                v-model="form.items[position].actual_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                class="text-right"
                                required
                            />
                            <InputError
                                :message="
                                    errors[`items.${position}.actual_amount`]
                                "
                            />
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t bg-muted/30 font-medium">
                    <tr>
                        <td class="p-2 text-right">
                            {{ t('finance.vouchers.total') }}
                        </td>
                        <td class="p-2 text-right tabular-nums">
                            {{ fromCentavos(advanced) }}
                        </td>
                        <td class="p-2 text-right tabular-nums">
                            {{ fromCentavos(actual) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <dl class="grid gap-3 rounded-lg border p-4 text-sm md:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.liquidations.refund') }}
                </dt>
                <dd class="tabular-nums">{{ fromCentavos(refund) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.liquidations.reimbursement') }}
                </dt>
                <dd class="tabular-nums">{{ fromCentavos(reimbursement) }}</dd>
            </div>
        </dl>

        <div v-if="refund > 0" class="grid gap-4 md:grid-cols-2">
            <p class="text-sm text-muted-foreground md:col-span-2">
                {{ t('finance.liquidations.refund_help') }}
            </p>
            <div class="grid gap-2">
                <Label for="or_number">{{
                    t('finance.liquidations.or_number')
                }}</Label>
                <Input id="or_number" v-model="form.or_number" required />
                <InputError :message="errors.or_number" />
            </div>
            <div class="grid gap-2">
                <Label for="or_date">{{
                    t('finance.liquidations.or_date')
                }}</Label>
                <Input
                    id="or_date"
                    v-model="form.or_date"
                    type="date"
                    :max="today"
                    required
                />
                <InputError :message="errors.or_date" />
            </div>
        </div>

        <div>
            <Button type="submit" :disabled="form.processing">{{
                t('common.save')
            }}</Button>
        </div>
    </form>
</template>
