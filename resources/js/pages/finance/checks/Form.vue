<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { index, update } from '@/routes/finance/checks';
import { store as storeDirect } from '@/routes/finance/checks/direct';
import { store as storeForVoucher } from '@/routes/finance/vouchers/checks';
import type { Check, SelectOption, Voucher } from '@/types';

const props = defineProps<{
    check: Check | null;
    voucher: Voucher | null;
    bankAccounts: SelectOption[];
    vendors: SelectOption[];
    minimum: string;
    today: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.checks'), href: index() }] },
});

const check = props.check;
const direct = props.voucher === null;
const form = useForm({
    bank_account_id: check?.bank_account_id ?? null,
    check_number: check?.check_number ?? '',
    check_date: check?.check_date ?? props.today,
    remarks: check?.remarks ?? '',
    vendor_id: check?.vendor_id ?? null,
    amount: check?.amount ?? '',
});

function submit(): void {
    if (check !== null) {
        form.put(update.url(check.id));
    } else if (props.voucher !== null) {
        form.post(storeForVoucher.url(props.voucher.id));
    } else {
        form.post(storeDirect.url());
    }
}
</script>

<template>
    <Head
        :title="
            check ? t('finance.checks.edit_title') : t('finance.checks.issue')
        "
    />

    <form class="flex max-w-2xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                check
                    ? t('finance.checks.edit_title')
                    : direct
                      ? t('finance.checks.direct')
                      : t('finance.checks.issue')
            "
            :description="
                voucher
                    ? t('finance.checks.for_voucher', {
                          number: voucher.voucher_number,
                          payee: voucher.payee_name,
                      })
                    : t('finance.checks.direct_help')
            "
        />

        <div v-if="direct" class="grid gap-2">
            <Label for="vendor_id">{{ t('finance.checks.payee') }}</Label>
            <OptionSelect
                id="vendor_id"
                v-model="form.vendor_id"
                :options="vendors"
                required
            />
            <InputError :message="form.errors.vendor_id" />
        </div>

        <div class="grid gap-2">
            <Label for="amount">{{ t('finance.checks.amount') }}</Label>
            <Input
                v-if="direct"
                id="amount"
                v-model="form.amount"
                type="number"
                step="0.01"
                :min="minimum"
                required
            />
            <p v-else class="font-mono tabular-nums">
                {{ voucher?.total }}
            </p>
            <InputError :message="form.errors.amount" />
        </div>

        <div class="grid gap-2">
            <Label for="bank_account_id">{{
                t('finance.checks.bank_account')
            }}</Label>
            <OptionSelect
                id="bank_account_id"
                v-model="form.bank_account_id"
                :options="bankAccounts"
                required
            />
            <InputError :message="form.errors.bank_account_id" />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="check_number">{{
                    t('finance.checks.number')
                }}</Label>
                <Input id="check_number" v-model="form.check_number" required />
                <InputError :message="form.errors.check_number" />
            </div>
            <div class="grid gap-2">
                <Label for="check_date">{{ t('finance.checks.date') }}</Label>
                <Input
                    id="check_date"
                    v-model="form.check_date"
                    type="date"
                    required
                />
                <InputError :message="form.errors.check_date" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="remarks">{{ t('finance.checks.remarks') }}</Label>
            <Input id="remarks" v-model="form.remarks" />
            <InputError :message="form.errors.remarks" />
        </div>

        <div>
            <Button type="submit" :disabled="form.processing">{{
                t('common.save')
            }}</Button>
        </div>
    </form>
</template>
