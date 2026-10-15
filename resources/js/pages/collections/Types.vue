<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusPill from '@/components/finance/StatusPill.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/composables/useI18n';
import { index as collectionsIndex } from '@/routes/collections';
import {
    deactivate,
    index,
    reactivate,
    store,
    update,
} from '@/routes/collections/types';
import type { CollectionType } from '@/types';

defineProps<{ types: CollectionType[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.collections'), href: collectionsIndex() },
            { title: t('collections.types.title'), href: index() },
        ],
    },
});

const editingId = ref<number | null>(null);
const form = useForm({ name: '' });

function edit(type: CollectionType): void {
    editingId.value = type.id;
    form.name = type.name;
}

function reset(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: reset };

    if (editingId.value === null) {
        form.post(store.url(), options);
    } else {
        form.put(update.url(editingId.value), options);
    }
}

function toggle(type: CollectionType): void {
    router.post(
        type.is_active ? deactivate.url(type.id) : reactivate.url(type.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('collections.types.title')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <Heading
            :title="t('collections.types.title')"
            :description="t('collections.types.description')"
        />

        <form class="flex flex-wrap items-start gap-2" @submit.prevent="submit">
            <div class="grid flex-1 gap-1">
                <Input
                    v-model="form.name"
                    :aria-label="t('collections.types.name')"
                    :placeholder="t('collections.types.name')"
                    required
                />
                <InputError :message="form.errors.name" />
            </div>
            <Button type="submit" :disabled="form.processing">{{
                editingId === null ? t('common.add') : t('common.save')
            }}</Button>
            <Button
                v-if="editingId !== null"
                type="button"
                variant="outline"
                @click="reset"
                >{{ t('common.cancel') }}</Button
            >
        </form>

        <ul class="divide-y rounded-lg border">
            <li
                v-for="type in types"
                :key="type.id"
                class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
            >
                <div>
                    <span class="font-medium">{{ type.name }}</span>
                    <StatusPill
                        v-if="!type.is_active"
                        :label="t('common.inactive')"
                        muted
                    />
                    <div class="text-muted-foreground">
                        {{
                            t('collections.types.usage', {
                                count: type.collections_count,
                            })
                        }}
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button size="sm" variant="outline" @click="edit(type)">{{
                        t('common.edit')
                    }}</Button>
                    <Button size="sm" variant="outline" @click="toggle(type)">{{
                        type.is_active
                            ? t('finance.deactivate')
                            : t('finance.reactivate')
                    }}</Button>
                </div>
            </li>
        </ul>
    </div>
</template>
