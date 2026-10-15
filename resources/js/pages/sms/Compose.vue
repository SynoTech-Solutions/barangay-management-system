<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import MessageEditor from '@/components/sms/MessageEditor.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { smsLength } from '@/lib/sms';
import type { SmsLimits } from '@/lib/sms';
import { create, index, store } from '@/routes/sms';
import type {
    ResidentOption,
    SmsComposePrefill,
    SmsTemplateOption,
} from '@/types';

const NUMBER_SEPARATORS = /[,;\n]+/;

const props = defineProps<{
    prefill: SmsComposePrefill;
    templates: SmsTemplateOption[];
    limits: SmsLimits;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.sms_outbox'), href: index() },
            { title: t('sms.new'), href: create() },
        ],
    },
});

const residents = ref<ResidentOption[]>(
    [props.prefill.resident, props.prefill.reply_resident].filter(
        (option): option is ResidentOption => option !== null,
    ),
);
const picked = ref<ResidentOption | null>(null);

const form = useForm({
    numbers: props.prefill.numbers.join(', '),
    body: '',
});

watch(picked, (option) => {
    if (option && !residents.value.some((item) => item.id === option.id)) {
        residents.value = [...residents.value, option];
    }

    picked.value = null;
});

const typedCount = computed(
    () =>
        form.numbers
            .split(NUMBER_SEPARATORS)
            .map((number) => number.trim())
            .filter((number) => number !== '').length,
);
const recipientCount = computed(
    () => residents.value.length + typedCount.value,
);
const tooLong = computed(
    () =>
        smsLength(form.body, props.limits).segments > props.limits.max_segments,
);

function errorFor(prefix: string): string | undefined {
    return Object.entries(form.errors as Record<string, string>).find(
        ([key]) => key === prefix || key.startsWith(`${prefix}.`),
    )?.[1];
}

function remove(option: ResidentOption): void {
    residents.value = residents.value.filter((item) => item.id !== option.id);
}

function submit(): void {
    if (
        !window.confirm(t('sms.send_confirm', { count: recipientCount.value }))
    ) {
        return;
    }

    form.transform((data) => ({
        ...data,
        resident_ids: residents.value.map((option) => option.id),
    })).post(store.url());
}
</script>

<template>
    <Head :title="t('sms.new')" />

    <form class="flex max-w-3xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="t('sms.new')"
            :description="t('sms.compose_description')"
        />

        <div class="grid gap-2">
            <Label for="picker">{{ t('sms.pick_residents') }}</Label>
            <ResidentPicker
                id="picker"
                v-model="picked"
                with-mobile
                :placeholder="t('sms.pick_placeholder')"
            />
            <div v-if="residents.length" class="flex flex-wrap gap-2">
                <Badge
                    v-for="option in residents"
                    :key="option.id"
                    variant="secondary"
                    class="gap-1"
                >
                    {{ option.full_name }}
                    <button
                        type="button"
                        class="ml-1"
                        :aria-label="t('sms.remove_recipient')"
                        @click="remove(option)"
                    >
                        ×
                    </button>
                </Badge>
            </div>
            <InputError :message="errorFor('resident_ids')" />
        </div>

        <div class="grid gap-2">
            <Label for="numbers">{{ t('sms.typed_numbers') }}</Label>
            <textarea
                id="numbers"
                v-model="form.numbers"
                rows="2"
                :placeholder="t('sms.numbers_placeholder')"
                class="w-full rounded-md border bg-background p-3 text-sm"
            />
            <p class="text-sm text-muted-foreground">
                {{ t('sms.numbers_help') }}
            </p>
            <InputError :message="errorFor('numbers')" />
        </div>

        <MessageEditor
            v-model="form.body"
            :limits="limits"
            :templates="templates"
            :error="form.errors.body"
        />

        <div class="flex items-center gap-3">
            <Button
                type="submit"
                :disabled="
                    form.processing ||
                    recipientCount === 0 ||
                    form.body.trim() === '' ||
                    tooLong
                "
                >{{ t('sms.send', { count: recipientCount }) }}</Button
            >
            <span class="text-sm text-muted-foreground">{{
                t('sms.dedupe_hint')
            }}</span>
        </div>
    </form>
</template>
