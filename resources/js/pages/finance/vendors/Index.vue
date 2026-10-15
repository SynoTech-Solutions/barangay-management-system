<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import StatusPill from '@/components/finance/StatusPill.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import {
    deactivate,
    index,
    print,
    reactivate,
    store,
    update,
} from '@/routes/finance/vendors';
import type { Paginated, Vendor } from '@/types';

const SEARCH_DELAY_MS = 300;

const FIELDS = [
    'name',
    'address',
    'contact_number',
    'email',
    'contact_person',
    'position',
    'tin',
] as const;

const REQUIRED: ReadonlyArray<string> = ['name', 'address', 'contact_number'];

const props = defineProps<{
    vendors: Paginated<Vendor>;
    filters: { search: string };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.vendors'), href: index() }] },
});

const { can } = useAccess();
const search = ref(props.filters.search);
const editingId = ref<number | null>(null);
const form = useForm({
    name: '',
    address: '',
    contact_number: '',
    email: '',
    contact_person: '',
    position: '',
    tin: '',
});
const errors = form.errors as Record<string, string>;

const applySearch = useDebounceFn(() => {
    router.get(index.url(), search.value ? { search: search.value } : {}, {
        preserveState: true,
        replace: true,
    });
}, SEARCH_DELAY_MS);

function edit(vendor: Vendor): void {
    editingId.value = vendor.id;
    FIELDS.forEach((field) => (form[field] = vendor[field] ?? ''));
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

function toggle(vendor: Vendor): void {
    router.post(
        vendor.is_active
            ? deactivate.url(vendor.id)
            : reactivate.url(vendor.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('finance.vendors.title')" />

    <div class="flex max-w-6xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('finance.vendors.title')"
                :description="t('finance.vendors.description')"
            />
            <Button variant="outline" as-child>
                <a
                    :href="print.url({ query: search ? { search } : {} })"
                    target="_blank"
                    >{{ t('finance.print_list') }}</a
                >
            </Button>
        </div>

        <form
            v-if="can(Permission.SuppliersManage)"
            class="grid gap-4 rounded-lg border p-3"
            @submit.prevent="submit"
        >
            <div class="grid gap-4 md:grid-cols-4">
                <div v-for="field in FIELDS" :key="field" class="grid gap-2">
                    <Label :for="field">{{
                        t(`finance.vendors.${field}`)
                    }}</Label>
                    <Input
                        :id="field"
                        v-model="form[field]"
                        :required="REQUIRED.includes(field)"
                    />
                    <InputError :message="errors[field]" />
                </div>
            </div>
            <div class="flex gap-2">
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
            </div>
        </form>

        <Input
            v-model="search"
            class="max-w-sm"
            :placeholder="t('finance.vendors.search')"
            @input="applySearch"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('finance.vendors.name') }}</th>
                        <th class="p-3">{{ t('finance.vendors.address') }}</th>
                        <th class="p-3">
                            {{ t('finance.vendors.contact_number') }}
                        </th>
                        <th class="p-3">
                            {{ t('finance.vendors.contact_person') }}
                        </th>
                        <th class="p-3">{{ t('finance.vendors.tin') }}</th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="vendor in vendors.data"
                        :key="vendor.id"
                        class="border-t"
                        :class="{ 'text-muted-foreground': !vendor.is_active }"
                    >
                        <td class="p-3 font-medium">
                            {{ vendor.name }}
                            <StatusPill
                                v-if="!vendor.is_active"
                                :label="t('common.inactive')"
                                muted
                            />
                            <div
                                v-if="vendor.email"
                                class="text-xs text-muted-foreground"
                            >
                                {{ vendor.email }}
                            </div>
                        </td>
                        <td class="p-3">{{ vendor.address }}</td>
                        <td class="p-3">{{ vendor.contact_number }}</td>
                        <td class="p-3">
                            {{ vendor.contact_person }}
                            <span
                                v-if="vendor.position"
                                class="text-muted-foreground"
                                >· {{ vendor.position }}</span
                            >
                        </td>
                        <td class="p-3">{{ vendor.tin }}</td>
                        <td class="p-3">
                            <div
                                v-if="can(Permission.SuppliersManage)"
                                class="flex justify-end gap-2"
                            >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="edit(vendor)"
                                    >{{ t('common.edit') }}</Button
                                >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="toggle(vendor)"
                                    >{{
                                        vendor.is_active
                                            ? t('finance.deactivate')
                                            : t('finance.reactivate')
                                    }}</Button
                                >
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="vendors.meta" />
    </div>
</template>
