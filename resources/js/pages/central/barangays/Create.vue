<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { index, store } from '@/routes/central/barangays';
import { search as searchMunicipalities } from '@/routes/central/municipalities';
import type { SearchResult } from '@/types';

const SEARCH_DELAY_MS = 250;

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.barangays'), href: index() }] },
});

const municipalityQuery = ref('');
const municipalities = ref<SearchResult[]>([]);
const municipality = ref<SearchResult | null>(null);

const runSearch = useDebounceFn(async () => {
    const response = await fetch(
        searchMunicipalities.url({
            query: { search: municipalityQuery.value },
        }),
        {
            headers: { Accept: 'application/json' },
        },
    );
    municipalities.value = response.ok
        ? ((await response.json()) as { data: SearchResult[] }).data
        : [];
}, SEARCH_DELAY_MS);

function choose(result: SearchResult): void {
    municipality.value = result;
    municipalityQuery.value = result.label;
    municipalities.value = [];
}
</script>

<template>
    <Head :title="t('barangays.create_title')" />

    <Form
        v-bind="store.form()"
        v-slot="{ errors, processing }"
        class="flex max-w-2xl flex-col gap-6 p-4"
    >
        <Heading :title="t('barangays.create_title')" />

        <div class="relative grid gap-2">
            <Label for="municipality">{{ t('barangays.municipality') }}</Label>
            <Input
                id="municipality"
                v-model="municipalityQuery"
                :placeholder="t('barangays.municipality_placeholder')"
                autocomplete="off"
                @input="runSearch"
            />
            <input
                type="hidden"
                name="municipality_id"
                :value="municipality?.id ?? ''"
            />
            <ul
                v-if="municipalities.length"
                class="absolute top-full z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-background text-sm shadow"
            >
                <li v-for="result in municipalities" :key="result.id">
                    <button
                        type="button"
                        class="w-full px-3 py-1.5 text-left hover:bg-accent"
                        @click="choose(result)"
                    >
                        {{ result.label }}
                    </button>
                </li>
            </ul>
            <InputError :message="errors.municipality_id" />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="name">{{ t('barangays.name') }}</Label>
                <Input id="name" name="name" required />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="psgc_code">{{ t('barangays.psgc') }}</Label>
                <Input
                    id="psgc_code"
                    name="psgc_code"
                    inputmode="numeric"
                    required
                />
                <InputError :message="errors.psgc_code" />
            </div>
        </div>

        <fieldset class="grid gap-4 rounded-lg border p-4">
            <Heading
                variant="small"
                :title="t('barangays.first_admin')"
                :description="t('barangays.first_admin_help')"
            />
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="admin_name">{{ t('users.name') }}</Label>
                    <Input id="admin_name" name="admin[name]" />
                    <InputError :message="errors['admin.name']" />
                </div>
                <div class="grid gap-2">
                    <Label for="admin_username">{{
                        t('users.username')
                    }}</Label>
                    <Input
                        id="admin_username"
                        name="admin[username]"
                        autocapitalize="none"
                    />
                    <InputError :message="errors['admin.username']" />
                </div>
                <div class="grid gap-2">
                    <Label for="admin_password">{{
                        t('users.password')
                    }}</Label>
                    <PasswordInput
                        id="admin_password"
                        name="admin[password]"
                        autocomplete="new-password"
                    />
                    <InputError :message="errors['admin.password']" />
                </div>
                <div class="grid gap-2">
                    <Label for="admin_password_confirmation">{{
                        t('users.password_confirmation')
                    }}</Label>
                    <PasswordInput
                        id="admin_password_confirmation"
                        name="admin[password_confirmation]"
                        autocomplete="new-password"
                    />
                </div>
            </div>
        </fieldset>

        <Button type="submit" :disabled="processing" class="w-fit">{{
            t('common.save')
        }}</Button>
    </Form>
</template>
