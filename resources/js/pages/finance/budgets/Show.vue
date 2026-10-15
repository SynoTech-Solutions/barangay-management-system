<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusPill from '@/components/finance/StatusPill.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import { fromCentavos, toCentavos } from '@/lib/money';
import {
    approve,
    copy,
    index,
    lines,
    revise,
    summary,
    update,
} from '@/routes/finance/budgets';
import type {
    BudgetRow,
    BudgetSummary,
    BudgetTotals,
    BudgetVersionHistory,
    SelectOption,
} from '@/types';

const INDENT_REM_PER_LEVEL = 1.25;
const ZERO = '0.00';

type Mode = 'view' | 'prepare' | 'revise';

const props = defineProps<{
    budget: BudgetSummary;
    version: number | null;
    rows: BudgetRow[];
    totals: BudgetTotals;
    history: BudgetVersionHistory[];
    copySources: SelectOption[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.budgets'), href: index() }] },
});

const { can, feature } = useAccess();

const canPrepare = computed(
    () => props.budget.is_draft && can(Permission.BudgetPrepare),
);
const canRevise = computed(
    () =>
        !props.budget.is_draft &&
        can(Permission.BudgetRevise) &&
        feature(FeatureFlag.BudgetRevision),
);
const isEmpty = computed(() => props.rows.every((row) => row.amount === ZERO));

const mode = ref<Mode>(canPrepare.value ? 'prepare' : 'view');
const amounts = reactive<Record<number, string>>(
    Object.fromEntries(props.rows.map((row) => [row.id, row.amount])),
);
const remarks = reactive<Record<number, string>>({});
const versionRemarks = ref('');
const sourceBudgetId = ref<number | null>(null);
const titleForm = useForm({ title: props.budget.title });

const editableRows = computed(() =>
    props.rows.filter(
        (row) =>
            row.is_posting && (row.is_active || toCentavos(row.amount) !== 0),
    ),
);
const changedRows = computed(() =>
    editableRows.value.filter(
        (row) => toCentavos(amounts[row.id] ?? ZERO) !== toCentavos(row.amount),
    ),
);
const difference = computed(() =>
    fromCentavos(
        changedRows.value.reduce(
            (sum, row) =>
                sum +
                toCentavos(amounts[row.id] ?? ZERO) -
                toCentavos(row.amount),
            0,
        ),
    ),
);

function isEditing(row: BudgetRow): boolean {
    return mode.value !== 'view' && row.is_posting;
}

function startRevision(): void {
    mode.value = 'revise';
}

function stopRevision(): void {
    mode.value = 'view';
    props.rows.forEach((row) => (amounts[row.id] = row.amount));
}

function saveAmounts(): void {
    router.put(
        lines.url(props.budget.id),
        {
            lines: editableRows.value.map((row) => ({
                account_id: row.id,
                amount: amounts[row.id] || ZERO,
            })),
        },
        { preserveScroll: true },
    );
}

function saveRevision(): void {
    router.post(
        revise.url(props.budget.id),
        {
            remarks: versionRemarks.value,
            lines: changedRows.value.map((row) => ({
                account_id: row.id,
                amount: amounts[row.id] || ZERO,
                remarks: remarks[row.id] ?? '',
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                mode.value = 'view';
                versionRemarks.value = '';
            },
        },
    );
}

function copyFromSource(): void {
    router.post(
        copy.url(props.budget.id),
        { source_budget_id: sourceBudgetId.value },
        { preserveScroll: true },
    );
}

function approveBudget(): void {
    if (window.confirm(t('finance.budgets.approve_confirm'))) {
        router.post(approve.url(props.budget.id), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="budget.title" />

    <div class="flex max-w-6xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="budget.title"
                :description="
                    t('finance.budgets.version_line', {
                        year: budget.fiscal_year,
                        version: version ?? 1,
                    })
                "
            />
            <div class="flex flex-wrap items-center gap-2">
                <StatusPill
                    :label="budget.status_label"
                    :muted="budget.is_draft"
                />
                <Button v-if="!budget.is_draft" variant="outline" as-child>
                    <Link :href="summary(budget.id)">{{
                        t('finance.budgets.summary')
                    }}</Link>
                </Button>
                <Button
                    v-if="budget.is_draft && can(Permission.BudgetApprove)"
                    @click="approveBudget"
                    >{{ t('finance.budgets.approve') }}</Button
                >
                <Button
                    v-if="canRevise && mode === 'view'"
                    variant="outline"
                    @click="startRevision"
                    >{{ t('finance.budgets.revise') }}</Button
                >
            </div>
        </div>

        <form
            v-if="canPrepare"
            class="flex flex-wrap items-end gap-2"
            @submit.prevent="
                titleForm.put(update.url(budget.id), { preserveScroll: true })
            "
        >
            <div class="grid gap-1">
                <Input
                    v-model="titleForm.title"
                    :aria-label="t('finance.budgets.budget_title')"
                    class="w-80"
                />
                <InputError :message="titleForm.errors.title" />
            </div>
            <Button type="submit" variant="outline" size="sm">{{
                t('finance.budgets.rename')
            }}</Button>
        </form>

        <div
            v-if="canPrepare && isEmpty && copySources.length"
            class="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm"
        >
            <span>{{ t('finance.budgets.copy_from') }}</span>
            <div class="w-64">
                <OptionSelect v-model="sourceBudgetId" :options="copySources" />
            </div>
            <Button
                size="sm"
                variant="outline"
                :disabled="sourceBudgetId === null"
                @click="copyFromSource"
                >{{ t('finance.budgets.copy') }}</Button
            >
        </div>

        <dl class="grid gap-3 rounded-lg border p-3 text-sm md:grid-cols-4">
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.budgets.total_income') }}
                </dt>
                <dd class="font-medium tabular-nums">{{ totals.income }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.budgets.total_expense') }}
                </dt>
                <dd class="font-medium tabular-nums">{{ totals.expense }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{ t('finance.budgets.total_other') }}
                </dt>
                <dd class="font-medium tabular-nums">{{ totals.other }}</dd>
            </div>
            <div v-if="totals.unappropriated !== null">
                <dt class="text-muted-foreground">
                    {{ t('finance.budgets.unappropriated') }}
                </dt>
                <dd
                    class="font-medium tabular-nums"
                    :class="
                        toCentavos(totals.unappropriated) > 0
                            ? 'text-blue-700'
                            : 'text-red-700'
                    "
                >
                    {{ totals.unappropriated }}
                </dd>
            </div>
        </dl>

        <div
            v-if="mode === 'revise'"
            class="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/40 p-3 text-sm"
        >
            <span
                :class="difference === ZERO ? 'text-green-700' : 'text-red-700'"
                >{{
                    t('finance.budgets.difference', { amount: difference })
                }}</span
            >
            <Input
                v-model="versionRemarks"
                class="max-w-sm"
                :placeholder="t('finance.budgets.revision_remarks')"
            />
            <Button
                size="sm"
                :disabled="changedRows.length === 0"
                @click="saveRevision"
                >{{ t('finance.budgets.save_revision') }}</Button
            >
            <Button size="sm" variant="outline" @click="stopRevision">{{
                t('common.cancel')
            }}</Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('finance.accounts.code') }}</th>
                        <th class="p-3">
                            {{ t('finance.budgets.particulars') }}
                        </th>
                        <th class="p-3 text-right">
                            {{ t('finance.budgets.amount') }}
                        </th>
                        <th v-if="mode === 'revise'" class="p-3">
                            {{ t('finance.budgets.line_remarks') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="border-t"
                        :class="{
                            'bg-muted/30 font-semibold': !row.is_posting,
                        }"
                    >
                        <td class="p-2 font-mono">{{ row.code }}</td>
                        <td class="p-2">
                            <span
                                :style="{
                                    paddingLeft: `${row.level * INDENT_REM_PER_LEVEL}rem`,
                                }"
                                >{{ row.name }}</span
                            >
                        </td>
                        <td class="p-2 text-right tabular-nums">
                            <Input
                                v-if="
                                    isEditing(row) &&
                                    (row.is_active || mode === 'revise')
                                "
                                v-model="amounts[row.id]"
                                class="ml-auto w-40 text-right"
                                type="number"
                                step="0.01"
                                min="0"
                                :aria-label="row.name"
                            />
                            <span v-else>{{
                                row.is_posting ? row.amount : row.total
                            }}</span>
                        </td>
                        <td v-if="mode === 'revise'" class="p-2">
                            <Input
                                v-if="
                                    row.is_posting &&
                                    changedRows.some((r) => r.id === row.id)
                                "
                                v-model="remarks[row.id]"
                                :placeholder="t('finance.budgets.line_remarks')"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="mode === 'prepare'" class="flex gap-2">
            <Button @click="saveAmounts">{{
                t('finance.budgets.save_amounts')
            }}</Button>
        </div>

        <section v-if="history.length" class="flex flex-col gap-3">
            <h3 class="font-semibold">{{ t('finance.budgets.history') }}</h3>
            <article
                v-for="entry in history"
                :key="entry.number"
                class="rounded-lg border p-3 text-sm"
            >
                <p class="font-medium">
                    {{
                        entry.is_revision
                            ? t('finance.budgets.revision_number', {
                                  number: entry.number,
                              })
                            : t('finance.budgets.original_version')
                    }}
                    <span class="text-muted-foreground">
                        ·
                        {{
                            longDate(entry.approved_at) ||
                            t('finance.budgets.not_approved')
                        }}
                        <template v-if="entry.created_by">
                            · {{ entry.created_by }}</template
                        >
                        ·
                        {{
                            t('finance.budgets.total', { amount: entry.total })
                        }}
                    </span>
                </p>
                <p v-if="entry.remarks">{{ entry.remarks }}</p>
                <table v-if="entry.changes.length" class="mt-2 w-full">
                    <tbody>
                        <tr
                            v-for="change in entry.changes"
                            :key="change.code"
                            class="border-t"
                        >
                            <td class="py-1 font-mono">{{ change.code }}</td>
                            <td class="py-1">{{ change.name }}</td>
                            <td class="py-1 text-right tabular-nums">
                                {{ change.previous }} → {{ change.amount }}
                            </td>
                            <td class="py-1 text-right tabular-nums">
                                {{ change.delta }}
                            </td>
                            <td class="py-1 pl-3 text-muted-foreground">
                                {{ change.remarks }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </article>
        </section>
    </div>
</template>
