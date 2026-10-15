<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import FieldDefinitionsEditor from '@/components/forms/FieldDefinitionsEditor.vue';
import PdfMappingEditor from '@/components/forms/PdfMappingEditor.vue';
import RichTextEditor from '@/components/forms/RichTextEditor.vue';
import TokenList from '@/components/forms/TokenList.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { postFormData, postJson } from '@/lib/http';
import {
    index,
    inspectPdf,
    preview,
    show,
    store,
    update,
} from '@/routes/forms/templates';
import type {
    FormFieldDraft,
    FormTemplate,
    FormTemplateEditor,
    PdfField,
    PdfMappingDraft,
    SelectOption,
    TokenGroup,
} from '@/types';

const props = defineProps<{
    template: FormTemplate | null;
    editor: FormTemplateEditor | null;
    types: SelectOption[];
    kinds: SelectOption<string>[];
    inputTypes: SelectOption<string>[];
    picklists: SelectOption[];
    tokens: TokenGroup[];
    limits: { pdf_max_kilobytes: number; fields_max: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.form_templates'), href: index() }],
    },
});

const template = props.template;
const form = useForm<{
    type_id: number | null;
    name: string;
    kind: string | null;
    body: string;
    pdf: File | null;
    fields: FormFieldDraft[];
    mappings: PdfMappingDraft[];
}>({
    type_id: template?.type_id ?? null,
    name: template?.name ?? '',
    kind: template?.kind ?? props.kinds[0]?.value ?? null,
    body: props.editor?.body ?? '',
    pdf: null,
    fields: props.editor?.fields.map((field) => ({ ...field })) ?? [],
    mappings: props.editor?.mappings.map((mapping) => ({ ...mapping })) ?? [],
});

const errors = form.errors as Record<string, string | undefined>;
const editorRef = ref<InstanceType<typeof RichTextEditor> | null>(null);
const pdfFields = ref<PdfField[]>(props.editor?.pdf_fields ?? []);
const pdfError = ref<string | null>(null);
const previewHtml = ref<string | null>(null);
const previewMissing = ref<string[]>([]);

const isPdf = computed(() => form.kind === 'pdf');
const fieldKeys = computed(() =>
    form.fields.map((field) => field.key).filter((key) => key !== ''),
);

async function choosePdf(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.pdf = file;
    pdfError.value = null;

    if (file === null) {
        return;
    }

    const body = new FormData();
    body.append('pdf', file);
    const result = await postFormData<{ fields: PdfField[] }>(
        inspectPdf.url(),
        body,
    );

    if (result.ok) {
        pdfFields.value = result.data.fields;
    } else {
        pdfError.value = result.errors.pdf ?? t('forms.pdf.unreadable');
    }
}

async function showPreview(): Promise<void> {
    const result = await postJson<{ html: string; missing: string[] }>(
        preview.url(),
        { body: form.body },
    );

    if (result.ok) {
        previewHtml.value = result.data.html;
        previewMissing.value = result.data.missing;
    }
}

function insert(token: string): void {
    editorRef.value?.insertToken(token);
}

function submit(): void {
    const options = {
        forceFormData: isPdf.value,
        queryStringArrayFormat: 'indices' as const,
    };

    if (template === null) {
        form.transform((data) => ({
            ...data,
            body: isPdf.value ? undefined : data.body,
            pdf: isPdf.value ? data.pdf : undefined,
            mappings: isPdf.value ? data.mappings : undefined,
        })).post(store.url(), options);

        return;
    }

    if (!isPdf.value) {
        form.transform((data) => ({
            type_id: data.type_id,
            name: data.name,
            body: data.body,
            fields: data.fields,
        })).put(update.url(template.id));

        return;
    }

    form.transform((data) => ({
        _method: 'put',
        type_id: data.type_id,
        name: data.name,
        pdf: data.pdf ?? undefined,
        fields: data.fields,
        mappings: data.mappings,
    })).post(update.url(template.id), options);
}
</script>

<template>
    <Head
        :title="template ? t('forms.templates.edit') : t('forms.templates.new')"
    />

    <form class="flex flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                template ? t('forms.templates.edit') : t('forms.templates.new')
            "
            :description="
                template
                    ? `${template.template_number} · ${t('forms.templates.version_note', { version: editor?.version ?? 1 })}`
                    : t('forms.templates.versioning_note')
            "
        />

        <div class="grid max-w-3xl gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label for="name">{{ t('forms.templates.name') }}</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="type_id">{{ t('forms.templates.type') }}</Label>
                <OptionSelect
                    id="type_id"
                    v-model="form.type_id"
                    :options="types"
                    required
                />
                <InputError :message="form.errors.type_id" />
            </div>
            <div class="grid gap-2">
                <Label for="kind">{{ t('forms.templates.kind') }}</Label>
                <OptionSelect
                    v-if="template === null"
                    id="kind"
                    v-model="form.kind"
                    :options="kinds"
                    required
                />
                <p v-else class="text-sm">{{ template.kind_label }}</p>
                <InputError :message="form.errors.kind" />
            </div>
        </div>

        <section class="grid gap-2">
            <Heading
                variant="small"
                :title="t('forms.fields.title')"
                :description="t('forms.fields.description')"
            />
            <FieldDefinitionsEditor
                v-model="form.fields"
                :input-types="inputTypes"
                :picklists="picklists"
                :errors="errors"
                :max="limits.fields_max"
            />
            <InputError :message="errors.fields" />
        </section>

        <section v-if="!isPdf" class="grid gap-3 lg:grid-cols-[1fr_320px]">
            <div class="grid gap-2">
                <Heading
                    variant="small"
                    :title="t('forms.templates.body')"
                    :description="t('forms.templates.body_help')"
                />
                <RichTextEditor ref="editorRef" v-model="form.body" />
                <InputError :message="form.errors.body" />
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showPreview"
                        >{{ t('forms.templates.preview') }}</Button
                    >
                </div>
                <div v-if="previewHtml !== null" class="grid gap-2">
                    <p
                        v-if="previewMissing.length"
                        class="text-sm text-destructive"
                    >
                        {{
                            t('forms.templates.missing', {
                                tokens: previewMissing.join(', '),
                            })
                        }}
                    </p>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <article
                        class="form-document rounded-md border bg-white p-6 font-serif text-[11pt] text-black"
                        v-html="previewHtml"
                    />
                </div>
            </div>
            <aside class="max-h-[640px] overflow-y-auto rounded-md border p-3">
                <TokenList
                    :groups="tokens"
                    :field-keys="fieldKeys"
                    insertable
                    @insert="insert"
                />
            </aside>
        </section>

        <section v-else class="grid gap-3">
            <Heading
                variant="small"
                :title="t('forms.pdf.title')"
                :description="
                    t('forms.pdf.help', { size: limits.pdf_max_kilobytes })
                "
            />
            <p v-if="editor?.pdf_file_name" class="text-sm">
                {{ t('forms.pdf.current', { name: editor.pdf_file_name }) }}
            </p>
            <input
                type="file"
                accept="application/pdf"
                class="text-sm"
                :aria-label="t('forms.pdf.choose')"
                @change="choosePdf"
            />
            <InputError :message="pdfError ?? form.errors.pdf" />
            <PdfMappingEditor
                v-model="form.mappings"
                :pdf-fields="pdfFields"
                :groups="tokens"
                :field-keys="fieldKeys"
            />
        </section>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">{{
                form.processing ? t('common.saving') : t('common.save')
            }}</Button>
            <Button v-if="template" type="button" variant="ghost" as-child>
                <a :href="show.url(template.id)">{{ t('common.cancel') }}</a>
            </Button>
        </div>
    </form>
</template>
