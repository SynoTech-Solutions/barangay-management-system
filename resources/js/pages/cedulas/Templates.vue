<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { index as cedulasIndex } from '@/routes/cedulas';
import {
    deactivate,
    index,
    reactivate,
    store,
    update,
} from '@/routes/cedulas/templates';
import type { CedulaTemplate } from '@/types';

const INCOME_FIELDS = [
    'business_gross_receipts',
    'salaries',
    'real_property_income',
] as const;

defineProps<{ templates: CedulaTemplate[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.cedulas'), href: cedulasIndex() },
            { title: t('cedulas.templates'), href: index() },
        ],
    },
});

const editingId = ref<number | null>(null);
const form = useForm({
    name: '',
    is_exempt: false,
    business_gross_receipts: '',
    salaries: '',
    real_property_income: '',
});

const errors = form.errors as Record<string, string>;

function edit(template: CedulaTemplate): void {
    editingId.value = template.id;
    form.name = template.name;
    form.is_exempt = template.is_exempt;
    form.business_gross_receipts = template.business_gross_receipts;
    form.salaries = template.salaries;
    form.real_property_income = template.real_property_income;
}

function reset(): void {
    editingId.value = null;
    form.reset();
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: reset };

    if (editingId.value === null) {
        form.post(store.url(), options);
    } else {
        form.put(update.url(editingId.value), options);
    }
}

function toggle(template: CedulaTemplate): void {
    router.post(
        template.is_active
            ? deactivate.url(template.id)
            : reactivate.url(template.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('cedulas.templates')" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            :title="t('cedulas.templates')"
            :description="t('cedulas.templates_description')"
        />

        <form class="grid gap-4 rounded-lg border p-3" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-4">
                <div class="grid gap-2">
                    <Label for="name">{{ t('cedulas.template_name') }}</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div
                    v-for="field in INCOME_FIELDS"
                    :key="field"
                    class="grid gap-2"
                >
                    <Label :for="field">{{
                        t(`cedulas.fields.${field}`)
                    }}</Label>
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
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">{{
                    editingId === null ? t('common.add') : t('common.save')
                }}</Button>
                <Button
                    v-if="editingId !== null"
                    type="button"
                    variant="outline"
                    @click="reset"
                    >{{ t('common.cancel') }}</Button
                >
            </div>
        </form>

        <ul class="divide-y rounded-lg border">
            <li
                v-for="template in templates"
                :key="template.id"
                class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
            >
                <div>
                    <span class="font-medium">{{ template.name }}</span>
                    <Badge
                        v-if="!template.is_active"
                        variant="outline"
                        class="ml-2"
                        >{{ t('common.inactive') }}</Badge
                    >
                    <div class="text-muted-foreground">
                        {{ template.business_gross_receipts }} ·
                        {{ template.salaries }} ·
                        {{ template.real_property_income }}
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        @click="edit(template)"
                        >{{ t('common.edit') }}</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        @click="toggle(template)"
                        >{{
                            template.is_active
                                ? t('lookups.hide')
                                : t('lookups.restore')
                        }}</Button
                    >
                </div>
            </li>
        </ul>
    </div>
</template>
