<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import TokenList from '@/components/forms/TokenList.vue';
import { t } from '@/composables/useI18n';
import { wrapToken } from '@/lib/placeholders';
import { index } from '@/routes/forms/templates';
import type { TokenGroup } from '@/types';

defineProps<{
    tokens: TokenGroup[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.form_templates'), href: index() }],
    },
});
</script>

<template>
    <Head :title="t('forms.tokens.title')" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            :title="t('forms.tokens.title')"
            :description="t('forms.tokens.description')"
        />
        <p class="text-sm">
            {{
                t('forms.tokens.field_help', {
                    example: wrapToken('field.purpose'),
                })
            }}
        </p>
        <TokenList :groups="tokens" />
    </div>
</template>
