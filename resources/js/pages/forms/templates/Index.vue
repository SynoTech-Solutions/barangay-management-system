<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { tokens } from '@/routes/forms';
import { index as picklistsIndex } from '@/routes/forms/picklists';
import { create, index, show } from '@/routes/forms/templates';
import type { FormTemplate, Paginated, SelectOption } from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.form_templates'), href: index() }],
    },
});

const props = defineProps<{
    templates: Paginated<FormTemplate>;
    filters: {
        search: string | null;
        type_id: number | null;
        inactive: boolean;
    };
    types: SelectOption[];
}>();

const { feature } = useAccess();
const state = reactive({
    search: props.filters.search ?? '',
    type_id: props.filters.type_id,
    inactive: props.filters.inactive,
});

function apply(): void {
    router.get(
        index.url(),
        Object.fromEntries(
            Object.entries({
                search: state.search,
                type_id: state.type_id,
                inactive: state.inactive ? 1 : null,
            }).filter(([, value]) => value !== null && value !== ''),
        ) as Record<string, string>,
        { preserveState: true, replace: true },
    );
}

const applySearch = useDebounceFn(apply, SEARCH_DELAY_MS);
</script>

<template>
    <Head :title="t('forms.templates.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('forms.templates.title')"
                :description="t('forms.templates.description')"
            />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="tokens()">{{ t('forms.tokens.title') }}</Link>
                </Button>
                <Button
                    v-if="feature(FeatureFlag.FormsTablePicklists)"
                    variant="outline"
                    as-child
                >
                    <Link :href="picklistsIndex()">{{
                        t('forms.picklists.title')
                    }}</Link>
                </Button>
                <Button as-child>
                    <Link :href="create()">{{ t('forms.templates.new') }}</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-3">
            <Input
                v-model="state.search"
                :placeholder="t('forms.templates.search')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.type_id"
                :options="types"
                :placeholder="t('forms.templates.all_types')"
                @update:model-value="apply"
            />
            <CheckboxField
                v-model="state.inactive"
                :label="t('forms.templates.show_inactive')"
                @update:model-value="apply"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('forms.templates.number') }}</th>
                        <th class="p-3">{{ t('forms.templates.name') }}</th>
                        <th class="p-3">{{ t('forms.templates.type') }}</th>
                        <th class="p-3">{{ t('forms.templates.kind') }}</th>
                        <th class="p-3">{{ t('forms.templates.version') }}</th>
                        <th class="p-3">{{ t('documents.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!templates.data.length">
                        <td colspan="6" class="p-3 text-muted-foreground">
                            {{ t('forms.templates.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="template in templates.data"
                        :key="template.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">
                            {{ template.template_number }}
                        </td>
                        <td class="p-3">
                            <Link
                                :href="show(template.id)"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ template.name }}</Link
                            >
                        </td>
                        <td class="p-3">{{ template.type }}</td>
                        <td class="p-3">{{ template.kind_label }}</td>
                        <td class="p-3">{{ template.version }}</td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    template.is_active ? 'default' : 'outline'
                                "
                                >{{
                                    template.is_active
                                        ? t('common.active')
                                        : t('common.inactive')
                                }}</Badge
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="templates.meta" />
    </div>
</template>
