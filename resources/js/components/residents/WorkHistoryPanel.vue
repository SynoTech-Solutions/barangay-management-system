<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { destroy, store, update } from '@/routes/residents/work-histories';
import type { ResidentWorkHistory, SelectOption } from '@/types';

const props = defineProps<{
    residentId: number;
    workHistories: ResidentWorkHistory[];
    companies: SelectOption[];
    positions: SelectOption[];
    statuses: SelectOption[];
}>();

const editingId = ref<number | null>(null);
const form = useForm<{
    company_id: number | null;
    position_id: number | null;
    employment_status_id: number | null;
    started_on: string;
    ended_on: string;
    is_current: boolean;
}>({
    company_id: null,
    position_id: null,
    employment_status_id: null,
    started_on: '',
    ended_on: '',
    is_current: false,
});

function reset(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function startEdit(work: ResidentWorkHistory): void {
    editingId.value = work.id;
    form.company_id = work.company_id;
    form.position_id = work.position_id;
    form.employment_status_id = work.employment_status_id;
    form.started_on = work.started_on ?? '';
    form.ended_on = work.ended_on ?? '';
    form.is_current = work.is_current;
}

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        started_on: data.started_on || null,
        ended_on: data.is_current ? null : data.ended_on || null,
    }));
    const options = { preserveScroll: true, onSuccess: reset };

    if (editingId.value === null) {
        request.post(store.url(props.residentId), options);
    } else {
        request.put(update.url([props.residentId, editingId.value]), options);
    }
}

function remove(work: ResidentWorkHistory): void {
    if (window.confirm(t('work.remove_confirm'))) {
        router.delete(destroy.url([props.residentId, work.id]), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <section class="grid gap-4">
        <Heading variant="small" :title="t('work.title')" />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-2">{{ t('work.company') }}</th>
                        <th class="p-2">{{ t('work.position') }}</th>
                        <th class="p-2">{{ t('work.employment_status') }}</th>
                        <th class="p-2">{{ t('work.started_on') }}</th>
                        <th class="p-2">{{ t('work.ended_on') }}</th>
                        <th class="p-2 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!workHistories.length">
                        <td colspan="6" class="p-2 text-muted-foreground">
                            {{ t('work.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="work in workHistories"
                        :key="work.id"
                        class="border-t"
                    >
                        <td class="p-2">{{ work.company }}</td>
                        <td class="p-2">{{ work.position }}</td>
                        <td class="p-2">{{ work.employment_status }}</td>
                        <td class="p-2">{{ work.started_on ?? '' }}</td>
                        <td class="p-2">
                            {{
                                work.is_current
                                    ? t('work.current')
                                    : (work.ended_on ?? '')
                            }}
                        </td>
                        <td class="p-2 text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="startEdit(work)"
                                    >{{ t('common.edit') }}</Button
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="remove(work)"
                                    >{{ t('residents.delete') }}</Button
                                >
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form
            class="grid gap-4 rounded-lg border p-4 sm:grid-cols-3"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="work_company">{{ t('work.company') }}</Label>
                <OptionSelect
                    id="work_company"
                    v-model="form.company_id"
                    :options="companies"
                    required
                />
                <InputError :message="form.errors.company_id" />
            </div>
            <div class="grid gap-2">
                <Label for="work_position">{{ t('work.position') }}</Label>
                <OptionSelect
                    id="work_position"
                    v-model="form.position_id"
                    :options="positions"
                    required
                />
                <InputError :message="form.errors.position_id" />
            </div>
            <div class="grid gap-2">
                <Label for="work_status">{{
                    t('work.employment_status')
                }}</Label>
                <OptionSelect
                    id="work_status"
                    v-model="form.employment_status_id"
                    :options="statuses"
                    required
                />
                <InputError :message="form.errors.employment_status_id" />
            </div>
            <div class="grid gap-2">
                <Label for="work_started_on">{{ t('work.started_on') }}</Label>
                <Input
                    id="work_started_on"
                    v-model="form.started_on"
                    type="date"
                />
                <InputError :message="form.errors.started_on" />
            </div>
            <div class="grid gap-2">
                <Label for="work_ended_on">{{ t('work.ended_on') }}</Label>
                <Input
                    id="work_ended_on"
                    v-model="form.ended_on"
                    type="date"
                    :disabled="form.is_current"
                />
                <InputError :message="form.errors.ended_on" />
            </div>
            <div class="flex items-end">
                <CheckboxField
                    v-model="form.is_current"
                    :label="t('work.is_current')"
                />
            </div>
            <div class="flex gap-2 sm:col-span-3">
                <Button type="submit" :disabled="form.processing">{{
                    editingId === null ? t('work.add') : t('common.save')
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
