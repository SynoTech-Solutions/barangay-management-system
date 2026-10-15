<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { fromCentavos, toCentavos } from '@/lib/money';
import { create, index, store, update } from '@/routes/finance/vouchers';
import type {
    BudgetOption,
    FundSource,
    PaymentType,
    ResidentOption,
    SelectOption,
    Voucher,
} from '@/types';

type PayeeMode = 'vendor' | 'resident' | 'text';

type ItemRow = {
    account_id: number | null;
    description: string;
    amount: string;
};

const props = defineProps<{
    voucher: Voucher | null;
    payee: ResidentOption | null;
    budgets: BudgetOption[];
    budgetId: number | null;
    fundSources: FundSource[];
    vendors: SelectOption[];
    paymentTypes: SelectOption<PaymentType>[];
    today: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.vouchers'), href: index() }] },
});

const voucher = props.voucher;
const resident = ref<ResidentOption | null>(props.payee);
const payeeMode = ref<PayeeMode>(
    voucher === null || voucher.vendor_id !== null
        ? 'vendor'
        : voucher.resident_id !== null
          ? 'resident'
          : 'text',
);

const budgetYear = (id: number | null): number | null =>
    props.budgets.find((budget) => budget.value === id)?.year ?? null;

function defaultDate(id: number | null): string {
    const year = budgetYear(id);

    return year === null ? props.today : `${year}${props.today.slice(4)}`;
}

const form = useForm({
    budget_id: props.budgetId,
    payment_type: (voucher?.payment_type ?? 'reimbursement') as PaymentType,
    voucher_date: voucher?.voucher_date ?? defaultDate(props.budgetId),
    vendor_id: voucher?.vendor_id ?? null,
    payee_name:
        voucher !== null &&
        voucher.vendor_id === null &&
        voucher.resident_id === null
            ? voucher.payee_name
            : '',
    particulars: voucher?.particulars ?? '',
    responsibility_center: voucher?.responsibility_center ?? '',
    office_unit: voucher?.office_unit ?? '',
    items: (voucher?.items.map((item) => ({
        account_id: item.account_id,
        description: item.description,
        amount: item.amount,
    })) ?? [{ account_id: null, description: '', amount: '' }]) as ItemRow[],
});
const errors = form.errors as Record<string, string>;

const sourceOptions = computed<SelectOption[]>(() =>
    props.fundSources.map((source) => ({
        value: source.value,
        label: t('finance.vouchers.source_option', {
            account: source.label,
            available: source.available,
        }),
    })),
);

const total = computed(() =>
    fromCentavos(
        form.items.reduce((sum, item) => sum + toCentavos(item.amount), 0),
    ),
);

const yearBounds = computed(() => {
    const year = budgetYear(form.budget_id);

    return year === null
        ? { min: undefined, max: undefined }
        : { min: `${year}-01-01`, max: `${year}-12-31` };
});

function changeBudget(id: number | null): void {
    form.voucher_date = defaultDate(id);
    router.get(create.url(), id === null ? {} : { budget_id: String(id) }, {
        only: ['fundSources', 'budgetId'],
        preserveState: true,
        replace: true,
    });
}

function addItem(): void {
    form.items.push({ account_id: null, description: '', amount: '' });
}

function removeItem(position: number): void {
    form.items.splice(position, 1);
}

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        vendor_id: payeeMode.value === 'vendor' ? data.vendor_id : null,
        resident_id:
            payeeMode.value === 'resident'
                ? (resident.value?.id ?? null)
                : null,
        payee_name: payeeMode.value === 'text' ? data.payee_name : null,
    }));

    if (voucher === null) {
        request.post(store.url());
    } else {
        request.put(update.url(voucher.id));
    }
}
</script>

<template>
    <Head
        :title="
            voucher
                ? t('finance.vouchers.edit_title')
                : t('finance.vouchers.new')
        "
    />

    <form class="flex max-w-5xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                voucher
                    ? t('finance.vouchers.edit_title')
                    : t('finance.vouchers.new')
            "
            :description="
                voucher?.voucher_number ?? t('finance.vouchers.number_on_save')
            "
        />

        <p
            v-if="budgets.length === 0"
            class="rounded-lg border p-3 text-sm text-muted-foreground"
        >
            {{ t('finance.vouchers.no_budget') }}
        </p>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label for="budget_id">{{
                    t('finance.vouchers.budget')
                }}</Label>
                <OptionSelect
                    id="budget_id"
                    v-model="form.budget_id"
                    :options="budgets"
                    :disabled="voucher !== null"
                    required
                    @update:model-value="changeBudget"
                />
                <InputError :message="errors.budget_id" />
            </div>
            <div class="grid gap-2">
                <Label for="payment_type">{{
                    t('finance.vouchers.payment_type')
                }}</Label>
                <OptionSelect
                    id="payment_type"
                    v-model="form.payment_type"
                    :options="paymentTypes"
                    required
                />
                <InputError :message="errors.payment_type" />
            </div>
            <div class="grid gap-2">
                <Label for="voucher_date">{{
                    t('finance.vouchers.date')
                }}</Label>
                <Input
                    id="voucher_date"
                    v-model="form.voucher_date"
                    type="date"
                    :min="yearBounds.min"
                    :max="yearBounds.max"
                    required
                />
                <InputError :message="errors.voucher_date" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label>{{ t('finance.vouchers.payee') }}</Label>
            <div class="flex gap-2">
                <Button
                    v-for="mode in [
                        'vendor',
                        'resident',
                        'text',
                    ] as PayeeMode[]"
                    :key="mode"
                    type="button"
                    size="sm"
                    :variant="payeeMode === mode ? 'default' : 'outline'"
                    @click="payeeMode = mode"
                    >{{ t(`finance.vouchers.payee_${mode}`) }}</Button
                >
            </div>
            <OptionSelect
                v-if="payeeMode === 'vendor'"
                id="vendor_id"
                v-model="form.vendor_id"
                :options="vendors"
            />
            <ResidentPicker
                v-else-if="payeeMode === 'resident'"
                id="resident"
                v-model="resident"
            />
            <Input v-else id="payee_name" v-model="form.payee_name" />
            <InputError
                :message="
                    errors.vendor_id ?? errors.resident_id ?? errors.payee_name
                "
            />
        </div>

        <div class="grid gap-2">
            <Label for="particulars">{{
                t('finance.vouchers.particulars')
            }}</Label>
            <Input id="particulars" v-model="form.particulars" required />
            <InputError :message="errors.particulars" />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="responsibility_center">{{
                    t('finance.vouchers.responsibility_center')
                }}</Label>
                <Input
                    id="responsibility_center"
                    v-model="form.responsibility_center"
                />
                <InputError :message="errors.responsibility_center" />
            </div>
            <div class="grid gap-2">
                <Label for="office_unit">{{
                    t('finance.vouchers.office_unit')
                }}</Label>
                <Input id="office_unit" v-model="form.office_unit" />
                <InputError :message="errors.office_unit" />
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <Label>{{ t('finance.vouchers.items') }}</Label>
            <p class="text-xs text-muted-foreground">
                {{ t('finance.vouchers.items_help') }}
            </p>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="p-2">
                                {{ t('finance.vouchers.fund_source') }}
                            </th>
                            <th class="p-2">
                                {{ t('finance.vouchers.description') }}
                            </th>
                            <th class="w-40 p-2 text-right">
                                {{ t('finance.vouchers.amount') }}
                            </th>
                            <th class="p-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(item, position) in form.items"
                            :key="position"
                            class="border-t align-top"
                        >
                            <td class="p-2">
                                <OptionSelect
                                    v-model="item.account_id"
                                    :options="sourceOptions"
                                    required
                                />
                                <InputError
                                    :message="
                                        errors[`items.${position}.account_id`]
                                    "
                                />
                            </td>
                            <td class="p-2">
                                <Input v-model="item.description" required />
                                <InputError
                                    :message="
                                        errors[`items.${position}.description`]
                                    "
                                />
                            </td>
                            <td class="p-2">
                                <Input
                                    v-model="item.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="text-right"
                                    required
                                />
                                <InputError
                                    :message="
                                        errors[`items.${position}.amount`]
                                    "
                                />
                            </td>
                            <td class="p-2 text-right">
                                <Button
                                    v-if="form.items.length > 1"
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="removeItem(position)"
                                    >{{
                                        t('finance.vouchers.remove_item')
                                    }}</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t bg-muted/30 font-medium">
                        <tr>
                            <td class="p-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="addItem"
                                    >{{
                                        t('finance.vouchers.add_item')
                                    }}</Button
                                >
                            </td>
                            <td class="p-2 text-right">
                                {{ t('finance.vouchers.total') }}
                            </td>
                            <td class="p-2 text-right tabular-nums">
                                {{ total }}
                            </td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>
            <InputError :message="errors.items" />
        </div>

        <div>
            <Button type="submit" :disabled="form.processing">{{
                t('common.save')
            }}</Button>
        </div>
    </form>
</template>
