<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PaymentSummary from '@/components/documents/PaymentSummary.vue';
import PrintSheet from '@/components/documents/PrintSheet.vue';
import SignatureBlock from '@/components/documents/SignatureBlock.vue';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { longDate, personName } from '@/lib/format';
import { show as photo } from '@/routes/residents/photo';
import type { Certifier, DocumentPayment, PrintHeader } from '@/types';

const props = defineProps<{
    document: DocumentPayment & {
        id: number;
        clearance_number: string;
        control_number: string;
        type: string | null;
        purpose: string | null;
        issued_on: string;
        expires_on: string;
    };
    resident: {
        resident_id: number;
        first_name: string;
        middle_name: string | null;
        last_name: string;
        name_extension: string | null;
        birthdate: string | null;
        birthplace: string | null;
        gender: string | null;
        civil_status: string | null;
        address: string;
        has_photo: boolean;
        photo_version: number | null;
    };
    header: PrintHeader;
    verifier: { name: string; title: string | null } | null;
    certifier: Certifier;
}>();

const { feature } = useAccess();

const fullName = computed(() =>
    personName([
        props.resident.first_name,
        props.resident.middle_name,
        props.resident.last_name,
        props.resident.name_extension,
    ]),
);

const title = computed(
    () => props.document.type ?? t('clearances.default_title'),
);

const photoUrl = computed(() =>
    props.resident.has_photo && feature(FeatureFlag.ResidentPhoto)
        ? photo.url(props.resident.resident_id, {
              query: { v: props.resident.photo_version ?? 0 },
          })
        : null,
);

const facts = computed<Array<[string, string | null]>>(() => [
    [t('clearances.print.name'), fullName.value],
    [t('clearances.print.address'), props.resident.address],
    [t('clearances.print.civil_status'), props.resident.civil_status],
    [t('clearances.print.sex'), props.resident.gender],
    [t('clearances.print.birthdate'), longDate(props.resident.birthdate)],
    [t('clearances.print.birthplace'), props.resident.birthplace],
    [t('clearances.print.purpose'), props.document.purpose],
]);

const place = computed(() =>
    [props.header.barangay, props.header.municipality, props.header.province]
        .filter((part) => part)
        .join(', '),
);
</script>

<template>
    <Head :title="`${title} ${document.clearance_number}`" />

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
            {{ title }}
        </h1>

        <div class="flex gap-6">
            <div class="flex-1 space-y-3">
                <p>{{ t('clearances.print.to_whom') }}</p>
                <p class="indent-8">
                    {{ t('clearances.print.certify', { type: title }) }}
                </p>
                <dl class="grid grid-cols-[10rem_1fr] gap-x-3 gap-y-1">
                    <template v-for="[label, value] in facts" :key="label">
                        <dt class="text-[9pt] uppercase">{{ label }}</dt>
                        <dd class="font-semibold">{{ value ?? '' }}</dd>
                    </template>
                </dl>
                <p class="indent-8">{{ t('clearances.print.statement') }}</p>
                <p class="indent-8">
                    {{
                        t('documents.issued_line', {
                            date: longDate(document.issued_on),
                            place,
                        })
                    }}
                </p>
                <p class="font-semibold">
                    {{
                        t('documents.valid_until_line', {
                            date: longDate(document.expires_on),
                        })
                    }}
                </p>
            </div>
            <div class="flex w-[40mm] flex-col items-center gap-3">
                <img
                    v-if="photoUrl"
                    :src="photoUrl"
                    alt=""
                    class="h-[40mm] w-[40mm] border object-cover object-top"
                />
                <div
                    v-else
                    class="flex h-[40mm] w-[40mm] items-center justify-center border text-[8pt]"
                >
                    {{ t('print.no_photo') }}
                </div>
                <img
                    v-if="document.qr"
                    :src="document.qr"
                    alt=""
                    class="h-[25mm] w-[25mm]"
                />
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-10">
            <SignatureBlock
                :caption="t('documents.applicant_signature')"
                :name="fullName"
                :title="null"
            />
            <SignatureBlock
                v-if="verifier"
                :caption="t('documents.verified_by')"
                :name="verifier.name"
                :title="verifier.title"
            />
            <div v-else />
            <PaymentSummary :document="document" />
            <SignatureBlock
                :caption="t('documents.certified_by')"
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
