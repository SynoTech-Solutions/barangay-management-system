<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import CedulaBreakdownTable from '@/components/documents/CedulaBreakdownTable.vue';
import PrintSheet from '@/components/documents/PrintSheet.vue';
import SignatureBlock from '@/components/documents/SignatureBlock.vue';
import { t } from '@/composables/useI18n';
import { longDate, personName } from '@/lib/format';
import type { CommunityTaxBreakdown, PrintHeader } from '@/types';

const props = defineProps<{
    document: {
        id: number;
        cedula_number: string;
        form_number: string | null;
        tax_year: number;
        place_of_issue: string;
        issued_on: string;
        breakdown: CommunityTaxBreakdown;
        print_count: number;
        is_reprint: boolean;
    };
    resident: {
        first_name: string;
        middle_name: string | null;
        last_name: string;
        name_extension: string | null;
        birthdate: string | null;
        birthplace: string | null;
        gender: string | null;
        civil_status: string | null;
        nationality: string | null;
        address: string;
    };
    header: PrintHeader;
    collector: { name: string | null; title: string | null };
}>();

const facts = computed<Array<[string, string | number | null]>>(() => [
    [t('cedulas.number'), props.document.cedula_number],
    [t('cedulas.form_number'), props.document.form_number],
    [t('cedulas.tax_year'), props.document.tax_year],
    [t('cedulas.place_of_issue'), props.document.place_of_issue],
    [t('cedulas.date_issued'), longDate(props.document.issued_on)],
    [
        t('clearances.print.name'),
        personName([
            props.resident.last_name + ',',
            props.resident.first_name,
            props.resident.middle_name,
            props.resident.name_extension,
        ]),
    ],
    [t('clearances.print.address'), props.resident.address],
    [t('clearances.print.civil_status'), props.resident.civil_status],
    [t('clearances.print.sex'), props.resident.gender],
    [t('clearances.print.birthdate'), longDate(props.resident.birthdate)],
    [t('clearances.print.birthplace'), props.resident.birthplace],
    [t('cedulas.citizenship'), props.resident.nationality],
]);
</script>

<template>
    <Head :title="`${t('cedulas.print_title')} ${document.cedula_number}`" />

    <PrintSheet
        :header="header"
        :print-count="document.print_count"
        :is-reprint="document.is_reprint"
    >
        <h1 class="text-center text-[16pt] font-bold uppercase">
            {{ t('cedulas.print_title') }}
        </h1>
        <p class="text-center text-[9pt] italic">
            {{ t('cedulas.print_notice') }}
        </p>
        <dl class="grid grid-cols-[12rem_1fr] gap-x-3 gap-y-1">
            <template v-for="[label, value] in facts" :key="label">
                <dt class="text-[9pt] uppercase">{{ label }}</dt>
                <dd class="font-semibold">{{ value ?? '' }}</dd>
            </template>
        </dl>
        <CedulaBreakdownTable :breakdown="document.breakdown" />
        <div class="mt-8 grid grid-cols-2 gap-10">
            <SignatureBlock
                :caption="t('cedulas.taxpayer_signature')"
                :name="null"
                :title="null"
            />
            <SignatureBlock
                :caption="t('cedulas.collector')"
                :name="collector.name"
                :title="collector.title"
            />
        </div>
    </PrintSheet>
</template>
