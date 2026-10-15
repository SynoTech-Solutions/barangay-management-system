<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { edit, update } from '@/routes/admin/barangay-profile';
import { update as updateSignatory } from '@/routes/admin/barangay-profile/signatories';
import type { Signatory } from '@/types';

type Profile = {
    telephone: string | null;
    email: string | null;
    id_card_ordinance_no: string | null;
    clearance_validity_mode: string;
    clearance_validity_months: number | null;
};

const props = defineProps<{
    profile: Profile;
    validityModes: string[];
    signatories: Signatory[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.barangay_profile'), href: edit() }],
    },
});

const validityMode = ref(props.profile.clearance_validity_mode);
</script>

<template>
    <Head :title="t('profile.title')" />

    <div class="flex max-w-3xl flex-col gap-10 p-4">
        <Form
            v-bind="update.form()"
            v-slot="{ errors, processing }"
            class="grid gap-6"
        >
            <Heading
                :title="t('profile.title')"
                :description="t('profile.description')"
            />
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="telephone">{{ t('profile.telephone') }}</Label>
                    <Input
                        id="telephone"
                        name="telephone"
                        :default-value="profile.telephone ?? ''"
                    />
                    <InputError :message="errors.telephone" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">{{ t('profile.email') }}</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        :default-value="profile.email ?? ''"
                    />
                    <InputError :message="errors.email" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="id_card_ordinance_no">{{
                    t('profile.ordinance')
                }}</Label>
                <Input
                    id="id_card_ordinance_no"
                    name="id_card_ordinance_no"
                    :default-value="profile.id_card_ordinance_no ?? ''"
                />
                <InputError :message="errors.id_card_ordinance_no" />
            </div>
            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    {{ t('profile.validity_mode') }}
                </legend>
                <label
                    v-for="mode in validityModes"
                    :key="mode"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="validityMode"
                        type="radio"
                        name="clearance_validity_mode"
                        :value="mode"
                    />
                    {{ t(`profile.validity_${mode}`) }}
                </label>
                <div
                    v-if="validityMode === 'months'"
                    class="grid max-w-40 gap-2"
                >
                    <Label for="clearance_validity_months">{{
                        t('profile.validity_months_count')
                    }}</Label>
                    <Input
                        id="clearance_validity_months"
                        name="clearance_validity_months"
                        type="number"
                        min="1"
                        :default-value="
                            profile.clearance_validity_months ?? undefined
                        "
                    />
                </div>
                <InputError :message="errors.clearance_validity_months" />
            </fieldset>
            <Button type="submit" :disabled="processing" class="w-fit">{{
                t('common.save')
            }}</Button>
        </Form>

        <section class="grid gap-4">
            <Heading
                :title="t('profile.signatories_title')"
                :description="t('profile.signatories_description')"
            />
            <Form
                v-for="signatory in signatories"
                :key="signatory.office"
                v-bind="updateSignatory.form(signatory.office)"
                v-slot="{ errors, processing }"
                class="grid gap-3 rounded-lg border p-4"
            >
                <h4 class="font-medium">{{ signatory.office_label }}</h4>
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="`${signatory.office}-name`">{{
                            t('profile.signatory_name')
                        }}</Label>
                        <Input
                            :id="`${signatory.office}-name`"
                            name="name"
                            :default-value="signatory.name ?? ''"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`${signatory.office}-title`">{{
                            t('profile.signatory_title')
                        }}</Label>
                        <Input
                            :id="`${signatory.office}-title`"
                            name="title"
                            :default-value="signatory.title ?? ''"
                        />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label :for="`${signatory.office}-signature`">{{
                        t('profile.signature')
                    }}</Label>
                    <Input
                        :id="`${signatory.office}-signature`"
                        name="signature"
                        type="file"
                        accept="image/png,image/jpeg"
                    />
                    <InputError :message="errors.signature" />
                    <Label
                        v-if="signatory.has_signature"
                        class="flex items-center gap-2 text-sm"
                    >
                        <Checkbox name="remove_signature" value="1" />
                        {{ t('profile.remove_signature') }}
                    </Label>
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    :disabled="processing"
                    class="w-fit"
                    >{{ t('common.save') }}</Button
                >
            </Form>
        </section>
    </div>
</template>
