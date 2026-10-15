<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/composables/useI18n';
import { index as templatesIndex } from '@/routes/forms/templates';
import { store, update } from '@/routes/forms/picklists';
import {
    store as storeItem,
    update as updateItem,
} from '@/routes/forms/picklists/items';
import type { FormPicklist } from '@/types';

defineProps<{ picklists: FormPicklist[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.form_templates'), href: templatesIndex() },
        ],
    },
});

const newList = useForm({ name: '' });
const newItems = reactive<Record<number, string>>({});

function createList(): void {
    newList.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => newList.reset(),
    });
}

function renameList(list: FormPicklist, name: string, active: boolean): void {
    router.put(
        update.url(list.id),
        { name, is_active: active },
        { preserveScroll: true },
    );
}

function addItem(list: FormPicklist): void {
    const label = (newItems[list.id] ?? '').trim();

    if (label === '') {
        return;
    }

    router.post(
        storeItem.url(list.id),
        { label },
        {
            preserveScroll: true,
            onSuccess: () => (newItems[list.id] = ''),
        },
    );
}

function saveItem(
    list: FormPicklist,
    item: FormPicklist['items'][number],
    label: string,
    active: boolean,
): void {
    router.put(
        updateItem.url({ picklist: list.id, item: item.id }),
        { label, is_active: active },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('forms.picklists.title')" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            :title="t('forms.picklists.title')"
            :description="t('forms.picklists.description')"
        />

        <form class="flex items-start gap-2" @submit.prevent="createList">
            <div class="grid flex-1 gap-1">
                <Input
                    v-model="newList.name"
                    :placeholder="t('forms.picklists.new_name')"
                    :aria-label="t('forms.picklists.new_name')"
                />
                <InputError :message="newList.errors.name" />
            </div>
            <Button type="submit" :disabled="newList.processing">{{
                t('forms.picklists.add_list')
            }}</Button>
        </form>

        <p v-if="!picklists.length" class="text-sm text-muted-foreground">
            {{ t('forms.picklists.empty') }}
        </p>

        <section
            v-for="list in picklists"
            :key="list.id"
            class="grid gap-3 rounded-lg border p-3"
        >
            <div class="flex flex-wrap items-center gap-2">
                <Input
                    :model-value="list.name"
                    class="max-w-sm font-medium"
                    :aria-label="t('forms.picklists.name')"
                    @change="
                        renameList(
                            list,
                            ($event.target as HTMLInputElement).value,
                            list.is_active,
                        )
                    "
                />
                <Badge :variant="list.is_active ? 'default' : 'outline'">{{
                    list.is_active ? t('common.active') : t('common.inactive')
                }}</Badge>
                <Button
                    size="sm"
                    variant="outline"
                    @click="renameList(list, list.name, !list.is_active)"
                    >{{
                        list.is_active
                            ? t('forms.picklists.hide')
                            : t('forms.picklists.restore')
                    }}</Button
                >
            </div>
            <ul class="grid gap-1 text-sm">
                <li
                    v-for="item in list.items"
                    :key="item.id"
                    class="flex items-center gap-2"
                >
                    <Input
                        :model-value="item.label"
                        :class="{ 'line-through': !item.is_active }"
                        :aria-label="t('forms.picklists.item')"
                        @change="
                            saveItem(
                                list,
                                item,
                                ($event.target as HTMLInputElement).value,
                                item.is_active,
                            )
                        "
                    />
                    <Button
                        size="sm"
                        variant="ghost"
                        @click="
                            saveItem(list, item, item.label, !item.is_active)
                        "
                        >{{
                            item.is_active
                                ? t('forms.picklists.hide')
                                : t('forms.picklists.restore')
                        }}</Button
                    >
                </li>
            </ul>
            <form class="flex gap-2" @submit.prevent="addItem(list)">
                <Input
                    v-model="newItems[list.id]"
                    :placeholder="t('forms.picklists.new_item')"
                    :aria-label="t('forms.picklists.new_item')"
                />
                <Button type="submit" variant="outline">{{
                    t('common.add')
                }}</Button>
            </form>
        </section>
    </div>
</template>
