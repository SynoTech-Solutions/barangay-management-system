<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { index, store, update } from '@/routes/admin/users';
import { reset as resetPassword } from '@/routes/admin/users/password';
import { update as updateRoles } from '@/routes/admin/users/roles';
import type { ManagedUser, Role } from '@/types';

const props = defineProps<{
    user: ManagedUser | null;
    roles: Role[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.users'), href: index() }] },
});

const selectedRoles = ref<number[]>(
    (props.user?.roles ?? []).map((role) => role.id),
);

function toggleRole(id: number, checked: boolean | 'indeterminate'): void {
    selectedRoles.value =
        checked === true
            ? [...selectedRoles.value, id]
            : selectedRoles.value.filter((value) => value !== id);
}
</script>

<template>
    <Head :title="user ? t('users.edit_title') : t('users.create_title')" />

    <div class="flex max-w-2xl flex-col gap-8 p-4">
        <Heading
            :title="user ? t('users.edit_title') : t('users.create_title')"
        />

        <Form
            v-bind="user ? update.form(user.id) : store.form()"
            :transform="
                (data) => (user ? data : { ...data, role_ids: selectedRoles })
            "
            v-slot="{ errors, processing }"
            class="grid gap-6"
        >
            <Heading variant="small" :title="t('users.profile_section')" />
            <div class="grid gap-2">
                <Label for="name">{{ t('users.name') }}</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="user?.name"
                    required
                />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="username">{{ t('users.username') }}</Label>
                <Input
                    id="username"
                    name="username"
                    :default-value="user?.username"
                    autocapitalize="none"
                    required
                />
                <InputError :message="errors.username" />
            </div>
            <div class="grid gap-2">
                <Label for="designation">{{ t('users.designation') }}</Label>
                <Input
                    id="designation"
                    name="designation"
                    :default-value="user?.designation ?? ''"
                />
                <InputError :message="errors.designation" />
            </div>
            <div class="grid gap-2">
                <Label for="email">{{ t('users.email') }}</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    :default-value="user?.email ?? ''"
                />
                <InputError :message="errors.email" />
            </div>

            <template v-if="!user">
                <div class="grid gap-2">
                    <Label for="password">{{ t('users.password') }}</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        required
                    />
                    <InputError :message="errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="password_confirmation">{{
                        t('users.password_confirmation')
                    }}</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        required
                    />
                </div>
                <div class="grid gap-3">
                    <Heading
                        variant="small"
                        :title="t('users.roles')"
                        :description="t('users.roles_help')"
                    />
                    <Label
                        v-for="role in roles"
                        :key="role.id"
                        class="flex items-center gap-3"
                    >
                        <Checkbox
                            :model-value="selectedRoles.includes(role.id)"
                            @update:model-value="
                                (checked) => toggleRole(role.id, checked)
                            "
                        />
                        <span>{{ role.label }}</span>
                    </Label>
                </div>
            </template>

            <Button type="submit" :disabled="processing" class="w-fit">{{
                processing ? t('common.saving') : t('common.save')
            }}</Button>
        </Form>

        <Form
            v-if="user"
            v-bind="updateRoles.form(user.id)"
            :transform="() => ({ role_ids: selectedRoles })"
            v-slot="{ processing }"
            class="grid gap-3"
        >
            <Heading
                variant="small"
                :title="t('users.roles')"
                :description="t('users.roles_help')"
            />
            <Label
                v-for="role in roles"
                :key="role.id"
                class="flex items-center gap-3"
            >
                <Checkbox
                    :model-value="selectedRoles.includes(role.id)"
                    @update:model-value="
                        (checked) => toggleRole(role.id, checked)
                    "
                />
                <span>{{ role.label }}</span>
            </Label>
            <Button
                type="submit"
                variant="outline"
                :disabled="processing"
                class="w-fit"
                >{{ t('users.save_roles') }}</Button
            >
        </Form>

        <Form
            v-if="user"
            v-bind="resetPassword.form(user.id)"
            :reset-on-success="true"
            v-slot="{ errors, processing }"
            class="grid gap-4"
        >
            <Heading
                variant="small"
                :title="t('users.reset_password')"
                :description="t('users.reset_password_help')"
            />
            <div class="grid gap-2">
                <Label for="reset_password">{{ t('users.password') }}</Label>
                <PasswordInput
                    id="reset_password"
                    name="password"
                    autocomplete="new-password"
                    required
                />
                <InputError :message="errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="reset_password_confirmation">{{
                    t('users.password_confirmation')
                }}</Label>
                <PasswordInput
                    id="reset_password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required
                />
            </div>
            <Button
                type="submit"
                variant="outline"
                :disabled="processing"
                class="w-fit"
                >{{ t('users.reset_password') }}</Button
            >
        </Form>
    </div>
</template>
