<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { index as outbox } from '@/routes/sms';
import {
    deactivate,
    index,
    reactivate,
    show,
    store,
    update,
} from '@/routes/sms/groups';
import { create as messageCreate } from '@/routes/sms/groups/message';
import type { SmsGroup } from '@/types';

defineProps<{ groups: SmsGroup[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.sms_outbox'), href: outbox() },
            { title: t('nav.sms_groups'), href: index() },
        ],
    },
});

const { can } = useAccess();
const editingId = ref<number | null>(null);
const editingName = ref('');

function startEdit(group: SmsGroup): void {
    editingId.value = group.id;
    editingName.value = group.name;
}

function saveEdit(group: SmsGroup): void {
    router.put(
        update.url(group.id),
        { name: editingName.value },
        { preserveScroll: true, onSuccess: () => (editingId.value = null) },
    );
}

function setActive(group: SmsGroup, active: boolean): void {
    router.post(
        active ? reactivate.url(group.id) : deactivate.url(group.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('sms_groups.title')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <Heading
            :title="t('sms_groups.title')"
            :description="t('sms_groups.description')"
        />

        <Form
            v-bind="store.form()"
            :reset-on-success="true"
            v-slot="{ errors, processing }"
            class="flex items-start gap-2"
        >
            <div class="grid flex-1 gap-1">
                <Input
                    name="name"
                    maxlength="50"
                    :placeholder="t('sms_groups.new_name')"
                    required
                />
                <InputError :message="errors.name" />
            </div>
            <Button type="submit" :disabled="processing">{{
                t('common.add')
            }}</Button>
        </Form>

        <ul class="divide-y rounded-lg border">
            <li
                v-for="group in groups"
                :key="group.id"
                class="flex flex-wrap items-center justify-between gap-3 p-3"
            >
                <div class="flex flex-1 items-center gap-2">
                    <Input
                        v-if="editingId === group.id"
                        v-model="editingName"
                        maxlength="50"
                        class="max-w-xs"
                        @keyup.enter="saveEdit(group)"
                    />
                    <Link
                        v-else
                        :href="show(group.id)"
                        class="font-medium hover:underline"
                        :class="{ 'text-muted-foreground': !group.is_active }"
                        >{{ group.name }}</Link
                    >
                    <Badge v-if="!group.is_active" variant="outline">{{
                        t('common.inactive')
                    }}</Badge>
                    <span class="text-sm text-muted-foreground">{{
                        t('sms_groups.members', {
                            count: group.members_count ?? 0,
                        })
                    }}</span>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="group.is_active && can(Permission.SmsSend)"
                        size="sm"
                        as-child
                    >
                        <Link :href="messageCreate(group.id)">{{
                            t('sms_groups.send')
                        }}</Link>
                    </Button>
                    <Button
                        v-if="editingId === group.id"
                        size="sm"
                        @click="saveEdit(group)"
                        >{{ t('common.save') }}</Button
                    >
                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        @click="startEdit(group)"
                        >{{ t('sms_groups.rename') }}</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        @click="setActive(group, !group.is_active)"
                        >{{
                            group.is_active
                                ? t('sms_groups.deactivate')
                                : t('sms_groups.reactivate')
                        }}</Button
                    >
                </div>
            </li>
            <li
                v-if="!groups.length"
                class="p-6 text-center text-sm text-muted-foreground"
            >
                {{ t('sms_groups.empty') }}
            </li>
        </ul>
    </div>
</template>
