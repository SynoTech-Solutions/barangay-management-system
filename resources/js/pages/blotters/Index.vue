<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import BlotterStatusBadge from '@/components/documents/BlotterStatusBadge.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { create, index, show } from '@/routes/blotters';
import type {
    BlotterListFilters,
    BlotterListItem,
    Paginated,
    SelectOption,
} from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.blotters'), href: index() }] },
});

const props = defineProps<{
    blotters: Paginated<BlotterListItem>;
    filters: BlotterListFilters;
    statuses: SelectOption<string>[];
    lookups: Record<string, SelectOption[]>;
}>();

const { can } = useAccess();
const state = reactive({
    search: props.filters.search,
    status: props.filters.status,
    incident_type_id: props.filters.incident_type_id,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
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
</script>

<template>
    <Head :title="t('blotters.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('blotters.title')"
                :description="t('blotters.description')"
            />
            <Button v-if="can(Permission.BlotterManage)" as-child>
                <Link :href="create()">{{ t('blotters.new') }}</Link>
            </Button>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-5">
            <Input
                v-model="state.search"
                :placeholder="t('blotters.search_placeholder')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('documents.all_statuses')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.incident_type_id"
                :options="lookups['blotter-incident-type'] ?? []"
                :placeholder="t('blotters.all_types')"
                @update:model-value="apply"
            />
            <Input
                v-model="state.from"
                type="date"
                :aria-label="t('blotters.from')"
                @change="apply"
            />
            <Input
                v-model="state.to"
                type="date"
                :aria-label="t('blotters.to')"
                @change="apply"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('blotters.number') }}</th>
                        <th class="p-3">{{ t('blotters.incident_at') }}</th>
                        <th class="p-3">{{ t('blotters.incident_type') }}</th>
                        <th class="p-3">{{ t('blotters.parties') }}</th>
                        <th class="p-3">{{ t('documents.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="blotter in blotters.data"
                        :key="blotter.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">
                            <Link
                                :href="show(blotter.id)"
                                class="hover:underline"
                                >{{ blotter.blotter_number }}</Link
                            >
                        </td>
                        <td class="p-3">
                            {{ new Date(blotter.incident_at).toLocaleString() }}
                        </td>
                        <td class="p-3">{{ blotter.incident_type }}</td>
                        <td class="p-3">
                            {{
                                t('blotters.party_counts', {
                                    complainants: blotter.complainant_count,
                                    respondents: blotter.respondent_count,
                                })
                            }}
                        </td>
                        <td class="p-3">
                            <BlotterStatusBadge
                                :status="blotter.status"
                                :label="blotter.status_label"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="blotters.meta" />
    </div>
</template>
