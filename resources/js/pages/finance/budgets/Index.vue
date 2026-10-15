<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import StatusPill from '@/components/finance/StatusPill.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import { index, show, store, summary } from '@/routes/finance/budgets';
import type { BudgetSummary, Paginated } from '@/types';

const props = defineProps<{
    budgets: Paginated<BudgetSummary>;
    suggestedYear: number;
    yearRange: { min: number; max: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.budgets'), href: index() }] },
});

const { can } = useAccess();
const form = useForm({ fiscal_year: props.suggestedYear, title: '' });
</script>

<template>
    <Head :title="t('finance.budgets.title')" />

    <div class="flex max-w-5xl flex-col gap-6 p-4">
        <Heading
            :title="t('finance.budgets.title')"
            :description="t('finance.budgets.description')"
        />

        <form
            v-if="can(Permission.BudgetPrepare)"
            class="grid gap-4 rounded-lg border p-3 md:grid-cols-[10rem_1fr_auto] md:items-end"
            @submit.prevent="form.post(store.url())"
        >
            <div class="grid gap-2">
                <Label for="fiscal_year">{{
                    t('finance.budgets.fiscal_year')
                }}</Label>
                <Input
                    id="fiscal_year"
                    v-model="form.fiscal_year"
                    type="number"
                    :min="yearRange.min"
                    :max="yearRange.max"
                    required
                />
                <InputError :message="form.errors.fiscal_year" />
            </div>
            <div class="grid gap-2">
                <Label for="title">{{
                    t('finance.budgets.budget_title')
                }}</Label>
                <Input
                    id="title"
                    v-model="form.title"
                    :placeholder="
                        t('finance.budgets.default_title', {
                            year: form.fiscal_year,
                        })
                    "
                />
                <InputError :message="form.errors.title" />
            </div>
            <Button type="submit" :disabled="form.processing">{{
                t('finance.budgets.create')
            }}</Button>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">
                            {{ t('finance.budgets.fiscal_year') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.budgets.budget_title') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.budgets.approved_on') }}
                        </th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="budget in budgets.data"
                        :key="budget.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">{{ budget.fiscal_year }}</td>
                        <td class="p-3">
                            <Link
                                :href="show(budget.id)"
                                class="hover:underline"
                                >{{ budget.title }}</Link
                            >
                            <StatusPill
                                :label="budget.status_label"
                                :muted="budget.is_draft"
                            />
                        </td>
                        <td class="p-3">
                            {{ longDate(budget.approved_at) }}
                            <span
                                v-if="budget.approved_by"
                                class="text-muted-foreground"
                                >· {{ budget.approved_by }}</span
                            >
                        </td>
                        <td class="p-3 text-right">
                            <Link
                                v-if="!budget.is_draft"
                                :href="summary(budget.id)"
                                class="text-sm hover:underline"
                                >{{ t('finance.budgets.summary') }}</Link
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="budgets.meta" />
    </div>
</template>
