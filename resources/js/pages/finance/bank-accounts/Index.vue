<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import StatusPill from '@/components/finance/StatusPill.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import {
    deactivate,
    index,
    print,
    reactivate,
    store,
    update,
} from '@/routes/finance/bank-accounts';
import type { BankAccount, Paginated } from '@/types';

const FIELDS = [
    'account_number',
    'account_name',
    'depository_bank',
    'branch_address',
] as const;

defineProps<{ accounts: Paginated<BankAccount> }>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.bank_accounts'), href: index() }] },
});

const { can } = useAccess();
const editingId = ref<number | null>(null);
const form = useForm({
    account_number: '',
    account_name: '',
    depository_bank: '',
    branch_address: '',
});
const errors = form.errors as Record<string, string>;

function edit(account: BankAccount): void {
    editingId.value = account.id;
    form.account_number = account.account_number;
    form.account_name = account.account_name;
    form.depository_bank = account.depository_bank;
    form.branch_address = account.branch_address ?? '';
}

function reset(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: reset };

    if (editingId.value === null) {
        form.post(store.url(), options);
    } else {
        form.put(update.url(editingId.value), options);
    }
}

function toggle(account: BankAccount): void {
    router.post(
        account.is_active
            ? deactivate.url(account.id)
            : reactivate.url(account.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('finance.bank_accounts.title')" />

    <div class="flex max-w-5xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('finance.bank_accounts.title')"
                :description="t('finance.bank_accounts.description')"
            />
            <Button variant="outline" as-child>
                <a :href="print.url()" target="_blank">{{
                    t('finance.print_list')
                }}</a>
            </Button>
        </div>

        <form
            v-if="can(Permission.BankAccountsManage)"
            class="grid gap-4 rounded-lg border p-3"
            @submit.prevent="submit"
        >
            <div class="grid gap-4 md:grid-cols-4">
                <div v-for="field in FIELDS" :key="field" class="grid gap-2">
                    <Label :for="field">{{
                        t(`finance.bank_accounts.${field}`)
                    }}</Label>
                    <Input
                        :id="field"
                        v-model="form[field]"
                        :required="field !== 'branch_address'"
                    />
                    <InputError :message="errors[field]" />
                </div>
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">{{
                    editingId === null ? t('common.add') : t('common.save')
                }}</Button>
                <Button
                    v-if="editingId !== null"
                    type="button"
                    variant="outline"
                    @click="reset"
                    >{{ t('common.cancel') }}</Button
                >
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th v-for="field in FIELDS" :key="field" class="p-3">
                            {{ t(`finance.bank_accounts.${field}`) }}
                        </th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="account in accounts.data"
                        :key="account.id"
                        class="border-t"
                        :class="{ 'text-muted-foreground': !account.is_active }"
                    >
                        <td class="p-3 font-mono">
                            {{ account.account_number }}
                            <StatusPill
                                v-if="!account.is_active"
                                :label="t('common.inactive')"
                                muted
                            />
                        </td>
                        <td class="p-3">{{ account.account_name }}</td>
                        <td class="p-3">{{ account.depository_bank }}</td>
                        <td class="p-3">{{ account.branch_address }}</td>
                        <td class="p-3">
                            <div
                                v-if="can(Permission.BankAccountsManage)"
                                class="flex justify-end gap-2"
                            >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="edit(account)"
                                    >{{ t('common.edit') }}</Button
                                >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="toggle(account)"
                                    >{{
                                        account.is_active
                                            ? t('finance.deactivate')
                                            : t('finance.reactivate')
                                    }}</Button
                                >
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="accounts.meta" />
    </div>
</template>
