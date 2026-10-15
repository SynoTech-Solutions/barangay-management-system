<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusPill from '@/components/finance/StatusPill.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import {
    deactivate,
    destroy,
    index,
    reactivate,
    store,
    update,
} from '@/routes/finance/accounts';
import type { Account, AccountKind, SelectOption } from '@/types';

const INDENT_REM_PER_LEVEL = 1.25;

const PARENT_KINDS: Record<AccountKind, AccountKind[]> = {
    group: [],
    title: ['group', 'title'],
    subsidiary: ['title', 'subsidiary'],
};

const props = defineProps<{
    accounts: Account[];
    kinds: SelectOption<string>[];
    groupTypes: SelectOption<string>[];
    canEditLocked: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.chart_of_accounts'), href: index() }],
    },
});

const { can } = useAccess();
const canManage = computed(() => can(Permission.ChartOfAccountsManage));
const editing = ref<Account | null>(null);

const form = useForm({
    kind: 'title' as AccountKind,
    code: '',
    series: '',
    name: '',
    remarks: '',
    parent_id: null as number | null,
    group_type: null as string | null,
});
const errors = form.errors as Record<string, string>;

const parentOptions = computed<SelectOption[]>(() =>
    props.accounts
        .filter(
            (account) =>
                account.is_active &&
                PARENT_KINDS[form.kind].includes(account.kind),
        )
        .map((account) => ({
            value: account.id,
            label: `${account.code} - ${account.name}`,
        })),
);

const previewCode = computed(() => {
    const parent = props.accounts.find((a) => a.id === form.parent_id);

    return form.kind === 'subsidiary' && parent && form.series
        ? `${parent.code}-${form.series}`
        : null;
});

function editable(account: Account): boolean {
    return canManage.value && (!account.is_locked || props.canEditLocked);
}

function edit(account: Account): void {
    editing.value = account;
    form.kind = account.kind;
    form.name = account.name;
    form.remarks = account.remarks ?? '';
    form.group_type = account.group_type;
}

function reset(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: reset };

    if (editing.value === null) {
        form.post(store.url(), options);
    } else {
        form.put(update.url(editing.value.id), options);
    }
}

function toggle(account: Account): void {
    router.post(
        account.is_active
            ? deactivate.url(account.id)
            : reactivate.url(account.id),
        {},
        { preserveScroll: true },
    );
}

function remove(account: Account): void {
    if (window.confirm(t('finance.accounts.delete_confirm'))) {
        router.delete(destroy.url(account.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('finance.accounts.title')" />

    <div class="flex max-w-6xl flex-col gap-6 p-4">
        <Heading
            :title="t('finance.accounts.title')"
            :description="t('finance.accounts.description')"
        />

        <form
            v-if="canManage"
            class="grid gap-4 rounded-lg border p-3"
            @submit.prevent="submit"
        >
            <p v-if="editing" class="text-sm font-medium">
                {{ t('finance.accounts.editing', { code: editing.code }) }}
            </p>
            <div class="grid gap-4 md:grid-cols-3">
                <div v-if="!editing" class="grid gap-2">
                    <Label for="kind">{{ t('finance.accounts.kind') }}</Label>
                    <select
                        id="kind"
                        v-model="form.kind"
                        class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="kind in kinds"
                            :key="kind.value"
                            :value="kind.value"
                        >
                            {{ kind.label }}
                        </option>
                    </select>
                </div>
                <div v-if="form.kind === 'group'" class="grid gap-2">
                    <Label for="group_type">{{
                        t('finance.accounts.group_type')
                    }}</Label>
                    <OptionSelect
                        id="group_type"
                        v-model="form.group_type"
                        :options="groupTypes"
                    />
                    <InputError :message="errors.group_type" />
                </div>
                <div
                    v-if="!editing && form.kind !== 'group'"
                    class="grid gap-2"
                >
                    <Label for="parent_id">{{
                        t('finance.accounts.parent')
                    }}</Label>
                    <OptionSelect
                        id="parent_id"
                        v-model="form.parent_id"
                        :options="parentOptions"
                    />
                    <InputError :message="errors.parent_id" />
                </div>
                <div
                    v-if="!editing && form.kind !== 'subsidiary'"
                    class="grid gap-2"
                >
                    <Label for="code">{{ t('finance.accounts.code') }}</Label>
                    <Input id="code" v-model="form.code" />
                    <InputError :message="errors.code" />
                </div>
                <div
                    v-if="!editing && form.kind === 'subsidiary'"
                    class="grid gap-2"
                >
                    <Label for="series">{{
                        t('finance.accounts.series')
                    }}</Label>
                    <Input id="series" v-model="form.series" />
                    <p v-if="previewCode" class="text-xs text-muted-foreground">
                        {{
                            t('finance.accounts.code_preview', {
                                code: previewCode,
                            })
                        }}
                    </p>
                    <InputError :message="errors.series" />
                </div>
                <div class="grid gap-2">
                    <Label for="name">{{ t('finance.accounts.name') }}</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="remarks">{{
                        t('finance.accounts.remarks')
                    }}</Label>
                    <Input id="remarks" v-model="form.remarks" />
                    <InputError :message="errors.remarks" />
                </div>
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">{{
                    editing ? t('common.save') : t('common.add')
                }}</Button>
                <Button
                    v-if="editing"
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
                        <th class="p-3">{{ t('finance.accounts.code') }}</th>
                        <th class="p-3">{{ t('finance.accounts.name') }}</th>
                        <th class="p-3">{{ t('finance.accounts.kind') }}</th>
                        <th v-if="canManage" class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="account in accounts"
                        :key="account.id"
                        class="border-t"
                        :class="{ 'text-muted-foreground': !account.is_active }"
                    >
                        <td class="p-3 font-mono">{{ account.code }}</td>
                        <td class="p-3">
                            <span
                                :class="{
                                    'font-semibold': !account.is_posting,
                                }"
                                :style="{
                                    paddingLeft: `${account.level * INDENT_REM_PER_LEVEL}rem`,
                                }"
                                >{{ account.name }}</span
                            >
                            <StatusPill
                                v-if="account.group_type_label"
                                :label="account.group_type_label"
                            />
                            <StatusPill
                                v-if="account.is_locked"
                                :label="t('finance.accounts.locked')"
                                muted
                            />
                            <StatusPill
                                v-if="!account.is_active"
                                :label="t('common.inactive')"
                                muted
                            />
                            <div
                                v-if="account.remarks"
                                class="text-xs text-muted-foreground"
                                :style="{
                                    paddingLeft: `${account.level * INDENT_REM_PER_LEVEL}rem`,
                                }"
                            >
                                {{ account.remarks }}
                            </div>
                        </td>
                        <td class="p-3">{{ account.kind_label }}</td>
                        <td v-if="canManage" class="p-3">
                            <div
                                v-if="editable(account)"
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
                                <Button
                                    v-if="!account.is_locked"
                                    size="sm"
                                    variant="outline"
                                    @click="remove(account)"
                                    >{{ t('finance.accounts.delete') }}</Button
                                >
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
