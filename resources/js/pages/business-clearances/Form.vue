<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import BusinessPicker from '@/components/documents/BusinessPicker.vue';
import PaymentFields from '@/components/documents/PaymentFields.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { index, store, update } from '@/routes/business-clearances';
import type { BusinessClearance, BusinessOption } from '@/types';

const props = defineProps<{
    clearance: BusinessClearance | null;
    business: BusinessOption | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.business_clearances'), href: index() }],
    },
});

const { feature } = useAccess();
const clearance = props.clearance;
const business = ref<BusinessOption | null>(props.business);
const form = useForm({
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
            ? { business_id: business.value?.id ?? null }
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
                ? t('business_clearances.edit_title')
                : t('business_clearances.create_title')
        "
    />

    <form class="flex max-w-3xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                clearance
                    ? t('business_clearances.edit_title')
                    : t('business_clearances.create_title')
            "
            :description="
                clearance
                    ? `${clearance.clearance_number} · ${clearance.control_number}`
                    : t('business_clearances.create_help')
            "
        />

        <div class="grid gap-2">
            <Label for="business">{{ t('businesses.name') }}</Label>
            <BusinessPicker
                v-if="clearance === null"
                id="business"
                v-model="business"
            />
            <p v-else class="text-sm font-medium">
                {{ clearance.business_name }} · {{ clearance.owner_name }}
            </p>
            <InputError :message="errors.business_id" />
        </div>

        <PaymentFields
            v-model:or-number="form.or_number"
            v-model:amount="form.amount"
            v-model:doc-stamp="form.doc_stamp"
            v-model:ctc-number="form.ctc_number"
            v-model:ctc-place="form.ctc_place"
            v-model:ctc-date="form.ctc_date"
            :errors="errors"
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
