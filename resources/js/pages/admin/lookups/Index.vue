<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/composables/useI18n';
import {
    deactivate,
    index,
    reactivate,
    store,
    update,
} from '@/routes/admin/lookups';
import type { LookupValue, Option } from '@/types';

const props = defineProps<{
    type: string;
    types: Option[];
    values: LookupValue[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.lookups'), href: '#' }] },
});

const editingId = ref<number | null>(null);
const editingLabel = ref('');

function startEdit(value: LookupValue): void {
    editingId.value = value.id;
    editingLabel.value = value.label;
}

function saveEdit(value: LookupValue): void {
    router.put(
        update.url([props.type, value.id]),
        { label: editingLabel.value },
        {
            preserveScroll: true,
            onSuccess: () => (editingId.value = null),
        },
    );
}
</script>

<template>
    <Head :title="t('lookups.title')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <Heading
            :title="t('lookups.title')"
            :description="t('lookups.description')"
        />

        <label class="grid max-w-sm gap-2 text-sm font-medium">
            {{ t('lookups.type') }}
            <select
                class="h-9 rounded-md border bg-background px-3"
                :value="type"
                @change="
                    router.get(
                        index.url(($event.target as HTMLSelectElement).value),
                    )
                "
            >
                <option
                    v-for="option in types"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </label>

        <Form
            v-bind="store.form(type)"
            :reset-on-success="true"
            v-slot="{ errors, processing }"
            class="flex items-start gap-2"
        >
            <div class="grid flex-1 gap-1">
                <Input
                    name="label"
                    :placeholder="t('lookups.new_value')"
                    required
                />
                <InputError :message="errors.label" />
            </div>
            <Button type="submit" :disabled="processing">{{
                t('common.add')
            }}</Button>
        </Form>

        <ul class="divide-y rounded-lg border">
            <li
                v-for="value in values"
                :key="value.id"
                class="flex items-center justify-between gap-3 p-3"
            >
                <div class="flex flex-1 items-center gap-2">
                    <Input
                        v-if="editingId === value.id"
                        v-model="editingLabel"
                        class="h-8"
                        @keyup.enter="saveEdit(value)"
                    />
                    <span
                        v-else
                        :class="
                            value.is_active
                                ? ''
                                : 'text-muted-foreground line-through'
                        "
                        >{{ value.label }}</span
                    >
                    <Badge v-if="value.is_global" variant="secondary">{{
                        t('lookups.shared')
                    }}</Badge>
                </div>
                <div v-if="!value.is_global" class="flex gap-1">
                    <Button
                        v-if="editingId === value.id"
                        size="sm"
                        @click="saveEdit(value)"
                        >{{ t('common.save') }}</Button
                    >
                    <Button
                        v-else
                        size="sm"
                        variant="ghost"
                        @click="startEdit(value)"
                        >{{ t('common.edit') }}</Button
                    >
                    <Button
                        v-if="value.is_active"
                        size="sm"
                        variant="outline"
                        @click="
                            router.post(
                                deactivate.url([type, value.id]),
                                {},
                                { preserveScroll: true },
                            )
                        "
                    >
                        {{ t('lookups.hide') }}
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        @click="
                            router.post(
                                reactivate.url([type, value.id]),
                                {},
                                { preserveScroll: true },
                            )
                        "
                    >
                        {{ t('lookups.restore') }}
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
