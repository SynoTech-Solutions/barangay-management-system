<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MessageEditor from '@/components/sms/MessageEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import type { SmsLimits } from '@/lib/sms';
import { index as outbox } from '@/routes/sms';
import { destroy, index, store, update } from '@/routes/sms/templates';
import type { SmsTemplate } from '@/types';

defineProps<{ templates: SmsTemplate[]; limits: SmsLimits }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.sms_outbox'), href: outbox() },
            { title: t('nav.sms_templates'), href: index() },
        ],
    },
});

const form = useForm<{ id: number | null; title: string; body: string }>({
    id: null,
    title: '',
    body: '',
});

function edit(template: SmsTemplate): void {
    form.clearErrors();
    form.id = template.id;
    form.title = template.title;
    form.body = template.body;
}

function reset(): void {
    form.reset();
    form.clearErrors();
}

function save(): void {
    const options = { preserveScroll: true, onSuccess: () => form.reset() };

    form.transform(({ title, body }) => ({ title, body }));

    if (form.id === null) {
        form.post(store.url(), options);
    } else {
        form.put(update.url(form.id), options);
    }
}

function remove(template: SmsTemplate): void {
    if (window.confirm(t('sms_templates.delete_confirm'))) {
        router.delete(destroy.url(template.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('sms_templates.title')" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            :title="t('sms_templates.title')"
            :description="t('sms_templates.description')"
        />

        <form class="grid gap-4 rounded-lg border p-4" @submit.prevent="save">
            <div class="grid gap-1">
                <Label for="title">{{ t('sms_templates.field_title') }}</Label>
                <Input
                    id="title"
                    v-model="form.title"
                    maxlength="50"
                    required
                />
                <InputError :message="form.errors.title" />
            </div>
            <MessageEditor
                v-model="form.body"
                :limits="limits"
                :error="form.errors.body"
                id="template_body"
            />
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">{{
                    form.id === null ? t('common.add') : t('common.save')
                }}</Button>
                <Button
                    v-if="form.id !== null"
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
                class="flex items-start justify-between gap-3 p-3"
            >
                <div class="grid gap-1">
                    <span class="font-medium">{{ template.title }}</span>
                    <span class="line-clamp-2 text-sm text-muted-foreground">{{
                        template.body
                    }}</span>
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
                        @click="remove(template)"
                        >{{ t('sms_templates.delete') }}</Button
                    >
                </div>
            </li>
            <li
                v-if="!templates.length"
                class="p-6 text-center text-sm text-muted-foreground"
            >
                {{ t('sms_templates.empty') }}
            </li>
        </ul>
    </div>
</template>
