<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PaymentSummary from '@/components/documents/PaymentSummary.vue';
import PrintSheet from '@/components/documents/PrintSheet.vue';
import SignatureBlock from '@/components/documents/SignatureBlock.vue';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import { show as photo } from '@/routes/residents/photo';
import type { Certifier, DocumentPayment, PrintHeader } from '@/types';

const props = defineProps<{
    document: DocumentPayment & {
        id: number;
        clearance_number: string;
        control_number: string;
        issued_on: string;
        expires_on: string;
    };
    business: {
        business_number: string;
        name: string;
        address: string | null;
        nature: string | null;
        ownership_type: string | null;
    };
    owner: {
        name: string;
        address: string | null;
        resident_id: number | null;
        has_photo: boolean;
        photo_version: number | null;
    };
    header: PrintHeader;
    certifier: Certifier;
}>();

const CONDITIONS = [1, 2, 3, 4];

const { feature } = useAccess();

const photoUrl = computed(() =>
    props.owner.resident_id !== null &&
    props.owner.has_photo &&
    feature(FeatureFlag.ResidentPhoto)
        ? photo.url(props.owner.resident_id, {
              query: { v: props.owner.photo_version ?? 0 },
          })
        : null,
);

const facts = computed<Array<[string, string | null]>>(() => [
    [t('business_clearances.print.owner'), props.owner.name],
    [t('business_clearances.print.owner_address'), props.owner.address],
    [t('business_clearances.print.business_name'), props.business.name],
    [t('business_clearances.print.business_address'), props.business.address],
    [t('business_clearances.print.nature'), props.business.nature],
    [t('business_clearances.print.ownership'), props.business.ownership_type],
    [
        t('business_clearances.print.business_number'),
        props.business.business_number,
    ],
    [
        t('business_clearances.print.issued_on'),
        longDate(props.document.issued_on),
    ],
    [
        t('business_clearances.print.valid_until'),
        longDate(props.document.expires_on),
    ],
]);
</script>

<template>
    <Head
        :title="`${t('business_clearances.print.title')} ${document.clearance_number}`"
    />

    <PrintSheet
        :header="header"
        :print-count="document.print_count"
        :is-reprint="document.is_reprint"
    >
        <p class="text-center font-semibold uppercase">
            {{
                t('documents.office_of', {
                    office:
                        certifier.title ?? t('documents.default_captain_title'),
                })
            }}
        </p>
        <h1 class="text-center text-[18pt] font-bold tracking-wide uppercase">
            {{ t('business_clearances.print.title') }}
        </h1>

        <div class="flex gap-6">
            <div class="flex-1 space-y-3">
                <p class="indent-8">
                    {{ t('business_clearances.print.certify') }}
                </p>
                <dl class="grid grid-cols-[11rem_1fr] gap-x-3 gap-y-1">
                    <template v-for="[label, value] in facts" :key="label">
                        <dt class="text-[9pt] uppercase">{{ label }}</dt>
                        <dd class="font-semibold">{{ value ?? '' }}</dd>
                    </template>
                </dl>
                <ol class="list-decimal space-y-1 pl-6 text-[10pt]">
                    <li v-for="number in CONDITIONS" :key="number">
                        {{ t(`business_clearances.print.condition_${number}`) }}
                    </li>
                </ol>
            </div>
            <div class="flex w-[40mm] flex-col items-center gap-3">
                <img
                    v-if="photoUrl"
                    :src="photoUrl"
                    alt=""
                    class="h-[40mm] w-[40mm] border object-cover object-top"
                />
                <img
                    v-if="document.qr"
                    :src="document.qr"
                    alt=""
                    class="h-[25mm] w-[25mm]"
                />
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-10">
            <PaymentSummary :document="document" />
            <SignatureBlock
                :caption="t('business_clearances.print.approved')"
                :name="certifier.name"
                :title="certifier.title"
                :signature="certifier.signature"
            />
        </div>

        <p class="mt-auto text-center text-[8pt] italic">
            {{ t('documents.dry_seal') }}
        </p>
    </PrintSheet>
</template>
