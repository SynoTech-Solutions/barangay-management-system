<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PrintSheet from '@/components/documents/PrintSheet.vue';
import SignatureBlock from '@/components/documents/SignatureBlock.vue';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { longDate, personName } from '@/lib/format';
import { show as photo } from '@/routes/residents/photo';
import type { Certifier, PrintHeader } from '@/types';

const props = defineProps<{
    document: {
        id: number;
        certificate_number: string;
        purpose: string | null;
        issued_on: string;
        print_count: number;
        is_reprint: boolean;
        qr: string | null;
    };
    resident: {
        resident_id: number;
        first_name: string;
        middle_name: string | null;
        last_name: string;
        name_extension: string | null;
        address: string;
        has_photo: boolean;
        photo_version: number | null;
    };
    header: PrintHeader;
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

const photoUrl = computed(() =>
    props.resident.has_photo && feature(FeatureFlag.ResidentPhoto)
        ? photo.url(props.resident.resident_id, {
              query: { v: props.resident.photo_version ?? 0 },
          })
        : null,
);

const place = computed(() =>
    [props.header.barangay, props.header.municipality, props.header.province]
        .filter((part) => part)
        .join(', '),
);
</script>

<template>
    <Head
        :title="`${t('indigency.print_title')} ${document.certificate_number}`"
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
            {{ t('indigency.print_title') }}
        </h1>
        <div class="flex gap-6">
            <div class="flex-1 space-y-4">
                <p>{{ t('clearances.print.to_whom') }}</p>
                <p class="indent-8">
                    {{
                        t('indigency.print_body', {
                            name: fullName,
                            address: resident.address,
                        })
                    }}
                </p>
                <p class="indent-8">
                    {{
                        t('indigency.print_purpose', {
                            purpose: document.purpose ?? '',
                        })
                    }}
                </p>
                <p class="indent-8">
                    {{
                        t('documents.issued_line', {
                            date: longDate(document.issued_on),
                            place,
                        })
                    }}
                </p>
                <p class="text-[9pt]">
                    {{ t('documents.certificate_number') }}
                    <span class="font-mono">{{
                        document.certificate_number
                    }}</span>
                </p>
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
        <div class="mt-10 grid grid-cols-2 gap-10">
            <div />
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
