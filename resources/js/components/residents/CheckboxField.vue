<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

defineProps<{ label: string; disabled?: boolean }>();

const model = defineModel<boolean>({ required: true });
</script>

<template>
    <Label class="flex items-center gap-2 font-normal">
        <Checkbox
            :model-value="model"
            :disabled="disabled"
            @update:model-value="(value) => (model = value === true)"
        />
        <span>{{ label }}</span>
    </Label>
</template>
