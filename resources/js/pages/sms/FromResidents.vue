<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import MessageEditor from '@/components/sms/MessageEditor.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { smsLength } from '@/lib/sms';
import type { SmsLimits } from '@/lib/sms';
import { index as residentsIndex } from '@/routes/residents';
import { store as groupStore } from '@/routes/sms/residents/group';
import { store as sendStore } from '@/routes/sms/residents/send';
import type {
    SelectOption,
    SmsRecipientSummary,
    SmsTemplateOption,
} from '@/types';

const props = defineProps<{
    mode: 'send' | 'group';
    selection: Record<string, unknown>;
    summary: SmsRecipientSummary & { max: number; over_cap: boolean };
    limits: SmsLimits;
    templates: SmsTemplateOption[];
    groups: SelectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.residents'), href: residentsIndex() }],
    },
});

const form = useForm<{ body: string; sms_group_id: number | null }>({
    body: '',
    sms_group_id: null,
});

const tooLong = computed(
    () =>
        smsLength(form.body, props.limits).segments > props.limits.max_segments,
);
const ready = computed(() => {
    if (props.summary.recipients === 0 || props.summary.over_cap) {
        return false;
    }

    return props.mode === 'send'
        ? form.body.trim() !== '' && !tooLong.value
        : form.sms_group_id !== null;
});

function submit(): void {
    const key =
        props.mode === 'send' ? 'sms.send_confirm' : 'sms.group_confirm';

    if (!window.confirm(t(key, { count: props.summary.recipients }))) {
        return;
    }

    form.transform((data) => ({
        ...props.selection,
        ...(props.mode === 'send'
            ? { body: data.body }
            : { sms_group_id: data.sms_group_id }),
        confirmed_count: props.summary.recipients,
    })).post(props.mode === 'send' ? sendStore.url() : groupStore.url());
}
</script>

<template>
    <Head
        :title="
            mode === 'send' ? t('sms.from_residents') : t('sms.add_to_group')
        "
    />

    <form class="flex max-w-3xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                mode === 'send'
                    ? t('sms.from_residents')
                    : t('sms.add_to_group')
            "
            :description="
                selection.scope === 'filtered'
                    ? t('sms.scope_filtered')
                    : t('sms.scope_selected')
            "
        />

        <div class="rounded-lg border bg-muted/40 p-3 text-sm">
            <p class="text-base font-medium">
                {{ t('sms.confirm_count', { count: summary.recipients }) }}
            </p>
            <p class="text-muted-foreground">
                {{
                    t('sms.summary', {
                        recipients: summary.recipients,
                        skipped: summary.skipped,
                        duplicates: summary.duplicates,
                    })
                }}
            </p>
            <p v-if="summary.over_cap" class="mt-1 text-destructive">
                {{ t('sms.over_cap', { max: summary.max }) }}
            </p>
        </div>

        <MessageEditor
            v-if="mode === 'send'"
            v-model="form.body"
            :limits="limits"
            :templates="templates"
            :error="form.errors.body"
        />
        <div v-else class="grid gap-1">
            <Label for="sms_group_id">{{ t('sms.group') }}</Label>
            <OptionSelect
                id="sms_group_id"
                v-model="form.sms_group_id"
                :options="groups"
            />
            <InputError :message="form.errors.sms_group_id" />
        </div>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing || !ready">{{
                mode === 'send'
                    ? t('sms.send', { count: summary.recipients })
                    : t('sms.add_count', { count: summary.recipients })
            }}</Button>
            <Button variant="outline" as-child>
                <a :href="residentsIndex.url()">{{ t('common.cancel') }}</a>
            </Button>
        </div>
    </form>
</template>
