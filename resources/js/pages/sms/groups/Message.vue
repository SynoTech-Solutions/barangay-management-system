<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import MessageEditor from '@/components/sms/MessageEditor.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import { smsLength } from '@/lib/sms';
import type { SmsLimits } from '@/lib/sms';
import { index as outbox } from '@/routes/sms';
import { index, show } from '@/routes/sms/groups';
import { store } from '@/routes/sms/groups/message';
import type { SmsGroup, SmsRecipientSummary, SmsTemplateOption } from '@/types';

const props = defineProps<{
    group: SmsGroup;
    summary: SmsRecipientSummary;
    templates: SmsTemplateOption[];
    limits: SmsLimits;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.sms_outbox'), href: outbox() },
            { title: t('nav.sms_groups'), href: index() },
        ],
    },
});

const form = useForm({ body: '' });
const tooLong = computed(
    () =>
        smsLength(form.body, props.limits).segments > props.limits.max_segments,
);
const overCap = computed(
    () => props.summary.recipients > props.limits.max_recipients,
);

function submit(): void {
    if (
        window.confirm(
            t('sms.send_confirm', { count: props.summary.recipients }),
        )
    ) {
        form.post(store.url(props.group.id));
    }
}
</script>

<template>
    <Head :title="t('sms_groups.send_title', { name: group.name })" />

    <form class="flex max-w-3xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="t('sms_groups.send_title', { name: group.name })"
            :description="t('sms_groups.send_description')"
        />

        <div class="rounded-lg border bg-muted/40 p-3 text-sm">
            {{
                t('sms.summary', {
                    recipients: summary.recipients,
                    skipped: summary.skipped,
                    duplicates: summary.duplicates,
                })
            }}
            <p v-if="overCap" class="mt-1 text-destructive">
                {{ t('sms.over_cap', { max: limits.max_recipients }) }}
            </p>
        </div>

        <MessageEditor
            v-model="form.body"
            :limits="limits"
            :templates="templates"
            :error="form.errors.body"
        />

        <div class="flex gap-2">
            <Button
                type="submit"
                :disabled="
                    form.processing ||
                    summary.recipients === 0 ||
                    overCap ||
                    form.body.trim() === '' ||
                    tooLong
                "
                >{{ t('sms.send', { count: summary.recipients }) }}</Button
            >
            <Button variant="outline" as-child>
                <a :href="show.url(group.id)">{{
                    t('sms_groups.members_link')
                }}</a>
            </Button>
        </div>
    </form>
</template>
