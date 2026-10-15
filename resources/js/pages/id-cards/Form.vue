<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PaymentFields from '@/components/documents/PaymentFields.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { index, store, update } from '@/routes/id-cards';
import type { IdCard, ResidentOption } from '@/types';

const props = defineProps<{
    card: IdCard | null;
    resident: ResidentOption | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.id_cards'), href: index() }] },
});

const card = props.card;
const resident = ref<ResidentOption | null>(props.resident);
const form = useForm({
    ctc_number: card?.ctc_number ?? '',
    ctc_place: card?.ctc_place ?? '',
    ctc_date: card?.ctc_date ?? '',
    or_number: card?.or_number ?? '',
    amount: card?.amount ?? '0.00',
});

const errors = form.errors as Record<string, string>;

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        ctc_date: data.ctc_date || null,
        ...(card === null ? { resident_id: resident.value?.id ?? null } : {}),
    }));

    if (card === null) {
        request.post(store.url());
    } else {
        request.put(update.url(card.id));
    }
}
</script>

<template>
    <Head
        :title="card ? t('id_cards.edit_title') : t('id_cards.create_title')"
    />

    <form class="flex max-w-2xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                card ? t('id_cards.edit_title') : t('id_cards.create_title')
            "
            :description="card?.card_number"
        />

        <div class="grid gap-2">
            <Label for="resident">{{ t('id_cards.resident') }}</Label>
            <ResidentPicker
                v-if="card === null"
                id="resident"
                v-model="resident"
                allow-quick-add
                for-issuance
            />
            <p v-else class="text-sm font-medium">
                {{ resident?.full_name }} · {{ resident?.resident_number }}
            </p>
            <InputError :message="errors.resident_id" />
        </div>

        <PaymentFields
            v-model:or-number="form.or_number"
            v-model:amount="form.amount"
            v-model:ctc-number="form.ctc_number"
            v-model:ctc-place="form.ctc_place"
            v-model:ctc-date="form.ctc_date"
            :errors="errors"
            :resident-id="resident?.id ?? null"
        />

        <Button type="submit" :disabled="form.processing" class="w-fit">{{
            form.processing ? t('common.saving') : t('common.save')
        }}</Button>
    </form>
</template>
