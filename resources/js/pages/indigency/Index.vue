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
import {
    cancel,
    create,
    edit,
    index,
    print,
    release,
} from '@/routes/indigency-certificates';
import type {
    DocumentListFilters,
    IndigencyCertificate,
    Paginated,
    SelectOption,
} from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.indigency'), href: index() }] },
});

const props = defineProps<{
    certificates: Paginated<IndigencyCertificate>;
    filters: DocumentListFilters;
    statuses: SelectOption<string>[];
    lookups: Record<string, SelectOption[]>;
}>();

const { can } = useAccess();
const state = reactive({
    search: props.filters.search,
    status: props.filters.status,
    purpose_id: props.filters.purpose_id,
});
const selected = ref<number[]>([]);

const canAct = computed(
    () => can(Permission.ClearancesRelease) || can(Permission.ClearancesCancel),
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
    <Head :title="t('indigency.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('indigency.title')"
                :description="t('indigency.description')"
            />
            <Button v-if="can(Permission.ClearancesIssue)" as-child>
                <Link :href="create()">{{ t('indigency.new') }}</Link>
            </Button>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-3">
            <Input
                v-model="state.search"
                :placeholder="t('indigency.search_placeholder')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('documents.all_statuses')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.purpose_id"
                :options="lookups['certificate-purpose'] ?? []"
                :placeholder="t('clearances.all_purposes')"
                @update:model-value="apply"
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
                        <th class="p-3">{{ t('clearances.purpose') }}</th>
                        <th class="p-3">{{ t('documents.status') }}</th>
                        <th class="p-3">{{ t('documents.issued_at') }}</th>
                        <th class="p-3 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="certificate in certificates.data"
                        :key="certificate.id"
                        class="border-t"
                    >
                        <td v-if="canAct" class="p-3">
                            <CheckboxField
                                v-if="certificate.is_pending"
                                :model-value="selected.includes(certificate.id)"
                                label=""
                                @update:model-value="
                                    (checked: boolean) =>
                                        toggle(certificate.id, checked)
                                "
                            />
                        </td>
                        <td class="p-3 font-mono">
                            {{ certificate.certificate_number }}
                        </td>
                        <td class="p-3">
                            {{ certificate.resident?.full_name }}
                        </td>
                        <td class="p-3">{{ certificate.purpose }}</td>
                        <td class="p-3">
                            <DocumentStatusBadge
                                :status="certificate.status"
                                :label="certificate.status_label"
                                :reason="certificate.cancel_reason"
                            />
                        </td>
                        <td class="p-3">
                            {{
                                new Date(
                                    certificate.issued_at,
                                ).toLocaleDateString()
                            }}
                        </td>
                        <td class="p-3 text-right">
                            <div
                                v-if="can(Permission.ClearancesIssue)"
                                class="flex justify-end gap-2"
                            >
                                <Button
                                    v-if="certificate.is_pending"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(certificate.id)">{{
                                        t('common.edit')
                                    }}</Link>
                                </Button>
                                <Button
                                    v-if="certificate.status !== 'cancelled'"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <a
                                        :href="print.url(certificate.id)"
                                        target="_blank"
                                        >{{ t('documents.print') }}</a
                                    >
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="certificates.meta" />
    </div>
</template>
