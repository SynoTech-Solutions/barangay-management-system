<script setup lang="ts">
import { reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { postJson } from '@/lib/http';
import { quickAdd } from '@/routes/residents';
import type { ResidentOption } from '@/types';

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ created: [resident: ResidentOption] }>();

const blank = () => ({
    last_name: '',
    first_name: '',
    middle_name: '',
    name_extension: '',
    birthdate: '',
});

const fields = reactive(blank());
const confirmDuplicate = ref(false);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

async function submit(): Promise<void> {
    processing.value = true;
    const result = await postJson<{ data: ResidentOption }>(quickAdd.url(), {
        ...fields,
        birthdate: fields.birthdate || null,
        confirm_duplicate: confirmDuplicate.value,
    });
    processing.value = false;

    if (!result.ok) {
        errors.value = result.errors;

        return;
    }

    emit('created', result.data.data);
    Object.assign(fields, blank());
    confirmDuplicate.value = false;
    errors.value = {};
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('quick_add.title') }}</DialogTitle>
                <DialogDescription>{{
                    t('quick_add.description')
                }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="quick_last_name">{{
                            t('residents.fields.last_name')
                        }}</Label>
                        <Input
                            id="quick_last_name"
                            v-model="fields.last_name"
                            required
                        />
                        <InputError :message="errors.last_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="quick_first_name">{{
                            t('residents.fields.first_name')
                        }}</Label>
                        <Input
                            id="quick_first_name"
                            v-model="fields.first_name"
                            required
                        />
                        <InputError :message="errors.first_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="quick_middle_name">{{
                            t('residents.fields.middle_name')
                        }}</Label>
                        <Input
                            id="quick_middle_name"
                            v-model="fields.middle_name"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="quick_name_extension">{{
                            t('residents.fields.name_extension')
                        }}</Label>
                        <Input
                            id="quick_name_extension"
                            v-model="fields.name_extension"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="quick_birthdate">{{
                            t('residents.fields.birthdate')
                        }}</Label>
                        <Input
                            id="quick_birthdate"
                            v-model="fields.birthdate"
                            type="date"
                        />
                        <InputError :message="errors.birthdate" />
                    </div>
                </div>
                <div
                    v-if="errors.duplicate"
                    class="grid gap-2 rounded-md border border-amber-500 p-3 text-sm"
                >
                    <p>{{ errors.duplicate }}</p>
                    <CheckboxField
                        v-model="confirmDuplicate"
                        :label="t('quick_add.confirm_duplicate')"
                    />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >{{ t('common.cancel') }}</Button
                    >
                    <Button type="submit" :disabled="processing">{{
                        t('quick_add.save')
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
