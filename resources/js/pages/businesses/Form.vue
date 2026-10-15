<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { index, store, update } from '@/routes/businesses';
import type { Business, BusinessFormOptions, ResidentOption } from '@/types';

type OwnerMode = 'resident' | 'text';

const props = defineProps<{
    business: Business | null;
    owner: ResidentOption | null;
    options: BusinessFormOptions;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.businesses'), href: index() }] },
});

const business = props.business;
const owner = ref<ResidentOption | null>(props.owner);
const ownerMode = ref<OwnerMode>(
    business !== null && business.owner_resident_id === null
        ? 'text'
        : 'resident',
);
const form = useForm({
    name: business?.name ?? '',
    owner_name: business?.owner_name ?? '',
    category_id: business?.category_id ?? null,
    nature_id: business?.nature_id ?? null,
    ownership_type_id: business?.ownership_type_id ?? null,
    purok_id: business?.purok_id ?? null,
    street_address: business?.street_address ?? '',
    plate_number: business?.plate_number ?? '',
    operating_since: business?.operating_since ?? '',
    capitalization: business?.capitalization ?? '',
    gross_receipts: business?.gross_receipts ?? '',
});

const errors = form.errors as Record<string, string>;

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        owner_resident_id:
            ownerMode.value === 'resident' ? (owner.value?.id ?? null) : null,
        owner_name: ownerMode.value === 'text' ? data.owner_name : null,
        operating_since: data.operating_since || null,
        capitalization: data.capitalization || null,
        gross_receipts: data.gross_receipts || null,
    }));

    if (business === null) {
        request.post(store.url());
    } else {
        request.put(update.url(business.id));
    }
}
</script>

<template>
    <Head
        :title="
            business ? t('businesses.edit_title') : t('businesses.create_title')
        "
    />

    <form class="flex max-w-3xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                business
                    ? t('businesses.edit_title')
                    : t('businesses.create_title')
            "
            :description="business?.business_number"
        />

        <div class="grid gap-2">
            <Label for="name">{{ t('businesses.name') }}</Label>
            <Input id="name" v-model="form.name" required />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label>{{ t('businesses.owner') }}</Label>
            <div class="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    :variant="ownerMode === 'resident' ? 'default' : 'outline'"
                    @click="ownerMode = 'resident'"
                    >{{ t('businesses.owner_resident') }}</Button
                >
                <Button
                    type="button"
                    size="sm"
                    :variant="ownerMode === 'text' ? 'default' : 'outline'"
                    @click="ownerMode = 'text'"
                    >{{ t('businesses.owner_text') }}</Button
                >
            </div>
            <ResidentPicker
                v-if="ownerMode === 'resident'"
                id="owner"
                v-model="owner"
            />
            <Input v-else id="owner_name" v-model="form.owner_name" />
            <InputError
                :message="errors.owner_resident_id ?? errors.owner_name"
            />
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label for="category_id">{{ t('businesses.category') }}</Label>
                <OptionSelect
                    id="category_id"
                    v-model="form.category_id"
                    :options="options.lookups['business-category'] ?? []"
                    required
                />
                <InputError :message="form.errors.category_id" />
            </div>
            <div class="grid gap-2">
                <Label for="nature_id">{{ t('businesses.nature') }}</Label>
                <OptionSelect
                    id="nature_id"
                    v-model="form.nature_id"
                    :options="options.lookups['business-nature'] ?? []"
                    required
                />
                <InputError :message="form.errors.nature_id" />
            </div>
            <div class="grid gap-2">
                <Label for="ownership_type_id">{{
                    t('businesses.ownership_type')
                }}</Label>
                <OptionSelect
                    id="ownership_type_id"
                    v-model="form.ownership_type_id"
                    :options="options.lookups['business-ownership-type'] ?? []"
                    required
                />
                <InputError :message="form.errors.ownership_type_id" />
            </div>
            <div class="grid gap-2">
                <Label for="purok_id">{{ t('businesses.purok') }}</Label>
                <OptionSelect
                    id="purok_id"
                    v-model="form.purok_id"
                    :options="options.puroks"
                />
                <InputError :message="form.errors.purok_id" />
            </div>
            <div class="grid gap-2 md:col-span-2">
                <Label for="street_address">{{
                    t('businesses.street_address')
                }}</Label>
                <Input id="street_address" v-model="form.street_address" />
                <InputError :message="form.errors.street_address" />
            </div>
            <div class="grid gap-2">
                <Label for="plate_number">{{
                    t('businesses.plate_number')
                }}</Label>
                <Input id="plate_number" v-model="form.plate_number" />
                <InputError :message="form.errors.plate_number" />
            </div>
            <div class="grid gap-2">
                <Label for="operating_since">{{
                    t('businesses.operating_since')
                }}</Label>
                <Input
                    id="operating_since"
                    v-model="form.operating_since"
                    type="date"
                />
                <InputError :message="form.errors.operating_since" />
            </div>
            <div class="grid gap-2">
                <Label for="capitalization">{{
                    t('businesses.capitalization')
                }}</Label>
                <Input
                    id="capitalization"
                    v-model="form.capitalization"
                    type="number"
                    step="0.01"
                    min="0"
                />
                <InputError :message="form.errors.capitalization" />
            </div>
            <div class="grid gap-2">
                <Label for="gross_receipts">{{
                    t('businesses.gross_receipts')
                }}</Label>
                <Input
                    id="gross_receipts"
                    v-model="form.gross_receipts"
                    type="number"
                    step="0.01"
                    min="0"
                />
                <InputError :message="form.errors.gross_receipts" />
            </div>
        </div>

        <Button type="submit" :disabled="form.processing" class="w-fit">{{
            form.processing ? t('common.saving') : t('common.save')
        }}</Button>
    </form>
</template>
