<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { getJson } from '@/lib/http';
import { references } from '@/routes/cedulas';
import type { CedulaReference } from '@/types';

const props = withDefaults(
    defineProps<{
        errors: Record<string, string | undefined>;
        showCtc?: boolean;
        showDocStamp?: boolean;
        residentId?: number | null;
    }>(),
    { showCtc: true, showDocStamp: false, residentId: null },
);

const orNumber = defineModel<string>('orNumber', { required: true });
const amount = defineModel<string>('amount', { required: true });
const docStamp = defineModel<string>('docStamp', { default: '0.00' });
const ctcNumber = defineModel<string>('ctcNumber', { required: true });
const ctcPlace = defineModel<string>('ctcPlace', { required: true });
const ctcDate = defineModel<string>('ctcDate', { required: true });

const { can, feature } = useAccess();
const cedulas = ref<CedulaReference[]>([]);

watch(
    () => props.residentId,
    async (residentId) => {
        cedulas.value = [];

        if (
            residentId === null ||
            !props.showCtc ||
            !feature(FeatureFlag.Cedula) ||
            !can(Permission.CedulaView)
        ) {
            return;
        }

        const result = await getJson<{ data: CedulaReference[] }>(
            references.url({ query: { resident_id: residentId } }),
        );
        cedulas.value = result?.data ?? [];
    },
    { immediate: true },
);

function useCedula(event: Event): void {
    const chosen = cedulas.value.find(
        (cedula) =>
            String(cedula.id) === (event.target as HTMLSelectElement).value,
    );

    if (chosen) {
        ctcNumber.value = chosen.ctc_number;
        ctcPlace.value = chosen.ctc_place;
        ctcDate.value = chosen.ctc_date;
    }
}
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3">
        <template v-if="showCtc">
            <div v-if="cedulas.length" class="grid gap-2 md:col-span-3">
                <Label for="cedula_reference">{{
                    t('documents.use_cedula')
                }}</Label>
                <select
                    id="cedula_reference"
                    class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                    @change="useCedula"
                >
                    <option value="">{{ t('documents.type_cedula') }}</option>
                    <option
                        v-for="cedula in cedulas"
                        :key="cedula.id"
                        :value="cedula.id"
                    >
                        {{ cedula.ctc_number }} · {{ cedula.ctc_date }}
                    </option>
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="ctc_number">{{ t('documents.ctc_number') }}</Label>
                <Input id="ctc_number" v-model="ctcNumber" />
                <InputError :message="errors.ctc_number" />
            </div>
            <div class="grid gap-2">
                <Label for="ctc_place">{{ t('documents.ctc_place') }}</Label>
                <Input id="ctc_place" v-model="ctcPlace" />
                <InputError :message="errors.ctc_place" />
            </div>
            <div class="grid gap-2">
                <Label for="ctc_date">{{ t('documents.ctc_date') }}</Label>
                <Input id="ctc_date" v-model="ctcDate" type="date" />
                <InputError :message="errors.ctc_date" />
            </div>
        </template>
        <div class="grid gap-2">
            <Label for="or_number">{{ t('documents.or_number') }}</Label>
            <Input id="or_number" v-model="orNumber" />
            <InputError :message="errors.or_number" />
        </div>
        <div class="grid gap-2">
            <Label for="amount">{{ t('documents.amount') }}</Label>
            <Input
                id="amount"
                v-model="amount"
                type="number"
                step="0.01"
                min="0"
            />
            <InputError :message="errors.amount" />
        </div>
        <div v-if="showDocStamp" class="grid gap-2">
            <Label for="doc_stamp">{{ t('documents.doc_stamp') }}</Label>
            <Input
                id="doc_stamp"
                v-model="docStamp"
                type="number"
                step="0.01"
                min="0"
            />
            <InputError :message="errors.doc_stamp" />
        </div>
    </div>
</template>
