<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import DocumentBulkActions from '@/components/documents/DocumentBulkActions.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import {
    cancel,
    create,
    edit,
    exportMethod,
    index,
    listPrint,
    print,
    release,
} from '@/routes/forms/transactions';
import type {
    FormTransaction,
    FormTransactionFilters,
    Paginated,
    SelectOption,
} from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.form_transactions'), href: index() }],
    },
});

const props = defineProps<{
    transactions: Paginated<FormTransaction>;
    total: string;
    filters: FormTransactionFilters;
    statuses: SelectOption<string>[];
    types: SelectOption[];
    templates: SelectOption[];
}>();

const { can, feature } = useAccess();
const state = reactive({
    search: props.filters.search,
    status: props.filters.status,
    type_id: props.filters.type_id,
    template_id: props.filters.template_id,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
const selected = ref<number[]>([]);

const canAct = computed(
    () =>
        can(Permission.FormTransactionsRelease) ||
        can(Permission.FormTransactionsCancel),
);

const query = computed(
    () =>
        Object.fromEntries(
            Object.entries(state).filter(
                ([, value]) => value !== null && value !== '',
            ),
        ) as Record<string, string>,
);

function apply(): void {
    selected.value = [];
    router.get(index.url(), query.value, {
        preserveState: true,
        replace: true,
    });
}

const applySearch = useDebounceFn(apply, SEARCH_DELAY_MS);

function toggle(id: number, checked: boolean): void {
    selected.value = checked
        ? [...selected.value, id]
        : selected.value.filter((value) => value !== id);
}
</script>

<template>
    <Head :title="t('forms.transactions.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('forms.transactions.title')"
                :description="t('forms.transactions.description')"
            />
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="feature(FeatureFlag.GridPrint)"
                    variant="outline"
                    as-child
                >
                    <a :href="listPrint.url({ query })" target="_blank">{{
                        t('forms.transactions.print_list')
                    }}</a>
                </Button>
                <Button
                    v-if="feature(FeatureFlag.GridExcelExport)"
                    variant="outline"
                    as-child
                >
                    <a :href="exportMethod.url({ query })">{{
                        t('forms.transactions.export')
                    }}</a>
                </Button>
                <Button v-if="can(Permission.FormTransactionsCreate)" as-child>
                    <Link :href="create()">{{
                        t('forms.transactions.new')
                    }}</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-3">
            <Input
                v-model="state.search"
                :placeholder="t('forms.transactions.search')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('documents.all_statuses')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.type_id"
                :options="types"
                :placeholder="t('forms.templates.all_types')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.template_id"
                :options="templates"
                :placeholder="t('forms.transactions.all_templates')"
                @update:model-value="apply"
            />
            <Input
                v-model="state.from"
                type="date"
                :aria-label="t('documents.from')"
                @change="apply"
            />
            <Input
                v-model="state.to"
                type="date"
                :aria-label="t('documents.to')"
                @change="apply"
            />
        </div>

        <DocumentBulkActions
            v-if="canAct"
            v-model="selected"
            :release-url="release.url()"
            :cancel-url="cancel.url()"
            :can-release="can(Permission.FormTransactionsRelease)"
            :can-cancel="can(Permission.FormTransactionsCancel)"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th v-if="canAct" class="w-8 p-3" />
                        <th class="p-3">
                            {{ t('forms.transactions.number') }}
                        </th>
                        <th class="p-3">
                            {{ t('forms.transactions.resident') }}
                        </th>
                        <th class="p-3">
                            {{ t('forms.transactions.template') }}
                        </th>
                        <th class="p-3">{{ t('documents.or_number') }}</th>
                        <th class="p-3 text-right">
                            {{ t('documents.amount') }}
                        </th>
                        <th class="p-3">{{ t('documents.status') }}</th>
                        <th class="p-3">{{ t('documents.issued_at') }}</th>
                        <th class="p-3 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!transactions.data.length">
                        <td colspan="9" class="p-3 text-muted-foreground">
                            {{ t('forms.transactions.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="transaction in transactions.data"
                        :key="transaction.id"
                        class="border-t"
                    >
                        <td v-if="canAct" class="p-3">
                            <CheckboxField
                                v-if="transaction.is_pending"
                                :model-value="selected.includes(transaction.id)"
                                label=""
                                @update:model-value="
                                    (checked: boolean) =>
                                        toggle(transaction.id, checked)
                                "
                            />
                        </td>
                        <td class="p-3 font-mono">
                            {{ transaction.transaction_number }}
                        </td>
                        <td class="p-3">
                            {{ transaction.resident?.full_name }}
                        </td>
                        <td class="p-3">
                            {{ transaction.template }}
                            <span class="block text-muted-foreground">{{
                                transaction.template_type
                            }}</span>
                        </td>
                        <td class="p-3 font-mono">
                            {{ transaction.or_number }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ transaction.amount }}
                        </td>
                        <td class="p-3">
                            <DocumentStatusBadge
                                :status="transaction.status"
                                :label="transaction.status_label"
                                :reason="transaction.cancel_reason"
                            />
                        </td>
                        <td class="p-3">
                            {{
                                new Date(
                                    transaction.issued_at,
                                ).toLocaleDateString()
                            }}
                        </td>
                        <td class="p-3 text-right">
                            <div
                                v-if="can(Permission.FormTransactionsCreate)"
                                class="flex justify-end gap-2"
                            >
                                <Button
                                    v-if="transaction.is_pending"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(transaction.id)">{{
                                        t('common.edit')
                                    }}</Link>
                                </Button>
                                <Button
                                    v-if="transaction.status !== 'cancelled'"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <a
                                        :href="print.url(transaction.id)"
                                        target="_blank"
                                        >{{
                                            transaction.print_count > 0
                                                ? t(
                                                      'forms.transactions.reprint',
                                                  )
                                                : t('documents.print')
                                        }}</a
                                    >
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t font-semibold">
                        <td :colspan="canAct ? 5 : 4" class="p-3 text-right">
                            {{ t('documents.totals') }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ total }}
                        </td>
                        <td colspan="3" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <Pagination :meta="transactions.meta" />
    </div>
</template>
