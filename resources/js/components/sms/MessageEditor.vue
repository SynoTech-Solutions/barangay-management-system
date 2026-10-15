<script setup lang="ts">
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { smsLength } from '@/lib/sms';
import type { SmsLimits } from '@/lib/sms';
import type { SmsTemplateOption } from '@/types';

const props = withDefaults(
    defineProps<{
        limits: SmsLimits;
        templates?: SmsTemplateOption[];
        error?: string;
        id?: string;
    }>(),
    { templates: () => [], error: undefined, id: 'body' },
);

const body = defineModel<string>({ required: true });
const templateId = ref<number | null>(null);

const length = computed(() => smsLength(body.value, props.limits));
const tooLong = computed(
    () => length.value.segments > props.limits.max_segments,
);

function applyTemplate(): void {
    const template = props.templates.find(
        (option) => option.id === templateId.value,
    );

    if (template) {
        body.value = template.body;
    }
}

defineExpose({ tooLong, length });
</script>

<template>
    <div class="grid gap-2">
        <div v-if="templates.length" class="grid gap-1">
            <Label :for="`${id}_template`">{{ t('sms.template') }}</Label>
            <select
                :id="`${id}_template`"
                v-model="templateId"
                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                @change="applyTemplate"
            >
                <option :value="null">{{ t('sms.choose_template') }}</option>
                <option
                    v-for="template in templates"
                    :key="template.id"
                    :value="template.id"
                >
                    {{ template.title }}
                </option>
            </select>
        </div>
        <Label :for="id">{{ t('sms.message') }}</Label>
        <textarea
            :id="id"
            v-model="body"
            rows="6"
            class="w-full rounded-md border bg-background p-3 text-sm"
        />
        <p
            class="text-sm"
            :class="tooLong ? 'text-destructive' : 'text-muted-foreground'"
        >
            {{
                t('sms.length', {
                    units: length.units,
                    segments: length.segments,
                    max: limits.max_segments,
                    encoding: length.encoding.toUpperCase(),
                })
            }}
        </p>
        <InputError :message="error" />
    </div>
</template>
