<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import FieldValueInput from '@/components/forms/FieldValueInput.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { index, store, update } from '@/routes/forms/transactions';
import type {
    FormFieldValue,
    FormTransactionEditor,
    IssuableField,
    IssuableTemplate,
    ResidentOption,
} from '@/types';

const props = defineProps<{
    transaction: FormTransactionEditor | null;
    values: Record<string, FormFieldValue>;
    templates: IssuableTemplate[];
    resident: ResidentOption | null;
    selectedTemplateId: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.form_transactions'), href: index() }],
    },
});

const transaction = props.transaction;
const resident = ref<ResidentOption | null>(props.resident);
const form = useForm<{
    form_template_id: number | null;
    or_number: string;
    amount: string;
    values: Record<string, FormFieldValue>;
}>({
    form_template_id: props.selectedTemplateId,
    or_number: transaction?.or_number ?? '',
    amount: transaction?.amount ?? '0.00',
    values: { ...props.values },
});

const errors = form.errors as Record<string, string | undefined>;

const template = computed<IssuableTemplate | null>(
    () =>
        props.templates.find(
            (candidate) => candidate.id === form.form_template_id,
        ) ?? null,
);

const fields = computed<IssuableField[]>(
    () => transaction?.fields ?? template.value?.fields ?? [],
);

const residentLabels = computed<Record<string, string>>(() =>
    Object.fromEntries(
        (transaction?.snapshot_fields ?? [])
            .filter((field) => field.input_type === 'resident')
            .map((field) => [field.key, field.display]),
    ),
);

const groupedTemplates = computed(() => {
    const groups = new Map<string, IssuableTemplate[]>();

    for (const candidate of props.templates) {
        const key = candidate.type ?? '';
        groups.set(key, [...(groups.get(key) ?? []), candidate]);
    }

    return [...groups.entries()];
});

watch(
    () => form.form_template_id,
    () => {
        if (transaction === null) {
            form.values = {};
        }
    },
);

function submit(): void {
    if (transaction === null) {
        form.transform((data) => ({
            ...data,
            resident_id: resident.value?.id ?? null,
        })).post(store.url());

        return;
    }

    form.transform((data) => ({
        or_number: data.or_number,
        amount: data.amount,
        values: data.values,
    })).put(update.url(transaction.id));
}
</script>

<template>
    <Head
        :title="
            transaction
                ? t('forms.transactions.edit')
                : t('forms.transactions.new')
        "
    />

    <form class="flex max-w-3xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                transaction
                    ? t('forms.transactions.edit')
                    : t('forms.transactions.new')
            "
            :description="
                transaction
                    ? `${transaction.transaction_number} · ${transaction.template} · ${t('forms.templates.version_note', { version: transaction.version })}`
                    : t('forms.transactions.create_help')
            "
        />

        <div class="grid gap-2">
            <Label for="resident">{{ t('forms.transactions.resident') }}</Label>
            <ResidentPicker
                v-if="transaction === null"
                id="resident"
                v-model="resident"
                for-issuance
            />
            <p v-else class="text-sm font-medium">
                {{ resident?.full_name }} · {{ resident?.resident_number }}
            </p>
            <InputError :message="errors.resident_id" />
        </div>

        <div v-if="transaction === null" class="grid gap-2">
            <Label for="form_template_id">{{
                t('forms.transactions.template')
            }}</Label>
            <select
                id="form_template_id"
                v-model="form.form_template_id"
                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                required
            >
                <option :value="null">
                    {{ t('forms.transactions.choose_template') }}
                </option>
                <optgroup
                    v-for="[type, items] in groupedTemplates"
                    :key="type"
                    :label="type"
                >
                    <option
                        v-for="item in items"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </optgroup>
            </select>
            <p v-if="!templates.length" class="text-sm text-muted-foreground">
                {{ t('forms.transactions.no_templates') }}
            </p>
            <InputError :message="errors.form_template_id" />
        </div>

        <section v-if="fields.length" class="grid gap-4 rounded-lg border p-4">
            <Heading variant="small" :title="t('forms.transactions.values')" />
            <FieldValueInput
                v-for="field in fields"
                :key="field.key"
                v-model="form.values[field.key]"
                :field="field"
                :resident-label="residentLabels[field.key] ?? null"
                :error="errors[`values.${field.key}`]"
            />
        </section>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="or_number">{{ t('documents.or_number') }}</Label>
                <Input id="or_number" v-model="form.or_number" />
                <InputError :message="form.errors.or_number" />
            </div>
            <div class="grid gap-2">
                <Label for="amount">{{ t('documents.amount') }}</Label>
                <Input
                    id="amount"
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0"
                />
                <InputError :message="form.errors.amount" />
            </div>
        </div>

        <Button type="submit" :disabled="form.processing" class="w-fit">{{
            form.processing
                ? t('common.saving')
                : transaction
                  ? t('common.save')
                  : t('forms.transactions.save_and_print')
        }}</Button>
    </form>
</template>
