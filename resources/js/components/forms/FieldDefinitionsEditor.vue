<script setup lang="ts">
import { ArrowDown, ArrowUp, Trash2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/composables/useI18n';
import { fieldKeyFromLabel } from '@/lib/placeholders';
import type { FormFieldDraft, FormInputType, SelectOption } from '@/types';

const props = defineProps<{
    inputTypes: SelectOption<string>[];
    picklists: SelectOption[];
    errors: Record<string, string | undefined>;
    max: number;
}>();

const fields = defineModel<FormFieldDraft[]>({ required: true });

function add(): void {
    fields.value = [
        ...fields.value,
        {
            key: '',
            label: '',
            input_type: 'text',
            is_required: false,
            picklist_id: null,
        },
    ];
}

function remove(index: number): void {
    fields.value = fields.value.filter((_, position) => position !== index);
}

function move(index: number, offset: number): void {
    const target = index + offset;

    if (target < 0 || target >= fields.value.length) {
        return;
    }

    const next = [...fields.value];
    [next[index], next[target]] = [next[target], next[index]];
    fields.value = next;
}

function relabel(field: FormFieldDraft, label: string): void {
    const derived = fieldKeyFromLabel(field.label);

    if (field.key === '' || field.key === derived) {
        field.key = fieldKeyFromLabel(label);
    }

    field.label = label;
}

function setType(field: FormFieldDraft, type: string | null): void {
    field.input_type = (type ?? 'text') as FormInputType;

    if (field.input_type !== 'table') {
        field.picklist_id = null;
    }
}

function error(index: number, name: string): string | undefined {
    return props.errors[`fields.${index}.${name}`];
}
</script>

<template>
    <div class="grid gap-3">
        <p v-if="!fields.length" class="text-sm text-muted-foreground">
            {{ t('forms.fields.empty') }}
        </p>
        <div
            v-for="(field, index) in fields"
            :key="index"
            class="grid gap-2 rounded-md border p-3 md:grid-cols-[1fr_1fr_1fr_auto]"
        >
            <div class="grid gap-1">
                <Input
                    :model-value="field.label"
                    :placeholder="t('forms.fields.label')"
                    :aria-label="t('forms.fields.label')"
                    @update:model-value="
                        (value: string | number) =>
                            relabel(field, String(value))
                    "
                />
                <InputError :message="error(index, 'label')" />
            </div>
            <div class="grid gap-1">
                <Input
                    v-model="field.key"
                    class="font-mono"
                    :placeholder="t('forms.fields.key')"
                    :aria-label="t('forms.fields.key')"
                />
                <InputError :message="error(index, 'key')" />
            </div>
            <div class="grid gap-1">
                <OptionSelect
                    :model-value="field.input_type"
                    :options="inputTypes"
                    :aria-label="t('forms.fields.input_type')"
                    @update:model-value="
                        (value: string | null) => setType(field, value)
                    "
                />
                <OptionSelect
                    v-if="field.input_type === 'table'"
                    v-model="field.picklist_id"
                    :options="picklists"
                    :placeholder="t('forms.fields.choose_picklist')"
                />
                <InputError
                    :message="
                        error(index, 'input_type') ??
                        error(index, 'picklist_id')
                    "
                />
            </div>
            <div class="flex items-start gap-1">
                <CheckboxField
                    v-model="field.is_required"
                    :label="t('forms.fields.required')"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :aria-label="t('forms.fields.move_up')"
                    @click="move(index, -1)"
                    ><ArrowUp class="size-4"
                /></Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :aria-label="t('forms.fields.move_down')"
                    @click="move(index, 1)"
                    ><ArrowDown class="size-4"
                /></Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :aria-label="t('forms.fields.remove')"
                    @click="remove(index)"
                    ><Trash2 class="size-4"
                /></Button>
            </div>
        </div>
        <Button
            type="button"
            variant="outline"
            class="w-fit"
            :disabled="fields.length >= max"
            @click="add"
            >{{ t('forms.fields.add') }}</Button
        >
    </div>
</template>
