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
import { cancel, create, index, release, show } from '@/routes/cedulas';
import { index as templatesIndex } from '@/routes/cedulas/templates';
import type {
    Cedula,
    DocumentListFilters,
    Paginated,
    SelectOption,
} from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.cedulas'), href: index() }] },
});

const props = defineProps<{
    cedulas: Paginated<Cedula>;
    total: string;
    filters: DocumentListFilters;
    statuses: SelectOption<string>[];
}>();

const { can, feature } = useAccess();
const state = reactive({
    search: props.filters.search,
    status: props.filters.status,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
const selected = ref<number[]>([]);

const canAct = computed(
    () => can(Permission.CedulaRelease) || can(Permission.CedulaCancel),
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
    <Head :title="t('cedulas.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('cedulas.title')"
                :description="t('cedulas.description')"
            />
            <div class="flex gap-2">
                <Button
                    v-if="
                        can(Permission.CedulaIssue) &&
                        feature(FeatureFlag.CedulaTemplates)
                    "
                    variant="outline"
                    as-child
                >
                    <Link :href="templatesIndex()">{{
                        t('cedulas.templates')
                    }}</Link>
                </Button>
                <Button v-if="can(Permission.CedulaIssue)" as-child>
                    <Link :href="create()">{{ t('cedulas.new') }}</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-4">
            <Input
                v-model="state.search"
                :placeholder="t('cedulas.search_placeholder')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('documents.all_statuses')"
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
            :can-release="can(Permission.CedulaRelease)"
            :can-cancel="can(Permission.CedulaCancel)"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th v-if="canAct" class="w-8 p-3" />
                        <th class="p-3">{{ t('cedulas.number') }}</th>
                        <th class="p-3">{{ t('clearances.resident') }}</th>
                        <th class="p-3">{{ t('documents.status') }}</th>
                        <th class="p-3 text-right">
                            {{ t('cedulas.breakdown.total') }}
                        </th>
                        <th class="p-3">{{ t('documents.issued_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="cedula in cedulas.data"
                        :key="cedula.id"
                        class="border-t"
                    >
                        <td v-if="canAct" class="p-3">
                            <CheckboxField
                                v-if="cedula.is_pending"
                                :model-value="selected.includes(cedula.id)"
                                label=""
                                @update:model-value="
                                    (checked: boolean) =>
                                        toggle(cedula.id, checked)
                                "
                            />
                        </td>
                        <td class="p-3 font-mono">
                            <Link
                                :href="show(cedula.id)"
                                class="hover:underline"
                                >{{ cedula.cedula_number }}</Link
                            >
                            <div class="text-muted-foreground">
                                {{ cedula.form_number ?? '' }}
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium">
                                {{ cedula.resident?.full_name }}
                            </div>
                            <div class="text-muted-foreground">
                                {{ cedula.resident?.resident_number }}
                            </div>
                        </td>
                        <td class="p-3">
                            <DocumentStatusBadge
                                :status="cedula.status"
                                :label="cedula.status_label"
                                :reason="cedula.cancel_reason"
                            />
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ cedula.amount }}
                        </td>
                        <td class="p-3">
                            {{
                                new Date(cedula.issued_at).toLocaleDateString()
                            }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t bg-muted/30 font-medium">
                    <tr>
                        <td :colspan="canAct ? 4 : 3" class="p-3 text-right">
                            {{ t('documents.totals') }}
                        </td>
                        <td class="p-3 text-right tabular-nums">{{ total }}</td>
                        <td />
                    </tr>
                </tfoot>
            </table>
        </div>

        <Pagination :meta="cedulas.meta" />
    </div>
</template>
