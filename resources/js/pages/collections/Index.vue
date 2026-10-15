<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import StatusPill from '@/components/finance/StatusPill.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import { cancel, create, edit, index, print } from '@/routes/collections';
import { index as reportIndex } from '@/routes/collections/report';
import { index as typesIndex } from '@/routes/collections/types';
import type {
    OtherCollection,
    OtherCollectionFilters,
    Paginated,
    SelectOption,
} from '@/types';

const SEARCH_DELAY_MS = 300;

const props = defineProps<{
    collections: Paginated<OtherCollection>;
    total: string;
    filters: OtherCollectionFilters;
    types: SelectOption[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.collections'), href: index() }] },
});

const { can, feature } = useAccess();
const state = reactive({
    search: props.filters.search,
    collection_type_id: props.filters.collection_type_id,
    include_cancelled: props.filters.include_cancelled,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const query = computed(
    () =>
        Object.fromEntries(
            Object.entries(state)
                .filter(
                    ([, value]) =>
                        value !== null && value !== '' && value !== false,
                )
                .map(([key, value]) => [
                    key,
                    value === true ? '1' : String(value),
                ]),
        ) as Record<string, string>,
);

function apply(): void {
    router.get(index.url(), query.value, {
        preserveState: true,
        replace: true,
    });
}

const applySearch = useDebounceFn(apply, SEARCH_DELAY_MS);

function cancelCollection(collection: OtherCollection): void {
    const reason = window.prompt(t('collections.cancel_prompt'));

    if (reason !== null && reason.trim() !== '') {
        router.post(
            cancel.url(collection.id),
            { reason },
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head :title="t('collections.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('collections.title')"
                :description="t('collections.description')"
            />
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="feature(FeatureFlag.ConsolidatedCollectionReport)"
                    variant="outline"
                    as-child
                >
                    <Link :href="reportIndex()">{{
                        t('nav.collection_report')
                    }}</Link>
                </Button>
                <Button
                    v-if="can(Permission.CollectionTypesManage)"
                    variant="outline"
                    as-child
                >
                    <Link :href="typesIndex()">{{
                        t('collections.types.title')
                    }}</Link>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="print.url({ query })" target="_blank">{{
                        t('finance.print_list')
                    }}</a>
                </Button>
                <Button v-if="can(Permission.CollectionsCreate)" as-child>
                    <Link :href="create()">{{ t('collections.new') }}</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-5">
            <Input
                v-model="state.search"
                :placeholder="t('collections.search')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.collection_type_id"
                :options="types"
                :placeholder="t('collections.all_types')"
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
            <CheckboxField
                v-model="state.include_cancelled"
                :label="t('collections.include_cancelled')"
                @update:model-value="apply"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('collections.number') }}</th>
                        <th class="p-3">{{ t('collections.paid_on') }}</th>
                        <th class="p-3">{{ t('collections.payer') }}</th>
                        <th class="p-3">{{ t('collections.type') }}</th>
                        <th class="p-3">{{ t('collections.or_number') }}</th>
                        <th class="p-3 text-right">
                            {{ t('collections.amount') }}
                        </th>
                        <th class="p-3">{{ t('collections.recorded_by') }}</th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="collection in collections.data"
                        :key="collection.id"
                        class="border-t"
                        :class="{
                            'text-muted-foreground line-through':
                                collection.is_cancelled,
                        }"
                    >
                        <td class="p-3 font-mono">
                            {{ collection.collection_number }}
                            <StatusPill
                                v-if="collection.is_cancelled"
                                :label="t('collections.cancelled')"
                                muted
                            />
                        </td>
                        <td class="p-3">{{ longDate(collection.paid_on) }}</td>
                        <td class="p-3">{{ collection.payer_name }}</td>
                        <td class="p-3">{{ collection.collection_type }}</td>
                        <td class="p-3 font-mono">
                            {{ collection.or_number }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ collection.amount }}
                        </td>
                        <td class="p-3">{{ collection.recorded_by }}</td>
                        <td class="p-3">
                            <div
                                v-if="!collection.is_cancelled"
                                class="flex justify-end gap-2"
                            >
                                <Button
                                    v-if="can(Permission.CollectionsCreate)"
                                    size="sm"
                                    variant="outline"
                                    as-child
                                >
                                    <Link :href="edit(collection.id)">{{
                                        t('common.edit')
                                    }}</Link>
                                </Button>
                                <Button
                                    v-if="can(Permission.CollectionsCancel)"
                                    size="sm"
                                    variant="outline"
                                    @click="cancelCollection(collection)"
                                    >{{ t('collections.cancel') }}</Button
                                >
                            </div>
                            <div
                                v-else-if="collection.cancel_reason"
                                class="text-xs no-underline"
                            >
                                {{ collection.cancel_reason }}
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t bg-muted/30 font-medium">
                    <tr>
                        <td colspan="5" class="p-3 text-right">
                            {{ t('documents.totals') }}
                        </td>
                        <td class="p-3 text-right tabular-nums">{{ total }}</td>
                        <td colspan="2" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <Pagination :meta="collections.meta" />
    </div>
</template>
