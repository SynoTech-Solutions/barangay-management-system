<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import {
    deactivate,
    index,
    reactivate,
    store,
    update,
} from '@/routes/political-tags/categories';

type Category = {
    id: number;
    name: string;
    color_hex: string;
    is_active: boolean;
    tags_count: number;
};

const DEFAULT_COLOR = '#2563eb';

defineProps<{ categories: Category[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.color_categories'), href: index() }],
    },
});

const editingId = ref<number | null>(null);
const form = useForm({ name: '', color_hex: DEFAULT_COLOR });

function startEdit(category: Category): void {
    editingId.value = category.id;
    form.name = category.name;
    form.color_hex = category.color_hex;
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
</script>

<template>
    <Head :title="t('color_categories.title')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <Heading
            :title="t('color_categories.title')"
            :description="t('color_categories.description')"
        />

        <form class="flex flex-wrap items-end gap-3" @submit.prevent="submit">
            <div class="grid flex-1 gap-2">
                <Label for="category_name">{{
                    t('color_categories.name')
                }}</Label>
                <Input id="category_name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="category_color">{{
                    t('color_categories.color')
                }}</Label>
                <input
                    id="category_color"
                    v-model="form.color_hex"
                    type="color"
                    class="h-9 w-16 rounded-md border"
                />
                <InputError :message="form.errors.color_hex" />
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
                v-for="category in categories"
                :key="category.id"
                class="flex items-center justify-between gap-3 p-3"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="inline-block size-4 rounded-full border"
                        :style="{ backgroundColor: category.color_hex }"
                    />
                    <span>{{ category.name }}</span>
                    <Badge v-if="!category.is_active" variant="outline">{{
                        t('common.inactive')
                    }}</Badge>
                    <span class="text-sm text-muted-foreground">{{
                        t('color_categories.tagged', {
                            count: category.tags_count,
                        })
                    }}</span>
                </div>
                <div class="flex gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        @click="startEdit(category)"
                        >{{ t('common.edit') }}</Button
                    >
                    <Button
                        v-if="category.is_active"
                        size="sm"
                        variant="outline"
                        @click="
                            router.post(
                                deactivate.url(category.id),
                                {},
                                { preserveScroll: true },
                            )
                        "
                        >{{ t('color_categories.hide') }}</Button
                    >
                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        @click="
                            router.post(
                                reactivate.url(category.id),
                                {},
                                { preserveScroll: true },
                            )
                        "
                        >{{ t('color_categories.restore') }}</Button
                    >
                </div>
            </li>
        </ul>
    </div>
</template>
