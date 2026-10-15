<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PaymentFields from '@/components/documents/PaymentFields.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { index, store, update } from '@/routes/clearances';
import type { BarangayClearance, ResidentOption, SelectOption } from '@/types';

const props = defineProps<{
    clearance: BarangayClearance | null;
    resident: ResidentOption | null;
    lookups: Record<string, SelectOption[]>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.clearances'), href: index() }] },
});

const { feature } = useAccess();
const clearance = props.clearance;
const resident = ref<ResidentOption | null>(props.resident);
const form = useForm({
    clearance_type_id: clearance?.clearance_type_id ?? null,
    purpose_id: clearance?.purpose_id ?? null,
    ctc_number: clearance?.ctc_number ?? '',
    ctc_place: clearance?.ctc_place ?? '',
    ctc_date: clearance?.ctc_date ?? '',
    or_number: clearance?.or_number ?? '',
    amount: clearance?.amount ?? '0.00',
    doc_stamp: clearance?.doc_stamp ?? '0.00',
});

const errors = form.errors as Record<string, string>;

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        ctc_date: data.ctc_date || null,
        ...(clearance === null
            ? { resident_id: resident.value?.id ?? null }
            : {}),
    }));

    if (clearance === null) {
        request.post(store.url());
    } else {
        request.put(update.url(clearance.id));
    }
}
</script>

<template>
    <Head
        :title="
            clearance
                ? t('clearances.edit_title')
                : t('clearances.create_title')
        "
    />

    <form class="flex max-w-3xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                clearance
                    ? t('clearances.edit_title')
                    : t('clearances.create_title')
            "
            :description="
                clearance
                    ? `${clearance.clearance_number} · ${clearance.control_number}`
                    : t('clearances.create_help')
            "
        />

        <div class="grid gap-2">
            <Label for="resident">{{ t('clearances.resident') }}</Label>
            <ResidentPicker
                v-if="clearance === null"
                id="resident"
                v-model="resident"
                allow-quick-add
                for-issuance
            />
            <p v-else class="text-sm font-medium">
                {{ resident?.full_name }} · {{ resident?.resident_number }}
            </p>
            <InputError :message="errors.resident_id" />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="clearance_type_id">{{
                    t('clearances.type')
                }}</Label>
                <OptionSelect
                    id="clearance_type_id"
                    v-model="form.clearance_type_id"
                    :options="lookups['clearance-type'] ?? []"
                    required
                />
                <InputError :message="form.errors.clearance_type_id" />
            </div>
            <div class="grid gap-2">
                <Label for="purpose_id">{{ t('clearances.purpose') }}</Label>
                <OptionSelect
                    id="purpose_id"
                    v-model="form.purpose_id"
                    :options="lookups['clearance-purpose'] ?? []"
                    required
                />
                <InputError :message="form.errors.purpose_id" />
            </div>
        </div>

        <PaymentFields
            v-model:or-number="form.or_number"
            v-model:amount="form.amount"
            v-model:doc-stamp="form.doc_stamp"
            v-model:ctc-number="form.ctc_number"
            v-model:ctc-place="form.ctc_place"
            v-model:ctc-date="form.ctc_date"
            :errors="errors"
            :resident-id="resident?.id ?? null"
            :show-ctc="feature(FeatureFlag.ClearanceCtcReference)"
            :show-doc-stamp="feature(FeatureFlag.ClearanceDocStamp)"
        />

        <p class="text-sm text-muted-foreground">
            {{
                clearance
                    ? t('documents.valid_until', { date: clearance.expires_on })
                    : t('clearances.validity_help')
            }}
        </p>

        <Button type="submit" :disabled="form.processing" class="w-fit">{{
            form.processing ? t('common.saving') : t('common.save')
        }}</Button>
    </form>
</template>
