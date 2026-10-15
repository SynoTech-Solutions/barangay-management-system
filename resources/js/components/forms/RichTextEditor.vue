<script setup lang="ts">
import {
    Bold,
    Heading1,
    Heading2,
    Italic,
    List,
    ListOrdered,
    Pilcrow,
    RemoveFormatting,
    Table,
    TextAlignCenter,
    TextAlignEnd,
    TextAlignJustify,
    TextAlignStart,
    Underline,
} from '@lucide/vue';
import type { Component } from 'vue';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import { wrapToken } from '@/lib/placeholders';

type ToolbarAction = {
    key: string;
    icon: Component;
    command: string;
    value?: string;
};

const TABLE_HTML =
    '<table><tbody><tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></tbody></table><p><br></p>';

const ACTIONS: ToolbarAction[] = [
    { key: 'bold', icon: Bold, command: 'bold' },
    { key: 'italic', icon: Italic, command: 'italic' },
    { key: 'underline', icon: Underline, command: 'underline' },
    { key: 'heading_1', icon: Heading1, command: 'formatBlock', value: 'h1' },
    { key: 'heading_2', icon: Heading2, command: 'formatBlock', value: 'h2' },
    { key: 'paragraph', icon: Pilcrow, command: 'formatBlock', value: 'p' },
    { key: 'bullets', icon: List, command: 'insertUnorderedList' },
    { key: 'numbers', icon: ListOrdered, command: 'insertOrderedList' },
    { key: 'align_left', icon: TextAlignStart, command: 'justifyLeft' },
    { key: 'align_center', icon: TextAlignCenter, command: 'justifyCenter' },
    { key: 'align_right', icon: TextAlignEnd, command: 'justifyRight' },
    { key: 'align_justify', icon: TextAlignJustify, command: 'justifyFull' },
    { key: 'table', icon: Table, command: 'insertHTML', value: TABLE_HTML },
    { key: 'clear', icon: RemoveFormatting, command: 'removeFormat' },
];

const model = defineModel<string>({ required: true });
const editor = ref<HTMLDivElement | null>(null);

onMounted(() => {
    if (editor.value) {
        editor.value.innerHTML = model.value;
    }
});

function sync(): void {
    model.value = editor.value?.innerHTML ?? '';
}

function run(command: string, value?: string): void {
    editor.value?.focus();
    document.execCommand(command, false, value);
    sync();
}

function insertToken(token: string): void {
    run('insertText', wrapToken(token));
}

defineExpose({ insertToken });
</script>

<template>
    <div class="rounded-md border">
        <div class="flex flex-wrap gap-1 border-b bg-muted/40 p-1">
            <Button
                v-for="action in ACTIONS"
                :key="action.key"
                type="button"
                variant="ghost"
                size="sm"
                :title="t(`forms.editor.${action.key}`)"
                :aria-label="t(`forms.editor.${action.key}`)"
                @mousedown.prevent
                @click="run(action.command, action.value)"
            >
                <component :is="action.icon" class="size-4" />
            </Button>
        </div>
        <div
            ref="editor"
            class="form-document min-h-[320px] bg-white p-4 font-serif text-[11pt] text-black focus:outline-none"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            @input="sync"
            @blur="sync"
        />
    </div>
</template>
