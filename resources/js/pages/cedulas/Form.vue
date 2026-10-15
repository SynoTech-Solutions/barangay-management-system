<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { onMounted, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CedulaBreakdownTable from '@/components/documents/CedulaBreakdownTable.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { getJson } from '@/lib/http';
import { index, quote, store } from '@/routes/cedulas';
import type {
    CedulaTemplate,
    CommunityTaxBreakdown,
    ResidentOption,
} from '@/types';

const QUOTE_DELAY_MS = 300;
const INCOME_FIELDS = [
    'business_gross_receipts',
    'salaries',
    'real_property_income',
] as const;

const props = defineProps<{
    resident: ResidentOption | null;
    templates: CedulaTemplate[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.cedulas'), href: index() }] },
});

const { feature } = useAccess();
const resident = ref<ResidentOption | null>(props.resident);
const breakdown = ref<CommunityTaxBreakdown | null>(null);
const form = useForm({
    form_number: '',
    is_exempt: false,
    business_gross_receipts: '',
    salaries: '',
    real_property_income: '',
});

const errors = form.errors as Record<string, string>;

const refreshQuote = useDebounceFn(async () => {
    const result = await getJson<{ data: CommunityTaxBreakdown }>(
        quote.url({
            query: {
                is_exempt: form.is_exempt ? 1 : 0,
                business_gross_receipts: form.business_gross_receipts || 0,
                salaries: form.salaries || 0,
                real_property_income: form.real_property_income || 0,
            },
        }),
    );
    breakdown.value = result?.data ?? null;
}, QUOTE_DELAY_MS);

watch(
    () => [
        form.is_exempt,
        form.business_gross_receipts,
        form.salaries,
        form.real_property_income,
    ],
    refreshQuote,
);
onMounted(refreshQuote);

function applyTemplate(event: Event): void {
    const template = props.templates.find(
        (item) => String(item.id) === (event.target as HTMLSelectElement).value,
    );

    if (template) {
        form.is_exempt = template.is_exempt;
        form.business_gross_receipts = template.business_gross_receipts;
        form.salaries = template.salaries;
        form.real_property_income = template.real_property_income;
    }
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        resident_id: resident.value?.id ?? null,
    })).post(store.url());
}
</script>

<template>
    <Head :title="t('cedulas.create_title')" />

    <form class="flex max-w-3xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="t('cedulas.create_title')"
            :description="t('cedulas.create_help')"
        />

        <div class="grid gap-2">
            <Label for="resident">{{ t('cedulas.taxpayer') }}</Label>
            <ResidentPicker id="resident" v-model="resident" allow-quick-add />
            <InputError :message="errors.resident_id" />
        </div>

        <div
            v-if="feature(FeatureFlag.CedulaTemplates) && templates.length"
            class="grid gap-2"
        >
            <Label for="template">{{ t('cedulas.use_template') }}</Label>
            <select
                id="template"
                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                @change="applyTemplate"
            >
                <option value="">{{ t('residents.choose') }}</option>
                <option
                    v-for="template in templates"
                    :key="template.id"
                    :value="template.id"
                >
                    {{ template.name }}
                </option>
            </select>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label for="form_number">{{ t('cedulas.form_number') }}</Label>
                <Input id="form_number" v-model="form.form_number" />
                <InputError :message="form.errors.form_number" />
            </div>
            <div v-for="field in INCOME_FIELDS" :key="field" class="grid gap-2">
                <Label :for="field">{{ t(`cedulas.fields.${field}`) }}</Label>
                <Input
                    :id="field"
                    v-model="form[field]"
                    type="number"
                    step="0.01"
                    min="0"
                />
                <InputError :message="errors[field]" />
            </div>
        </div>

        <CheckboxField
            v-model="form.is_exempt"
            :label="t('cedulas.fields.is_exempt')"
        />

        <section v-if="breakdown" class="rounded-lg border p-3">
            <Heading variant="small" :title="t('cedulas.computation')" />
            <CedulaBreakdownTable :breakdown="breakdown" />
        </section>

        <Button type="submit" :disabled="form.processing" class="w-fit">{{
            form.processing ? t('common.saving') : t('cedulas.issue')
        }}</Button>
    </form>
</template>
