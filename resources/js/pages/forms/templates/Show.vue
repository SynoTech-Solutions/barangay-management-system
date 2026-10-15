<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { deactivate, edit, index, reactivate } from '@/routes/forms/templates';
import {
    preview as versionPreview,
    source as versionSource,
} from '@/routes/forms/templates/versions';
import { create as newTransaction } from '@/routes/forms/transactions';
import type {
    FormTemplate,
    FormTemplateEditor,
    FormTemplateVersionSummary,
} from '@/types';

const props = defineProps<{
    template: FormTemplate;
    versions: FormTemplateVersionSummary[];
    currentVersion: FormTemplateEditor | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.form_templates'), href: index() }],
    },
});

const { can, feature } = useAccess();

const kindEnabled = (): boolean =>
    props.template.kind === 'pdf'
        ? feature(FeatureFlag.PdfFormTemplates)
        : feature(FeatureFlag.FormsRichTextTemplates);

function toggle(): void {
    const target = props.template.is_active ? deactivate : reactivate;
    router.post(target.url(props.template.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="template.name" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="template.name"
                :description="`${template.template_number} · ${template.type ?? ''} · ${template.kind_label}`"
            />
            <div class="flex flex-wrap gap-2">
                <Badge :variant="template.is_active ? 'default' : 'outline'">{{
                    template.is_active
                        ? t('common.active')
                        : t('common.inactive')
                }}</Badge>
                <Button
                    v-if="
                        template.is_active &&
                        kindEnabled() &&
                        can(Permission.FormTransactionsCreate)
                    "
                    as-child
                >
                    <Link
                        :href="
                            newTransaction({
                                query: { template_id: template.id },
                            })
                        "
                        >{{ t('forms.transactions.new') }}</Link
                    >
                </Button>
                <Button v-if="kindEnabled()" variant="outline" as-child>
                    <Link :href="edit(template.id)">{{
                        t('common.edit')
                    }}</Link>
                </Button>
                <Button variant="outline" @click="toggle">{{
                    template.is_active
                        ? t('forms.templates.deactivate')
                        : t('forms.templates.reactivate')
                }}</Button>
            </div>
        </div>

        <section v-if="currentVersion" class="grid gap-2">
            <Heading
                variant="small"
                :title="
                    t('forms.templates.current_fields', {
                        version: currentVersion.version,
                    })
                "
            />
            <table class="w-full rounded-lg border text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-2">{{ t('forms.fields.label') }}</th>
                        <th class="p-2">{{ t('forms.fields.key') }}</th>
                        <th class="p-2">{{ t('forms.fields.input_type') }}</th>
                        <th class="p-2">{{ t('forms.fields.required') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!currentVersion.fields.length">
                        <td colspan="4" class="p-2 text-muted-foreground">
                            {{ t('forms.fields.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="field in currentVersion.fields"
                        :key="field.key"
                        class="border-t"
                    >
                        <td class="p-2">{{ field.label }}</td>
                        <td class="p-2 font-mono">{{ field.key }}</td>
                        <td class="p-2">
                            {{ t(`forms.input_types.${field.input_type}`) }}
                        </td>
                        <td class="p-2">
                            {{
                                field.is_required
                                    ? t('common.yes')
                                    : t('common.no')
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="grid gap-2">
            <Heading
                variant="small"
                :title="t('forms.templates.versions')"
                :description="t('forms.templates.versions_help')"
            />
            <table class="w-full rounded-lg border text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-2">{{ t('forms.templates.version') }}</th>
                        <th class="p-2">{{ t('forms.templates.saved_at') }}</th>
                        <th class="p-2">{{ t('forms.templates.saved_by') }}</th>
                        <th class="p-2">
                            {{ t('forms.templates.field_count') }}
                        </th>
                        <th class="p-2 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="version in versions"
                        :key="version.id"
                        class="border-t"
                    >
                        <td class="p-2">
                            {{ version.version }}
                            <Badge
                                v-if="version.is_current"
                                variant="secondary"
                                >{{ t('forms.templates.current') }}</Badge
                            >
                        </td>
                        <td class="p-2">
                            {{ new Date(version.created_at).toLocaleString() }}
                        </td>
                        <td class="p-2">{{ version.created_by }}</td>
                        <td class="p-2">{{ version.field_count }}</td>
                        <td class="p-2 text-right">
                            <div
                                v-if="kindEnabled()"
                                class="flex justify-end gap-2"
                            >
                                <Button variant="outline" size="sm" as-child>
                                    <a
                                        :href="
                                            versionPreview.url({
                                                template: template.id,
                                                version: version.id,
                                            })
                                        "
                                        target="_blank"
                                        >{{ t('forms.templates.preview') }}</a
                                    >
                                </Button>
                                <Button
                                    v-if="version.pdf_file_name"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <a
                                        :href="
                                            versionSource.url({
                                                template: template.id,
                                                version: version.id,
                                            })
                                        "
                                        target="_blank"
                                        >{{
                                            t('forms.templates.source_pdf')
                                        }}</a
                                    >
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
