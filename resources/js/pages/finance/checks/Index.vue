<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import StatusPill from '@/components/finance/StatusPill.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import { cancel, edit, index, print, release } from '@/routes/finance/checks';
import { create as createDirect } from '@/routes/finance/checks/direct';
import { show as showVoucher } from '@/routes/finance/vouchers';
import type {
    Check,
    CheckFilters,
    CheckStatus,
    Paginated,
    SelectOption,
} from '@/types';

const SEARCH_DELAY_MS = 300;

const props = defineProps<{
    checks: Paginated<Check>;
    total: string;
    filters: CheckFilters;
    statuses: SelectOption<CheckStatus>[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.checks'), href: index() }] },
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

const releasing = ref<Check | null>(null);
const releaseForm = useForm({ received_by: '', confirmed: false });

function openRelease(check: Check): void {
    releaseForm.reset();
    releaseForm.clearErrors();
    releasing.value = check;
}

function submitRelease(): void {
    if (releasing.value === null) {
        return;
    }

    releaseForm.post(release.url(releasing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            releasing.value = null;
        },
    });
}

function cancelCheck(check: Check): void {
    const reason = window.prompt(t('finance.checks.cancel_prompt'));

    if (reason !== null && reason.trim() !== '') {
        router.post(cancel.url(check.id), { reason }, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('finance.checks.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('finance.checks.title')"
                :description="t('finance.checks.description')"
            />
            <Button
                v-if="
                    feature(FeatureFlag.DirectCheckIssuance) &&
                    can(Permission.ChecksIssueDirect)
                "
                as-child
            >
                <Link :href="createDirect()">{{
                    t('finance.checks.direct')
                }}</Link>
            </Button>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-3">
            <Input
                v-model="state.search"
                :placeholder="t('finance.checks.search')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('documents.all_statuses')"
                @update:model-value="apply"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('finance.checks.number') }}</th>
                        <th class="p-3">{{ t('finance.checks.date') }}</th>
                        <th class="p-3">{{ t('finance.checks.payee') }}</th>
                        <th class="p-3">{{ t('finance.checks.voucher') }}</th>
                        <th class="p-3">
                            {{ t('finance.checks.bank_account') }}
                        </th>
                        <th class="p-3 text-right">
                            {{ t('finance.checks.amount') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.checks.received_by') }}
                        </th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="check in checks.data"
                        :key="check.id"
                        class="border-t"
                        :class="{
                            'text-muted-foreground line-through':
                                check.status === 'cancelled',
                        }"
                    >
                        <td class="p-3 font-mono">
                            {{ check.check_number }}
                            <StatusPill
                                :label="check.status_label"
                                :muted="check.status === 'cancelled'"
                            />
                        </td>
                        <td class="p-3">{{ longDate(check.check_date) }}</td>
                        <td class="p-3">{{ check.payee_name }}</td>
                        <td class="p-3 font-mono">
                            <Link
                                v-if="check.voucher_id"
                                :href="showVoucher(check.voucher_id)"
                                class="hover:underline"
                                >{{ check.voucher_number }}</Link
                            >
                            <span v-else>{{
                                t('finance.checks.direct_label')
                            }}</span>
                        </td>
                        <td class="p-3">{{ check.bank_account }}</td>
                        <td class="p-3 text-right tabular-nums">
                            {{ check.amount }}
                        </td>
                        <td class="p-3">{{ check.received_by_name }}</td>
                        <td class="p-3">
                            <div class="flex justify-end gap-2">
                                <template v-if="check.status === 'for-release'">
                                    <Button
                                        v-if="can(Permission.ChecksRelease)"
                                        size="sm"
                                        @click="openRelease(check)"
                                        >{{
                                            t('finance.checks.release')
                                        }}</Button
                                    >
                                    <Button
                                        v-if="
                                            can(Permission.ChecksIssue) &&
                                            (!check.is_direct ||
                                                can(
                                                    Permission.ChecksIssueDirect,
                                                ))
                                        "
                                        size="sm"
                                        variant="outline"
                                        as-child
                                    >
                                        <Link :href="edit(check.id)">{{
                                            t('common.edit')
                                        }}</Link>
                                    </Button>
                                    <Button
                                        v-if="can(Permission.ChecksCancel)"
                                        size="sm"
                                        variant="outline"
                                        @click="cancelCheck(check)"
                                        >{{
                                            t('finance.checks.cancel')
                                        }}</Button
                                    >
                                </template>
                                <Button
                                    v-if="
                                        check.status !== 'cancelled' &&
                                        feature(FeatureFlag.CheckPrinting) &&
                                        can(Permission.ChecksPrint)
                                    "
                                    size="sm"
                                    variant="outline"
                                    as-child
                                >
                                    <a
                                        :href="print.url(check.id)"
                                        target="_blank"
                                        >{{ t('documents.print') }}</a
                                    >
                                </Button>
                                <span
                                    v-if="check.cancel_reason"
                                    class="text-xs no-underline"
                                    >{{ check.cancel_reason }}</span
                                >
                            </div>
                        </td>
                    </tr>
                    <tr v-if="checks.data.length === 0">
                        <td colspan="8" class="p-3 text-muted-foreground">
                            {{ t('finance.checks.empty') }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t bg-muted/30 font-medium">
                    <tr>
                        <td colspan="5" class="p-3 text-right">
                            {{ t('finance.checks.totals') }}
                        </td>
                        <td class="p-3 text-right tabular-nums">{{ total }}</td>
                        <td colspan="2" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <Pagination :meta="checks.meta" />

        <Dialog
            :open="releasing !== null"
            @update:open="(open) => !open && (releasing = null)"
        >
            <DialogContent class="sm:max-w-md">
                <form
                    class="flex flex-col gap-4"
                    @submit.prevent="submitRelease"
                >
                    <DialogHeader>
                        <DialogTitle>{{
                            t('finance.checks.release_title', {
                                number: releasing?.check_number ?? '',
                            })
                        }}</DialogTitle>
                        <DialogDescription>{{
                            t('finance.checks.release_description', {
                                payee: releasing?.payee_name ?? '',
                                amount: releasing?.amount ?? '',
                            })
                        }}</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="received_by">{{
                            t('finance.checks.received_by')
                        }}</Label>
                        <Input
                            id="received_by"
                            v-model="releaseForm.received_by"
                            required
                        />
                        <InputError :message="releaseForm.errors.received_by" />
                    </div>
                    <CheckboxField
                        v-model="releaseForm.confirmed"
                        :label="t('finance.checks.release_confirm')"
                    />
                    <InputError :message="releaseForm.errors.confirmed" />
                    <DialogFooter>
                        <Button
                            type="submit"
                            :disabled="
                                releaseForm.processing || !releaseForm.confirmed
                            "
                            >{{ t('finance.checks.release') }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
