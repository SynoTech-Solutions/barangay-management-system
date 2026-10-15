<script setup lang="ts">
import { Permission } from '@/contracts/generated';
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
import { cancel, create, edit, index, print, release } from '@/routes/id-cards';
import { print as printLedger } from '@/routes/id-cards/ledger';
import type {
    IdCard,
    IdCardLedgerFilters,
    Paginated,
    SelectOption,
} from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.id_cards'), href: index() }] },
});

const props = defineProps<{
    cards: Paginated<IdCard>;
    filters: IdCardLedgerFilters;
    statuses: SelectOption<string>[];
}>();

const { can } = useAccess();
const state = reactive({
    search: props.filters.search,
    status: props.filters.status,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
const selected = ref<number[]>([]);

const canAct = computed(
    () => can(Permission.IdCardsRelease) || can(Permission.IdCardsCancel),
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
    <Head :title="t('id_cards.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('id_cards.title')"
                :description="t('id_cards.description')"
            />
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <a :href="printLedger.url({ query })" target="_blank">{{
                        t('id_cards.print_ledger')
                    }}</a>
                </Button>
                <Button v-if="can(Permission.IdCardsIssue)" as-child>
                    <Link :href="create()">{{ t('id_cards.new') }}</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-4">
            <Input
                v-model="state.search"
                :placeholder="t('id_cards.search_placeholder')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('id_cards.all_statuses')"
                @update:model-value="apply"
            />
            <Input
                v-model="state.from"
                type="date"
                :aria-label="t('id_cards.from')"
                @change="apply"
            />
            <Input
                v-model="state.to"
                type="date"
                :aria-label="t('id_cards.to')"
                @change="apply"
            />
        </div>

        <DocumentBulkActions
            v-if="canAct"
            v-model="selected"
            :release-url="release.url()"
            :cancel-url="cancel.url()"
            :can-release="can(Permission.IdCardsRelease)"
            :can-cancel="can(Permission.IdCardsCancel)"
            ids-field="card_ids"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th v-if="canAct" class="w-8 p-3" />
                        <th class="p-3">{{ t('id_cards.card_number') }}</th>
                        <th class="p-3">{{ t('id_cards.resident') }}</th>
                        <th class="p-3">{{ t('id_cards.status') }}</th>
                        <th class="p-3">{{ t('id_cards.or_number') }}</th>
                        <th class="p-3 text-right">
                            {{ t('id_cards.amount') }}
                        </th>
                        <th class="p-3">{{ t('id_cards.issued_at') }}</th>
                        <th class="p-3 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="card in cards.data"
                        :key="card.id"
                        class="border-t"
                    >
                        <td v-if="canAct" class="p-3">
                            <CheckboxField
                                v-if="card.is_pending"
                                :model-value="selected.includes(card.id)"
                                label=""
                                @update:model-value="
                                    (checked: boolean) =>
                                        toggle(card.id, checked)
                                "
                            />
                        </td>
                        <td class="p-3 font-mono">{{ card.card_number }}</td>
                        <td class="p-3">
                            <div class="font-medium">
                                {{ card.resident?.full_name }}
                            </div>
                            <div class="text-muted-foreground">
                                {{ card.resident?.resident_number }}
                            </div>
                        </td>
                        <td class="p-3">
                            <DocumentStatusBadge
                                :status="card.status"
                                :label="card.status_label"
                                :reason="card.cancel_reason"
                            />
                        </td>
                        <td class="p-3">{{ card.or_number ?? '' }}</td>
                        <td class="p-3 text-right">{{ card.amount }}</td>
                        <td class="p-3">
                            {{ new Date(card.issued_at).toLocaleDateString() }}
                            <div class="text-muted-foreground">
                                {{ card.issued_by ?? '' }}
                            </div>
                        </td>
                        <td class="p-3 text-right">
                            <div
                                v-if="can(Permission.IdCardsIssue)"
                                class="flex justify-end gap-2"
                            >
                                <Button
                                    v-if="card.is_pending"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(card.id)">{{
                                        t('common.edit')
                                    }}</Link>
                                </Button>
                                <Button
                                    v-if="card.status !== 'cancelled'"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <a
                                        :href="print.url(card.id)"
                                        target="_blank"
                                        >{{ t('id_cards.print') }}</a
                                    >
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="cards.meta" />
    </div>
</template>
