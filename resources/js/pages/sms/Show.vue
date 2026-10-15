<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { cancel, create, index, retry } from '@/routes/sms';
import type { SmsMessage } from '@/types';

const props = defineProps<{ message: SmsMessage }>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.sms_outbox'), href: index() }] },
});

const { can } = useAccess();

function when(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '';
}

function cancelMessage(): void {
    if (window.confirm(t('sms.cancel_confirm', { count: 1 }))) {
        router.post(cancel.url(), { ids: [props.message.id] });
    }
}

function retryMessage(): void {
    router.post(retry.url(), { ids: [props.message.id] });
}
</script>

<template>
    <Head :title="t('sms.view_title')" />

    <div class="flex max-w-3xl flex-col gap-4 p-4">
        <Heading
            :title="
                message.resident?.full_name ??
                message.recipient ??
                t('sms.pruned')
            "
            :description="t('sms.view_description')"
        />

        <dl
            class="grid gap-x-6 gap-y-2 rounded-lg border p-4 text-sm sm:grid-cols-2"
        >
            <dt class="text-muted-foreground">{{ t('sms.recipient') }}</dt>
            <dd>{{ message.recipient ?? t('sms.pruned') }}</dd>
            <dt class="text-muted-foreground">{{ t('sms.status') }}</dt>
            <dd>
                <Badge variant="outline">{{ message.status_label }}</Badge>
                <span
                    v-if="message.last_error_code"
                    class="ml-2 text-muted-foreground"
                    >{{ message.last_error_code }}</span
                >
            </dd>
            <dt class="text-muted-foreground">{{ t('sms.source') }}</dt>
            <dd>{{ message.source ?? '' }}</dd>
            <dt class="text-muted-foreground">{{ t('sms.queued_at') }}</dt>
            <dd>
                {{ when(message.queued_at) }} · {{ message.queued_by ?? '' }}
            </dd>
            <dt class="text-muted-foreground">{{ t('sms.sent_at') }}</dt>
            <dd>{{ when(message.sent_at) }}</dd>
            <dt v-if="message.cancelled_at" class="text-muted-foreground">
                {{ t('sms.cancelled_at') }}
            </dt>
            <dd v-if="message.cancelled_at">
                {{ when(message.cancelled_at) }} ·
                {{ message.cancelled_by ?? '' }}
            </dd>
            <dt class="text-muted-foreground">{{ t('sms.attempts') }}</dt>
            <dd>{{ message.attempts }}</dd>
            <dt class="text-muted-foreground">{{ t('sms.segments') }}</dt>
            <dd>
                {{ message.segments }} ({{ message.encoding.toUpperCase() }})
            </dd>
            <dt
                v-if="message.provider_message_id"
                class="text-muted-foreground"
            >
                {{ t('sms.provider_id') }}
            </dt>
            <dd v-if="message.provider_message_id" class="font-mono">
                {{ message.provider_message_id }}
            </dd>
        </dl>

        <div class="rounded-lg border p-4 text-sm whitespace-pre-wrap">
            {{ message.body ?? t('sms.pruned_body') }}
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                v-if="can(Permission.SmsSend) && message.recipient"
                as-child
            >
                <Link :href="create({ query: { reply_to: message.id } })">{{
                    t('sms.send_another')
                }}</Link>
            </Button>
            <Button
                v-if="can(Permission.SmsCancel) && message.can_cancel"
                variant="outline"
                @click="cancelMessage"
                >{{ t('sms.cancel') }}</Button
            >
            <Button
                v-if="can(Permission.SmsSend) && message.can_retry"
                variant="outline"
                @click="retryMessage"
                >{{ t('sms.retry') }}</Button
            >
            <Button variant="link" as-child>
                <Link :href="index()">{{ t('sms.back') }}</Link>
            </Button>
        </div>
        <p class="text-xs text-muted-foreground">#{{ message.id }}</p>
    </div>
</template>
