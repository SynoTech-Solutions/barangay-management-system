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
import { index, store, update } from '@/routes/collections';
import type { OtherCollection, ResidentOption, SelectOption } from '@/types';

type PayerMode = 'resident' | 'text';

const props = defineProps<{
    collection: OtherCollection | null;
    payer: ResidentOption | null;
    types: SelectOption[];
    today: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.collections'), href: index() }] },
});

const collection = props.collection;
const payer = ref<ResidentOption | null>(props.payer);
const payerMode = ref<PayerMode>(
    collection !== null && collection.resident_id === null
        ? 'text'
        : 'resident',
);
const form = useForm({
    collection_type_id: collection?.collection_type_id ?? null,
    payer_name: collection?.resident_id === null ? collection.payer_name : '',
    or_number: collection?.or_number ?? '',
    amount: collection?.amount ?? '',
    paid_on: collection?.paid_on ?? props.today,
    remarks: collection?.remarks ?? '',
});
const errors = form.errors as Record<string, string>;

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        resident_id:
            payerMode.value === 'resident' ? (payer.value?.id ?? null) : null,
        payer_name: payerMode.value === 'text' ? data.payer_name : null,
    }));

    if (collection === null) {
        request.post(store.url());
    } else {
        request.put(update.url(collection.id));
    }
}
</script>

<template>
    <Head
        :title="collection ? t('collections.edit_title') : t('collections.new')"
    />

    <form class="flex max-w-2xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                collection ? t('collections.edit_title') : t('collections.new')
            "
            :description="collection?.collection_number"
        />

        <div class="grid gap-2">
            <Label for="collection_type_id">{{ t('collections.type') }}</Label>
            <OptionSelect
                id="collection_type_id"
                v-model="form.collection_type_id"
                :options="types"
                required
            />
            <InputError :message="errors.collection_type_id" />
        </div>

        <div class="grid gap-2">
            <Label>{{ t('collections.payer') }}</Label>
            <div class="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    :variant="payerMode === 'resident' ? 'default' : 'outline'"
                    @click="payerMode = 'resident'"
                    >{{ t('collections.payer_resident') }}</Button
                >
                <Button
                    type="button"
                    size="sm"
                    :variant="payerMode === 'text' ? 'default' : 'outline'"
                    @click="payerMode = 'text'"
                    >{{ t('collections.payer_text') }}</Button
                >
            </div>
            <ResidentPicker
                v-if="payerMode === 'resident'"
                id="payer"
                v-model="payer"
            />
            <Input v-else id="payer_name" v-model="form.payer_name" />
            <InputError :message="errors.resident_id ?? errors.payer_name" />
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label for="or_number">{{ t('collections.or_number') }}</Label>
                <Input id="or_number" v-model="form.or_number" required />
                <InputError :message="errors.or_number" />
            </div>
            <div class="grid gap-2">
                <Label for="amount">{{ t('collections.amount') }}</Label>
                <Input
                    id="amount"
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                />
                <InputError :message="errors.amount" />
            </div>
            <div class="grid gap-2">
                <Label for="paid_on">{{ t('collections.paid_on') }}</Label>
                <Input
                    id="paid_on"
                    v-model="form.paid_on"
                    type="date"
                    :max="today"
                    required
                />
                <InputError :message="errors.paid_on" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="remarks">{{ t('collections.remarks') }}</Label>
            <Input id="remarks" v-model="form.remarks" />
            <InputError :message="errors.remarks" />
        </div>

        <div>
            <Button type="submit" :disabled="form.processing">{{
                t('common.save')
            }}</Button>
        </div>
    </form>
</template>
