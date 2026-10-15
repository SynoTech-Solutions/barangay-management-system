<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { destroy, store, update } from '@/routes/residents/family-members';
import type { FamilyMember, ResidentOption, SelectOption } from '@/types';

type Mode = 'free' | 'linked';

const props = defineProps<{
    residentId: number;
    members: FamilyMember[];
    relationships: SelectOption[];
}>();

const { feature } = useAccess();
const editingId = ref<number | null>(null);
const mode = ref<Mode>('free');
const linked = ref<ResidentOption | null>(null);
const form = useForm<{
    relationship_id: number | null;
    full_name: string;
    birthdate: string;
}>({ relationship_id: null, full_name: '', birthdate: '' });

function reset(): void {
    editingId.value = null;
    mode.value = 'free';
    linked.value = null;
    form.reset();
    form.clearErrors();
}

function startEdit(member: FamilyMember): void {
    editingId.value = member.id;
    mode.value = member.related_resident ? 'linked' : 'free';
    linked.value = member.related_resident;
    form.relationship_id = member.relationship_id;
    form.full_name = member.related_resident ? '' : member.full_name;
    form.birthdate = member.related_resident ? '' : (member.birthdate ?? '');
}

function submit(): void {
    const isLinked = mode.value === 'linked';
    const request = form.transform((data) => ({
        relationship_id: data.relationship_id,
        full_name: isLinked ? null : data.full_name,
        birthdate: isLinked ? null : data.birthdate || null,
        related_resident_id: isLinked ? (linked.value?.id ?? null) : null,
    }));
    const options = { preserveScroll: true, onSuccess: reset };

    if (editingId.value === null) {
        request.post(store.url(props.residentId), options);
    } else {
        request.put(update.url([props.residentId, editingId.value]), options);
    }
}

function remove(member: FamilyMember): void {
    if (window.confirm(t('family.remove_confirm'))) {
        router.delete(destroy.url([props.residentId, member.id]), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <section class="grid gap-4">
        <Heading variant="small" :title="t('family.title')" />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-2">{{ t('family.name') }}</th>
                        <th class="p-2">{{ t('family.relationship') }}</th>
                        <th class="p-2">{{ t('family.age') }}</th>
                        <th class="p-2 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!members.length">
                        <td colspan="4" class="p-2 text-muted-foreground">
                            {{ t('family.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="member in members"
                        :key="member.id"
                        class="border-t"
                    >
                        <td class="p-2">
                            {{ member.full_name }}
                            <span
                                v-if="member.related_resident"
                                class="block text-muted-foreground"
                                >{{
                                    member.related_resident.resident_number
                                }}</span
                            >
                        </td>
                        <td class="p-2">{{ member.relationship }}</td>
                        <td class="p-2">{{ member.age ?? '' }}</td>
                        <td class="p-2 text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="startEdit(member)"
                                    >{{ t('common.edit') }}</Button
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="remove(member)"
                                    >{{ t('residents.delete') }}</Button
                                >
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form class="grid gap-4 rounded-lg border p-4" @submit.prevent="submit">
            <div class="grid gap-2 sm:max-w-xs">
                <Label for="family_relationship">{{
                    t('family.relationship')
                }}</Label>
                <OptionSelect
                    id="family_relationship"
                    v-model="form.relationship_id"
                    :options="relationships"
                    required
                />
                <InputError :message="form.errors.relationship_id" />
            </div>

            <div
                v-if="feature(FeatureFlag.FamilyMemberLinking)"
                class="flex gap-2"
            >
                <Button
                    type="button"
                    size="sm"
                    :variant="mode === 'free' ? 'default' : 'outline'"
                    @click="mode = 'free'"
                    >{{ t('family.mode_free') }}</Button
                >
                <Button
                    type="button"
                    size="sm"
                    :variant="mode === 'linked' ? 'default' : 'outline'"
                    @click="mode = 'linked'"
                    >{{ t('family.mode_linked') }}</Button
                >
            </div>

            <div v-if="mode === 'linked'" class="grid gap-2">
                <Label for="family_linked">{{ t('family.linked') }}</Label>
                <ResidentPicker
                    id="family_linked"
                    v-model="linked"
                    :exclude-id="residentId"
                    include-deceased
                    allow-quick-add
                />
                <InputError
                    :message="
                        (form.errors as Record<string, string>)
                            .related_resident_id
                    "
                />
            </div>
            <div v-else class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="family_name">{{ t('family.name') }}</Label>
                    <Input id="family_name" v-model="form.full_name" />
                    <InputError :message="form.errors.full_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="family_birthdate">{{
                        t('family.birthdate')
                    }}</Label>
                    <Input
                        id="family_birthdate"
                        v-model="form.birthdate"
                        type="date"
                    />
                    <InputError :message="form.errors.birthdate" />
                </div>
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">{{
                    editingId === null ? t('family.add') : t('common.save')
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
    </section>
</template>
