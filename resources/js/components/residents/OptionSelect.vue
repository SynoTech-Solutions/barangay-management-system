<script setup lang="ts" generic="T extends string | number">
import { t } from '@/composables/useI18n';
import type { SelectOption } from '@/types';

withDefaults(
    defineProps<{
        options: SelectOption<T>[];
        id?: string;
        placeholder?: string;
        required?: boolean;
    }>(),
    { id: undefined, placeholder: undefined, required: false },
);

const model = defineModel<T | null>({ required: true });
</script>

<template>
    <select
        :id="id"
        v-model="model"
        :required="required"
        class="h-9 w-full rounded-md border bg-background px-3 text-sm"
    >
        <option :value="null">
            {{ placeholder ?? t('residents.choose') }}
        </option>
        <option
            v-for="option in options"
            :key="String(option.value)"
            :value="option.value"
        >
            {{ option.label }}
        </option>
    </select>
</template>
