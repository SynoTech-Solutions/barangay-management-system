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
    index,
    print,
    release,
} from '@/routes/clearances';
import type {
    BarangayClearance,
    DocumentListFilters,
    Paginated,
    PaymentTotals,
    SelectOption,
} from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.clearances'), href: index() }] },
});

const props = defineProps<{
    clearances: Paginated<BarangayClearance>;
    totals: PaymentTotals;
    filters: DocumentListFilters;
    statuses: SelectOption<string>[];
    lookups: Record<string, SelectOption[]>;
}>();

const { can, feature } = useAccess();
const state = reactive({
    search: props.filters.search,
    status: props.filters.status,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    clearance_type_id: props.filters.clearance_type_id,
    purpose_id: props.filters.purpose_id,
});
const selected = ref<number[]>([]);

const canAct = computed(
    () => can(Permission.ClearancesRelease) || can(Permission.ClearancesCancel),
);
const showDocStamp = computed(() => feature(FeatureFlag.ClearanceDocStamp));

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
    <Head :title="t('clearances.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('clearances.title')"
                :description="t('clearances.description')"
            />
            <Button v-if="can(Permission.ClearancesIssue)" as-child>
                <Link :href="create()">{{ t('clearances.new') }}</Link>
            </Button>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-3">
            <Input
                v-model="state.search"
                :placeholder="t('clearances.search_placeholder')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('documents.all_statuses')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.clearance_type_id"
                :options="lookups['clearance-type'] ?? []"
                :placeholder="t('clearances.all_types')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.purpose_id"
                :options="lookups['clearance-purpose'] ?? []"
                :placeholder="t('clearances.all_purposes')"
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
            :can-release="can(Permission.ClearancesRelease)"
            :can-cancel="can(Permission.ClearancesCancel)"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th v-if="canAct" class="w-8 p-3" />
                        <th class="p-3">{{ t('clearances.number') }}</th>
                        <th class="p-3">{{ t('clearances.resident') }}</th>
                        <th class="p-3">{{ t('clearances.type') }}</th>
                        <th class="p-3">{{ t('documents.status') }}</th>
                        <th class="p-3">{{ t('documents.or_number') }}</th>
                        <th class="p-3 text-right">
                            {{ t('documents.amount') }}
                        </th>
                        <th v-if="showDocStamp" class="p-3 text-right">
                            {{ t('documents.doc_stamp') }}
                        </th>
                        <th class="p-3">{{ t('documents.issued_at') }}</th>
                        <th class="p-3 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="clearance in clearances.data"
                        :key="clearance.id"
                        class="border-t"
                    >
                        <td v-if="canAct" class="p-3">
                            <CheckboxField
                                v-if="clearance.is_pending"
                                :model-value="selected.includes(clearance.id)"
                                label=""
                                @update:model-value="
                                    (checked: boolean) =>
                                        toggle(clearance.id, checked)
                                "
                            />
                        </td>
                        <td class="p-3 font-mono">
                            {{ clearance.clearance_number }}
                            <div class="text-muted-foreground">
                                {{ clearance.control_number }}
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium">
                                {{ clearance.resident?.full_name }}
                            </div>
                            <div class="text-muted-foreground">
                                {{ clearance.resident?.resident_number }}
                            </div>
                        </td>
                        <td class="p-3">
                            {{ clearance.clearance_type }}
                            <div class="text-muted-foreground">
                                {{ clearance.purpose }}
                            </div>
                        </td>
                        <td class="p-3">
                            <DocumentStatusBadge
                                :status="clearance.status"
                                :label="clearance.status_label"
                                :reason="clearance.cancel_reason"
                            />
                        </td>
                        <td class="p-3">{{ clearance.or_number ?? '' }}</td>
                        <td class="p-3 text-right tabular-nums">
                            {{ clearance.amount }}
                        </td>
                        <td
                            v-if="showDocStamp"
                            class="p-3 text-right tabular-nums"
                        >
                            {{ clearance.doc_stamp }}
                        </td>
                        <td class="p-3">
                            {{
                                new Date(
                                    clearance.issued_at,
                                ).toLocaleDateString()
                            }}
                            <div class="text-muted-foreground">
                                {{
                                    t('documents.valid_until', {
                                        date: clearance.expires_on,
                                    })
                                }}
                            </div>
                        </td>
                        <td class="p-3 text-right">
                            <div
                                v-if="can(Permission.ClearancesIssue)"
                                class="flex justify-end gap-2"
                            >
                                <Button
                                    v-if="clearance.is_pending"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(clearance.id)">{{
                                        t('common.edit')
                                    }}</Link>
                                </Button>
                                <Button
                                    v-if="clearance.status !== 'cancelled'"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <a
                                        :href="print.url(clearance.id)"
                                        target="_blank"
                                        >{{ t('documents.print') }}</a
                                    >
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t bg-muted/30 font-medium">
                    <tr>
                        <td :colspan="canAct ? 6 : 5" class="p-3 text-right">
                            {{ t('documents.totals') }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ totals.amount }}
                        </td>
                        <td
                            v-if="showDocStamp"
                            class="p-3 text-right tabular-nums"
                        >
                            {{ totals.doc_stamp }}
                        </td>
                        <td colspan="2" class="p-3">
                            {{
                                showDocStamp
                                    ? t('documents.grand_total', {
                                          total: totals.total,
                                      })
                                    : ''
                            }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <Pagination :meta="clearances.meta" />
    </div>
</template>
