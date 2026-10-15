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
    destroy,
    index,
    reactivate,
    store,
    update,
} from '@/routes/puroks';
import type { Purok } from '@/types';

defineProps<{ puroks: Purok[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.puroks'), href: index() }] },
});

const editingId = ref<number | null>(null);
const editingName = ref('');

function startEdit(purok: Purok): void {
    editingId.value = purok.id;
    editingName.value = purok.name;
}

function saveEdit(purok: Purok): void {
    router.put(
        update.url(purok.id),
        { name: editingName.value },
        { preserveScroll: true, onSuccess: () => (editingId.value = null) },
    );
}

function remove(purok: Purok): void {
    if (window.confirm(t('puroks.delete_confirm'))) {
        router.delete(destroy.url(purok.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('puroks.title')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <Heading
            :title="t('puroks.title')"
            :description="t('puroks.description')"
        />

        <Form
            v-bind="store.form()"
            :reset-on-success="true"
            v-slot="{ errors, processing }"
            class="flex items-start gap-2"
        >
            <div class="grid flex-1 gap-1">
                <Input
                    name="name"
                    :placeholder="t('puroks.new_value')"
                    required
                />
                <InputError :message="errors.name" />
            </div>
            <Button type="submit" :disabled="processing">{{
                t('common.add')
            }}</Button>
        </Form>

        <ul class="divide-y rounded-lg border">
            <li
                v-for="purok in puroks"
                :key="purok.id"
                class="flex items-center justify-between gap-3 p-3"
            >
                <div class="flex flex-1 items-center gap-2">
                    <Input
                        v-if="editingId === purok.id"
                        v-model="editingName"
                        class="max-w-xs"
                        @keyup.enter="saveEdit(purok)"
                    />
                    <span
                        v-else
                        :class="{ 'text-muted-foreground': !purok.is_active }"
                        >{{ purok.name }}</span
                    >
                    <Badge v-if="!purok.is_active" variant="outline">{{
                        t('common.inactive')
                    }}</Badge>
                    <span class="text-sm text-muted-foreground">{{
                        t('puroks.residents', { count: purok.residents_count })
                    }}</span>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="editingId === purok.id"
                        size="sm"
                        @click="saveEdit(purok)"
                        >{{ t('common.save') }}</Button
                    >
                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        @click="startEdit(purok)"
                        >{{ t('common.edit') }}</Button
                    >
                    <Button
                        v-if="purok.is_active"
                        size="sm"
                        variant="outline"
                        @click="
                            router.post(
                                deactivate.url(purok.id),
                                {},
                                { preserveScroll: true },
                            )
                        "
                        >{{ t('puroks.hide') }}</Button
                    >
                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        @click="
                            router.post(
                                reactivate.url(purok.id),
                                {},
                                { preserveScroll: true },
                            )
                        "
                        >{{ t('puroks.restore') }}</Button
                    >
                    <Button
                        v-if="purok.residents_count === 0"
                        size="sm"
                        variant="outline"
                        @click="remove(purok)"
                        >{{ t('puroks.delete') }}</Button
                    >
                </div>
            </li>
        </ul>
    </div>
</template>
