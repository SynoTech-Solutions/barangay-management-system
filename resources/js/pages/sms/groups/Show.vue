<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { index as outbox } from '@/routes/sms';
import { index } from '@/routes/sms/groups';
import { destroy, store } from '@/routes/sms/groups/members';
import { create as messageCreate } from '@/routes/sms/groups/message';
import type { ResidentOption, SmsGroup, SmsGroupMember } from '@/types';

const props = defineProps<{ group: SmsGroup; members: SmsGroupMember[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.sms_outbox'), href: outbox() },
            { title: t('nav.sms_groups'), href: index() },
        ],
    },
});

const { can } = useAccess();
const picked = ref<ResidentOption | null>(null);
const numberForm = useForm({ mobile_number: '', label: '' });

watch(picked, (option) => {
    if (option) {
        router.post(
            store.url(props.group.id),
            { resident_id: option.id },
            { preserveScroll: true, onFinish: () => (picked.value = null) },
        );
    }
});

function addNumber(): void {
    numberForm.post(store.url(props.group.id), {
        preserveScroll: true,
        onSuccess: () => numberForm.reset(),
    });
}

function remove(member: SmsGroupMember): void {
    if (window.confirm(t('sms_groups.remove_confirm'))) {
        router.delete(
            destroy.url({ group: props.group.id, member: member.id }),
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head :title="group.name" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="group.name"
                :description="
                    t('sms_groups.members', { count: group.members_count ?? 0 })
                "
            />
            <Button v-if="group.is_active && can(Permission.SmsSend)" as-child>
                <Link :href="messageCreate(group.id)">{{
                    t('sms_groups.send')
                }}</Link>
            </Button>
        </div>

        <p v-if="!group.is_active" class="text-sm text-destructive">
            {{ t('sms_groups.inactive_notice') }}
        </p>

        <div v-if="group.is_active" class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="member_picker">{{
                    t('sms_groups.add_resident')
                }}</Label>
                <ResidentPicker
                    id="member_picker"
                    v-model="picked"
                    with-mobile
                />
            </div>
            <form class="grid gap-2" @submit.prevent="addNumber">
                <Label for="mobile_number">{{
                    t('sms_groups.add_number')
                }}</Label>
                <div class="flex gap-2">
                    <Input
                        id="mobile_number"
                        v-model="numberForm.mobile_number"
                        :placeholder="t('sms.number_placeholder')"
                    />
                    <Input
                        v-model="numberForm.label"
                        :placeholder="t('sms_groups.label')"
                        maxlength="100"
                    />
                    <Button type="submit" :disabled="numberForm.processing">{{
                        t('common.add')
                    }}</Button>
                </div>
                <InputError :message="numberForm.errors.mobile_number" />
            </form>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('sms_groups.member') }}</th>
                        <th class="p-3">{{ t('sms.recipient') }}</th>
                        <th class="p-3">{{ t('sms.status') }}</th>
                        <th class="p-3 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="member in members"
                        :key="member.id"
                        class="border-t"
                    >
                        <td class="p-3">
                            {{
                                member.resident?.full_name ??
                                member.label ??
                                t('sms_groups.typed_number')
                            }}
                        </td>
                        <td class="p-3">{{ member.number ?? '' }}</td>
                        <td class="p-3">
                            <Badge
                                v-if="member.is_reachable"
                                variant="secondary"
                                >{{ t('sms_groups.reachable') }}</Badge
                            >
                            <Badge v-else variant="outline">{{
                                member.resident?.is_removed
                                    ? t('sms_groups.removed')
                                    : member.resident?.is_deceased
                                      ? t('sms_groups.deceased')
                                      : t('sms_groups.no_number')
                            }}</Badge>
                        </td>
                        <td class="p-3 text-right">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="remove(member)"
                                >{{ t('sms_groups.remove') }}</Button
                            >
                        </td>
                    </tr>
                    <tr v-if="!members.length">
                        <td
                            colspan="4"
                            class="p-6 text-center text-muted-foreground"
                        >
                            {{ t('sms_groups.no_members') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Button variant="link" class="self-start px-0" as-child>
            <Link :href="index()">{{ t('sms_groups.back') }}</Link>
        </Button>
    </div>
</template>
