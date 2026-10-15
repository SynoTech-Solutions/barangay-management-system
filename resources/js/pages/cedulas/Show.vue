<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import CedulaBreakdownTable from '@/components/documents/CedulaBreakdownTable.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import ResidentFacts from '@/components/residents/ResidentFacts.vue';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { index, print } from '@/routes/cedulas';
import type { Cedula } from '@/types';

const props = defineProps<{ cedula: Cedula }>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.cedulas'), href: index() }] },
});

const { can } = useAccess();

const facts = computed<Array<[string, string | number | null]>>(() => [
    [t('cedulas.taxpayer'), props.cedula.resident?.full_name ?? null],
    [t('cedulas.form_number'), props.cedula.form_number],
    [t('cedulas.tax_year'), props.cedula.tax_year],
    [t('cedulas.place_of_issue'), props.cedula.place_of_issue],
    [
        t('documents.issued_at'),
        new Date(props.cedula.issued_at).toLocaleString(),
    ],
    [t('cedulas.collector'), props.cedula.certified_by_name],
]);
</script>

<template>
    <Head :title="cedula.cedula_number" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid gap-2">
                <Heading
                    :title="cedula.cedula_number"
                    :description="t('cedulas.show_description')"
                />
                <DocumentStatusBadge
                    :status="cedula.status"
                    :label="cedula.status_label"
                    :reason="cedula.cancel_reason"
                />
            </div>
            <Button
                v-if="
                    can(Permission.CedulaIssue) && cedula.status !== 'cancelled'
                "
                variant="outline"
                as-child
            >
                <a :href="print.url(cedula.id)" target="_blank">{{
                    t('documents.print')
                }}</a>
            </Button>
        </div>

        <ResidentFacts :facts="facts" />
        <section class="rounded-lg border p-3">
            <Heading variant="small" :title="t('cedulas.computation')" />
            <CedulaBreakdownTable :breakdown="cedula.breakdown" />
        </section>
    </div>
</template>
