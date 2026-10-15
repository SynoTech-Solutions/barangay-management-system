<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { destroy, store, update } from '@/routes/residents/educations';
import type { ResidentEducation, SelectOption } from '@/types';

const props = defineProps<{
    residentId: number;
    educations: ResidentEducation[];
    schools: SelectOption[];
    courses: SelectOption[];
}>();

const editingId = ref<number | null>(null);
const form = useForm<{
    school_id: number | null;
    course_id: number | null;
    started_on: string;
    ended_on: string;
}>({ school_id: null, course_id: null, started_on: '', ended_on: '' });

function reset(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function startEdit(education: ResidentEducation): void {
    editingId.value = education.id;
    form.school_id = education.school_id;
    form.course_id = education.course_id;
    form.started_on = education.started_on ?? '';
    form.ended_on = education.ended_on ?? '';
}

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        started_on: data.started_on || null,
        ended_on: data.ended_on || null,
    }));
    const options = { preserveScroll: true, onSuccess: reset };

    if (editingId.value === null) {
        request.post(store.url(props.residentId), options);
    } else {
        request.put(update.url([props.residentId, editingId.value]), options);
    }
}

function remove(education: ResidentEducation): void {
    if (window.confirm(t('education.remove_confirm'))) {
        router.delete(destroy.url([props.residentId, education.id]), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <section class="grid gap-4">
        <Heading variant="small" :title="t('education.title')" />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-2">{{ t('education.school') }}</th>
                        <th class="p-2">{{ t('education.course') }}</th>
                        <th class="p-2">{{ t('education.started_on') }}</th>
                        <th class="p-2">{{ t('education.ended_on') }}</th>
                        <th class="p-2 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!educations.length">
                        <td colspan="5" class="p-2 text-muted-foreground">
                            {{ t('education.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="education in educations"
                        :key="education.id"
                        class="border-t"
                    >
                        <td class="p-2">{{ education.school }}</td>
                        <td class="p-2">{{ education.course }}</td>
                        <td class="p-2">{{ education.started_on ?? '' }}</td>
                        <td class="p-2">{{ education.ended_on ?? '' }}</td>
                        <td class="p-2 text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="startEdit(education)"
                                    >{{ t('common.edit') }}</Button
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="remove(education)"
                                    >{{ t('residents.delete') }}</Button
                                >
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form
            class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="education_school">{{
                    t('education.school')
                }}</Label>
                <OptionSelect
                    id="education_school"
                    v-model="form.school_id"
                    :options="schools"
                    required
                />
                <InputError :message="form.errors.school_id" />
            </div>
            <div class="grid gap-2">
                <Label for="education_course">{{
                    t('education.course')
                }}</Label>
                <OptionSelect
                    id="education_course"
                    v-model="form.course_id"
                    :options="courses"
                    required
                />
                <InputError :message="form.errors.course_id" />
            </div>
            <div class="grid gap-2">
                <Label for="education_started_on">{{
                    t('education.started_on')
                }}</Label>
                <Input
                    id="education_started_on"
                    v-model="form.started_on"
                    type="date"
                />
                <InputError :message="form.errors.started_on" />
            </div>
            <div class="grid gap-2">
                <Label for="education_ended_on">{{
                    t('education.ended_on')
                }}</Label>
                <Input
                    id="education_ended_on"
                    v-model="form.ended_on"
                    type="date"
                />
                <InputError :message="form.errors.ended_on" />
            </div>
            <div class="flex gap-2 sm:col-span-2">
                <Button type="submit" :disabled="form.processing">{{
                    editingId === null ? t('education.add') : t('common.save')
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
