<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { t } from '@/composables/useI18n';
import { fieldToken, wrapToken } from '@/lib/placeholders';
import type { PdfField, PdfMappingDraft, TokenGroup } from '@/types';

const props = defineProps<{
    pdfFields: PdfField[];
    groups: TokenGroup[];
    fieldKeys: string[];
}>();

const mappings = defineModel<PdfMappingDraft[]>({ required: true });

const FILLABLE = ['text', 'checkbox', 'choice'];

const fillable = computed(() =>
    props.pdfFields.filter((field) => FILLABLE.includes(field.type)),
);

function mappingFor(name: string): PdfMappingDraft {
    let mapping = mappings.value.find((item) => item.pdf_field === name);

    if (!mapping) {
        mapping = {
            pdf_field: name,
            token: null,
            checked_value: null,
            unchecked_value: null,
        };
        mappings.value = [...mappings.value, mapping];
    }

    return mapping;
}

function update(
    name: string,
    key: 'token' | 'checked_value' | 'unchecked_value',
    value: string | null,
): void {
    const mapping = mappingFor(name);
    mapping[key] = value === '' ? null : value;
}

function current(
    name: string,
    key: 'token' | 'checked_value' | 'unchecked_value',
): string {
    return mappings.value.find((item) => item.pdf_field === name)?.[key] ?? '';
}
</script>

<template>
    <div class="overflow-x-auto rounded-md border">
        <table class="w-full text-sm">
            <thead class="bg-muted/50 text-left">
                <tr>
                    <th class="p-2">{{ t('forms.pdf.field') }}</th>
                    <th class="p-2">{{ t('forms.pdf.type') }}</th>
                    <th class="p-2">{{ t('forms.pdf.source') }}</th>
                    <th class="p-2">{{ t('forms.pdf.checked_value') }}</th>
                    <th class="p-2">{{ t('forms.pdf.unchecked_value') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!fillable.length">
                    <td colspan="5" class="p-2 text-muted-foreground">
                        {{ t('forms.pdf.no_fields') }}
                    </td>
                </tr>
                <tr
                    v-for="field in fillable"
                    :key="field.name"
                    class="border-t"
                >
                    <td class="p-2 font-mono">{{ field.name }}</td>
                    <td class="p-2">
                        {{ t(`forms.pdf.types.${field.type}`) }}
                    </td>
                    <td class="p-2">
                        <select
                            class="h-9 w-full rounded-md border bg-background px-2 text-sm"
                            :aria-label="t('forms.pdf.source')"
                            :value="current(field.name, 'token')"
                            @change="
                                update(
                                    field.name,
                                    'token',
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">
                                {{ t('forms.pdf.leave_blank') }}
                            </option>
                            <optgroup
                                v-if="fieldKeys.length"
                                :label="t('forms.tokens.fields')"
                            >
                                <option
                                    v-for="key in fieldKeys"
                                    :key="key"
                                    :value="fieldToken(key)"
                                >
                                    {{ wrapToken(fieldToken(key)) }}
                                </option>
                            </optgroup>
                            <optgroup
                                v-for="group in groups"
                                :key="group.namespace"
                                :label="group.label"
                            >
                                <option
                                    v-for="token in group.tokens"
                                    :key="token.token"
                                    :value="token.token"
                                >
                                    {{ token.label }}
                                </option>
                            </optgroup>
                        </select>
                    </td>
                    <td class="p-2">
                        <Input
                            v-if="field.type === 'text'"
                            :model-value="current(field.name, 'checked_value')"
                            :aria-label="t('forms.pdf.checked_value')"
                            @update:model-value="
                                (value: string | number) =>
                                    update(
                                        field.name,
                                        'checked_value',
                                        String(value),
                                    )
                            "
                        />
                    </td>
                    <td class="p-2">
                        <Input
                            v-if="field.type === 'text'"
                            :model-value="
                                current(field.name, 'unchecked_value')
                            "
                            :aria-label="t('forms.pdf.unchecked_value')"
                            @update:model-value="
                                (value: string | number) =>
                                    update(
                                        field.name,
                                        'unchecked_value',
                                        String(value),
                                    )
                            "
                        />
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="p-2 text-xs text-muted-foreground">
            {{ t('forms.pdf.check_help') }}
        </p>
    </div>
</template>
