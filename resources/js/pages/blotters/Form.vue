<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import BlotterPartiesEditor from '@/components/documents/BlotterPartiesEditor.vue';
import type { PartyDraft } from '@/components/documents/BlotterPartiesEditor.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { index, store, update } from '@/routes/blotters';
import type { Blotter, BlotterParty, SelectOption } from '@/types';

const DATETIME_LOCAL_LENGTH = 16;

const props = defineProps<{
    blotter: Blotter | null;
    lookups: Record<string, SelectOption[]>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.blotters'), href: index() }] },
});

const blotter = props.blotter;

function draft(party: BlotterParty): PartyDraft {
    return {
        mode: party.resident ? 'resident' : 'text',
        resident: party.resident ? { ...party.resident, purok: null } : null,
        name: party.name ?? '',
        address: party.address ?? '',
    };
}

function localDateTime(iso: string | null): string {
    const date = iso ? new Date(iso) : new Date();
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset)
        .toISOString()
        .slice(0, DATETIME_LOCAL_LENGTH);
}

const complainants = ref<PartyDraft[]>(
    blotter?.parties.filter((p) => p.role === 'complainant').map(draft) ?? [
        { mode: 'resident', resident: null, name: '', address: '' },
    ],
);
const respondents = ref<PartyDraft[]>(
    blotter?.parties.filter((p) => p.role === 'respondent').map(draft) ?? [],
);

const form = useForm({
    incident_type_id: blotter?.incident_type_id ?? null,
    incident_at: localDateTime(blotter?.incident_at ?? null),
    place: blotter?.place ?? '',
    narrative: blotter?.narrative ?? '',
});

const errors = form.errors as Record<string, string>;

function payload(parties: PartyDraft[]) {
    return parties.map((party) =>
        party.mode === 'resident'
            ? { resident_id: party.resident?.id ?? null }
            : { name: party.name, address: party.address },
    );
}

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        complainants: payload(complainants.value),
        respondents: payload(respondents.value),
    }));

    if (blotter === null) {
        request.post(store.url());
    } else {
        request.put(update.url(blotter.id));
    }
}
</script>

<template>
    <Head
        :title="blotter ? t('blotters.edit_title') : t('blotters.create_title')"
    />

    <form class="flex max-w-3xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="
                blotter ? t('blotters.edit_title') : t('blotters.create_title')
            "
            :description="blotter?.blotter_number ?? t('blotters.create_help')"
        />

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label for="incident_type_id">{{
                    t('blotters.incident_type')
                }}</Label>
                <OptionSelect
                    id="incident_type_id"
                    v-model="form.incident_type_id"
                    :options="lookups['blotter-incident-type'] ?? []"
                    required
                />
                <InputError :message="form.errors.incident_type_id" />
            </div>
            <div class="grid gap-2">
                <Label for="incident_at">{{ t('blotters.incident_at') }}</Label>
                <Input
                    id="incident_at"
                    v-model="form.incident_at"
                    type="datetime-local"
                    required
                />
                <InputError :message="form.errors.incident_at" />
            </div>
            <div class="grid gap-2">
                <Label for="place">{{ t('blotters.place') }}</Label>
                <Input id="place" v-model="form.place" />
                <InputError :message="form.errors.place" />
            </div>
        </div>

        <BlotterPartiesEditor
            v-model="complainants"
            :title="t('blotters.complainants')"
            group="complainants"
            :errors="errors"
        />
        <BlotterPartiesEditor
            v-model="respondents"
            :title="t('blotters.respondents')"
            group="respondents"
            :errors="errors"
        />

        <div class="grid gap-2">
            <Label for="narrative">{{ t('blotters.narrative') }}</Label>
            <textarea
                id="narrative"
                v-model="form.narrative"
                rows="8"
                required
                class="w-full rounded-md border bg-background p-3 text-sm"
            />
            <InputError :message="form.errors.narrative" />
        </div>

        <Button type="submit" :disabled="form.processing" class="w-fit">{{
            form.processing ? t('common.saving') : t('common.save')
        }}</Button>
    </form>
</template>
