<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import { fieldToken, wrapToken } from '@/lib/placeholders';
import type { TokenGroup } from '@/types';

defineProps<{
    groups: TokenGroup[];
    fieldKeys?: string[];
    insertable?: boolean;
}>();

const emit = defineEmits<{ insert: [token: string] }>();
</script>

<template>
    <div class="grid gap-4 text-sm">
        <section v-if="fieldKeys && fieldKeys.length">
            <h3 class="mb-1 font-semibold">{{ t('forms.tokens.fields') }}</h3>
            <ul class="grid gap-1">
                <li
                    v-for="key in fieldKeys"
                    :key="key"
                    class="flex items-center justify-between gap-2"
                >
                    <code class="font-mono text-xs">{{
                        wrapToken(fieldToken(key))
                    }}</code>
                    <Button
                        v-if="insertable"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="emit('insert', fieldToken(key))"
                        >{{ t('forms.tokens.insert') }}</Button
                    >
                </li>
            </ul>
        </section>
        <section v-for="group in groups" :key="group.namespace">
            <h3 class="mb-1 font-semibold">{{ group.label }}</h3>
            <ul class="grid gap-1">
                <li
                    v-for="token in group.tokens"
                    :key="token.token"
                    class="flex items-center justify-between gap-2"
                >
                    <span>
                        <code class="font-mono text-xs">{{
                            wrapToken(token.token)
                        }}</code>
                        <span class="block text-muted-foreground">{{
                            token.label
                        }}</span>
                    </span>
                    <Button
                        v-if="insertable"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="emit('insert', token.token)"
                        >{{ t('forms.tokens.insert') }}</Button
                    >
                </li>
            </ul>
        </section>
    </div>
</template>
