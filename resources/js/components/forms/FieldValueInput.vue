<script setup lang="ts">
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { FormFieldValue, IssuableField, ResidentOption } from '@/types';

const props = defineProps<{
    field: IssuableField;
    error?: string;
    residentLabel?: string | null;
}>();

const model = defineModel<FormFieldValue>({ required: true });

const resident = ref<ResidentOption | null>(
    props.field.input_type === 'resident' &&
        typeof model.value === 'number' &&
        props.residentLabel
        ? {
              id: model.value,
              full_name: props.residentLabel,
              resident_number: '',
              birthdate: null,
              age: null,
              is_deceased: false,
          }
        : null,
);

watch(resident, (value) => {
    if (props.field.input_type === 'resident') {
        model.value = value?.id ?? null;
    }
});

const id = `field_${props.field.key}`;
</script>

<template>
    <div class="grid gap-2">
        <CheckboxField
            v-if="field.input_type === 'check'"
            :model-value="model === true"
            :label="field.label + (field.is_required ? ' *' : '')"
            @update:model-value="(value: boolean) => (model = value)"
        />
        <template v-else>
            <Label :for="id"
                >{{ field.label }}{{ field.is_required ? ' *' : '' }}</Label
            >
            <textarea
                v-if="field.input_type === 'text'"
                :id="id"
                :value="(model as string | null) ?? ''"
                rows="2"
                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                @input="model = ($event.target as HTMLTextAreaElement).value"
            />
            <Input
                v-else-if="field.input_type === 'date'"
                :id="id"
                type="date"
                :model-value="(model as string | null) ?? ''"
                @update:model-value="
                    (value: string | number) => (model = String(value) || null)
                "
            />
            <Input
                v-else-if="field.input_type === 'amount'"
                :id="id"
                type="number"
                step="0.01"
                min="0"
                :model-value="(model as string | null) ?? ''"
                @update:model-value="
                    (value: string | number) => (model = String(value) || null)
                "
            />
            <OptionSelect
                v-else-if="field.input_type === 'table'"
                :id="id"
                :model-value="(model as number | null) ?? null"
                :options="field.options"
                @update:model-value="(value: number | null) => (model = value)"
            />
            <ResidentPicker
                v-else-if="field.input_type === 'resident'"
                :id="id"
                v-model="resident"
            />
        </template>
        <InputError :message="error" />
    </div>
</template>
