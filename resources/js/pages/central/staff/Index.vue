<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { index, store } from '@/routes/central/staff';
import type { ManagedUser, Paginated, Role } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.staff'), href: index() }] },
});

defineProps<{
    users: Paginated<ManagedUser>;
    roles: Role[];
    filters: { search: string };
}>();

const selectedRoles = ref<number[]>([]);

function toggleRole(id: number, checked: boolean | 'indeterminate'): void {
    selectedRoles.value =
        checked === true
            ? [...selectedRoles.value, id]
            : selectedRoles.value.filter((value) => value !== id);
}
</script>

<template>
    <Head :title="t('staff.title')" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            :title="t('staff.title')"
            :description="t('staff.description')"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('users.name') }}</th>
                        <th class="p-3">{{ t('users.username') }}</th>
                        <th class="p-3">{{ t('users.roles') }}</th>
                        <th class="p-3">{{ t('users.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-t"
                    >
                        <td class="p-3 font-medium">{{ user.name }}</td>
                        <td class="p-3 font-mono">{{ user.username }}</td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="role in user.roles ?? []"
                                    :key="role.id"
                                    variant="secondary"
                                    >{{ role.label }}</Badge
                                >
                            </div>
                        </td>
                        <td class="p-3">
                            {{
                                user.is_active
                                    ? t('common.active')
                                    : t('common.inactive')
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :meta="users.meta" />

        <Form
            v-bind="store.form()"
            :transform="(data) => ({ ...data, role_ids: selectedRoles })"
            :reset-on-success="true"
            v-slot="{ errors, processing }"
            class="grid max-w-2xl gap-4 rounded-lg border p-4"
        >
            <Heading variant="small" :title="t('staff.new')" />
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">{{ t('users.name') }}</Label>
                    <Input id="name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="username">{{ t('users.username') }}</Label>
                    <Input
                        id="username"
                        name="username"
                        autocapitalize="none"
                        required
                    />
                    <InputError :message="errors.username" />
                </div>
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
            </div>
            <div class="grid gap-2">
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
            <Button type="submit" :disabled="processing" class="w-fit">{{
                t('common.save')
            }}</Button>
        </Form>
    </div>
</template>
