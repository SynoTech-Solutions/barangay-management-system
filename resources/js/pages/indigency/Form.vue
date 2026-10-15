<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { index, store, update } from '@/routes/indigency-certificates';
import type {
    IndigencyCertificate,
    ResidentOption,
    SelectOption,
} from '@/types';

const props = defineProps<{
    certificate: IndigencyCertificate | null;
    resident: ResidentOption | null;
    lookups: Record<string, SelectOption[]>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.indigency'), href: index() }] },
});

const certificate = props.certificate;
const resident = ref<ResidentOption | null>(props.resident);
const form = useForm({ purpose_id: certificate?.purpose_id ?? null });

const errors = form.errors as Record<string, string>;

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        ...(certificate === null
            ? { resident_id: resident.value?.id ?? null }
            : {}),
    }));

    if (certificate === null) {
        request.post(store.url());
    } else {
        request.put(update.url(certificate.id));
    }
}
</script>

<template>
    <Head
        :title="
            certificate
                ? t('indigency.edit_title')
                : t('indigency.create_title')
        "
    />

    <form class="flex max-w-2xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                certificate
                    ? t('indigency.edit_title')
                    : t('indigency.create_title')
            "
            :description="certificate?.certificate_number"
        />

        <div class="grid gap-2">
            <Label for="resident">{{ t('clearances.resident') }}</Label>
            <ResidentPicker
                v-if="certificate === null"
                id="resident"
                v-model="resident"
            />
            <p v-else class="text-sm font-medium">
                {{ resident?.full_name }} · {{ resident?.resident_number }}
            </p>
            <InputError :message="errors.resident_id" />
        </div>

        <div class="grid gap-2">
            <Label for="purpose_id">{{ t('clearances.purpose') }}</Label>
            <OptionSelect
                id="purpose_id"
                v-model="form.purpose_id"
                :options="lookups['certificate-purpose'] ?? []"
                required
            />
            <InputError :message="form.errors.purpose_id" />
        </div>

        <Button type="submit" :disabled="form.processing" class="w-fit">{{
            form.processing ? t('common.saving') : t('common.save')
        }}</Button>
    </form>
</template>
