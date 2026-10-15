<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/composables/useI18n';
import type { ResidentOption } from '@/types';

export type PartyDraft = {
    mode: 'resident' | 'text';
    resident: ResidentOption | null;
    name: string;
    address: string;
};

const props = defineProps<{
    title: string;
    group: string;
    errors: Record<string, string | undefined>;
}>();

const parties = defineModel<PartyDraft[]>({ required: true });

function add(): void {
    parties.value = [
        ...parties.value,
        { mode: 'resident', resident: null, name: '', address: '' },
    ];
}

function remove(position: number): void {
    parties.value = parties.value.filter((_, index) => index !== position);
}

function error(position: number, field: string): string | undefined {
    return props.errors[`${props.group}.${position}.${field}`];
}
</script>

<template>
    <section class="grid gap-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">{{ title }}</h3>
            <Button type="button" size="sm" variant="outline" @click="add">{{
                t('blotters.add_party')
            }}</Button>
        </div>
        <InputError :message="errors[group]" />
        <div
            v-for="(party, position) in parties"
            :key="position"
            class="grid gap-2 rounded-md border p-3"
        >
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    :variant="party.mode === 'resident' ? 'default' : 'outline'"
                    @click="party.mode = 'resident'"
                    >{{ t('blotters.party_resident') }}</Button
                >
                <Button
                    type="button"
                    size="sm"
                    :variant="party.mode === 'text' ? 'default' : 'outline'"
                    @click="party.mode = 'text'"
                    >{{ t('blotters.party_text') }}</Button
                >
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="ml-auto"
                    @click="remove(position)"
                    >{{ t('blotters.remove_party') }}</Button
                >
            </div>
            <ResidentPicker
                v-if="party.mode === 'resident'"
                v-model="party.resident"
                include-deceased
                allow-quick-add
            />
            <div v-else class="grid gap-2 md:grid-cols-2">
                <Input
                    v-model="party.name"
                    :placeholder="t('blotters.party_name')"
                />
                <Input
                    v-model="party.address"
                    :placeholder="t('blotters.party_address')"
                />
            </div>
            <InputError
                :message="
                    error(position, 'resident_id') ?? error(position, 'name')
                "
            />
        </div>
    </section>
</template>
