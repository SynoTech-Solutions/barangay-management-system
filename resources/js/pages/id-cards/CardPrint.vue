<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { show as photo } from '@/routes/residents/photo';
import type { SignatoryPrint } from '@/types';

const props = defineProps<{
    card: {
        id: number;
        card_number: string;
        issued_on: string;
        valid_until: string | null;
        print_count: number;
    };
    resident: {
        resident_id: number;
        resident_number: string;
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
        has_photo: boolean;
        photo_version: number | null;
        qr: string;
    };
    header: {
        barangay: string;
        municipality: string;
        province: string | null;
        telephone: string | null;
        ordinance_no: string | null;
    };
    captain: SignatoryPrint;
    mayor: SignatoryPrint;
    layout: { pvc: boolean };
}>();

const { feature } = useAccess();

const fullName = computed(() =>
    [
        props.resident.first_name,
        props.resident.middle_name,
        props.resident.last_name,
        props.resident.name_extension,
    ]
        .filter((part) => part)
        .join(' '),
);

const photoUrl = computed(() =>
    props.resident.has_photo && feature(FeatureFlag.ResidentPhoto)
        ? photo.url(props.resident.resident_id, {
              query: { v: props.resident.photo_version ?? 0 },
          })
        : null,
);

const facts = computed<Array<[string, string | null]>>(() => [
    [t('id_cards.fields.address'), props.resident.address],
    [t('id_cards.fields.birthdate'), props.resident.birthdate],
    [t('id_cards.fields.birthplace'), props.resident.birthplace],
    [t('id_cards.fields.gender'), props.resident.gender],
    [t('id_cards.fields.civil_status'), props.resident.civil_status],
    [t('id_cards.fields.nationality'), props.resident.nationality],
]);

function printPage(): void {
    window.print();
}
</script>

<template>
    <Head :title="`${t('id_cards.card_title')} ${card.card_number}`" />

    <div class="bg-white p-6 text-black print:p-0">
        <div class="mb-4 flex gap-2 print:hidden">
            <Button @click="printPage">{{ t('id_cards.print') }}</Button>
            <span class="self-center text-sm text-muted-foreground">{{
                t('id_cards.prints', { count: card.print_count })
            }}</span>
        </div>

        <div :class="layout.pvc ? 'sheet-pvc' : 'sheet-paper'">
            <section class="id-card front">
                <header class="text-center leading-tight">
                    <p class="text-[6pt]">{{ t('print.republic') }}</p>
                    <p v-if="header.province" class="text-[6pt]">
                        {{ header.province }}
                    </p>
                    <p class="text-[6pt]">{{ header.municipality }}</p>
                    <p class="text-[7pt] font-semibold">
                        {{ header.barangay }}
                    </p>
                    <p class="text-[7pt] font-bold uppercase">
                        {{ t('id_cards.card_title') }}
                    </p>
                    <p v-if="header.ordinance_no" class="text-[5pt]">
                        {{
                            t('id_cards.ordinance', {
                                number: header.ordinance_no,
                            })
                        }}
                    </p>
                </header>
                <div class="mt-1 flex gap-2">
                    <div class="flex w-[20mm] flex-col items-center gap-1">
                        <img
                            v-if="photoUrl"
                            :src="photoUrl"
                            alt=""
                            class="h-[20mm] w-[20mm] border object-cover object-top"
                        />
                        <div v-else class="h-[20mm] w-[20mm] border" />
                        <img
                            :src="resident.qr"
                            alt=""
                            class="h-[12mm] w-[12mm]"
                        />
                    </div>
                    <div class="flex-1 text-[6pt] leading-tight">
                        <p
                            class="font-bold uppercase"
                            :class="
                                fullName.length > 30
                                    ? 'text-[6.5pt]'
                                    : 'text-[7.5pt]'
                            "
                        >
                            {{ fullName }}
                        </p>
                        <p v-for="[label, value] in facts" :key="label">
                            <span class="text-[5pt] uppercase"
                                >{{ label }}:</span
                            >
                            {{ value ?? '' }}
                        </p>
                        <p class="font-semibold">
                            {{ t('id_cards.fields.resident_number') }}
                            {{ resident.resident_number }}
                        </p>
                        <p>
                            {{ t('id_cards.card_number') }}
                            {{ card.card_number }}
                        </p>
                        <p v-if="card.valid_until" class="font-semibold">
                            {{
                                t('id_cards.valid_until', {
                                    date: card.valid_until,
                                })
                            }}
                        </p>
                    </div>
                </div>
                <footer
                    class="mt-1 flex justify-between gap-2 text-center text-[5pt]"
                >
                    <div class="flex-1">
                        <img
                            v-if="captain?.signature"
                            :src="captain.signature"
                            alt=""
                            class="mx-auto h-[5mm]"
                        />
                        <p class="border-t font-semibold uppercase">
                            {{ captain?.name ?? '' }}
                        </p>
                        <p>
                            {{
                                captain?.title ??
                                t('id_cards.default_captain_title')
                            }}
                        </p>
                    </div>
                    <div class="flex-1 self-end">
                        <p class="border-t">
                            {{ t('id_cards.bearer_signature') }}
                        </p>
                    </div>
                </footer>
            </section>

            <section class="id-card back text-center text-[6pt] leading-snug">
                <p class="mt-2">{{ t('id_cards.back_statement') }}</p>
                <p class="mt-2">
                    {{
                        t('id_cards.back_return', { barangay: header.barangay })
                    }}
                    <span v-if="header.telephone">{{
                        t('id_cards.back_call', { telephone: header.telephone })
                    }}</span>
                </p>
                <div v-if="mayor" class="mt-3">
                    <img
                        v-if="mayor.signature"
                        :src="mayor.signature"
                        alt=""
                        class="mx-auto h-[5mm]"
                    />
                    <p class="font-semibold uppercase">{{ mayor.name }}</p>
                    <p>{{ mayor.title ?? '' }}</p>
                </div>
                <p class="mt-2 font-mono">{{ resident.resident_number }}</p>
            </section>
        </div>
    </div>
</template>

<style>
.id-card {
    box-sizing: border-box;
    width: 85.6mm;
    height: 53.98mm;
    overflow: hidden;
    padding: 2mm 3mm;
    border: 0.2mm solid #999;
    background: #fff;
}

.sheet-paper {
    display: flex;
    flex-direction: column;
    gap: 6mm;
    padding: 13mm;
}

.sheet-pvc {
    display: flex;
    flex-direction: column;
    gap: 6mm;
}

@page paper-sheet {
    size: A4 portrait;
    margin: 0;
}

@page pvc-card {
    size: 85.6mm 53.98mm;
    margin: 0;
}

@media print {
    .sheet-paper {
        page: paper-sheet;
    }

    .sheet-pvc {
        gap: 0;
    }

    .sheet-pvc .id-card {
        page: pvc-card;
        border: 0;
        break-after: page;
    }
}
</style>
