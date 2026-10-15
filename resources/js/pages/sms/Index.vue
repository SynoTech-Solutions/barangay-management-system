<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { cancel, create, index, retry, show } from '@/routes/sms';
import { index as groupsIndex } from '@/routes/sms/groups';
import { index as templatesIndex } from '@/routes/sms/templates';
import type {
    Paginated,
    SelectOption,
    SmsListFilters,
    SmsMessage,
} from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.sms_outbox'), href: index() }] },
});

const props = defineProps<{
    messages: Paginated<SmsMessage>;
    filters: SmsListFilters;
    statuses: SelectOption<string>[];
}>();

const { can, feature } = useAccess();
const state = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
const selected = ref<number[]>([]);

const query = computed(
    () =>
        Object.fromEntries(
            Object.entries(state).filter(
                ([, value]) => value !== null && value !== '',
            ),
        ) as Record<string, string>,
);

const selectedMessages = computed(() =>
    props.messages.data.filter((message) =>
        selected.value.includes(message.id),
    ),
);
const canCancelSelection = computed(
    () =>
        can(Permission.SmsCancel) &&
        selectedMessages.value.length > 0 &&
        selectedMessages.value.every((message) => message.can_cancel),
);
const canRetrySelection = computed(
    () =>
        can(Permission.SmsSend) &&
        selectedMessages.value.length > 0 &&
        selectedMessages.value.every((message) => message.can_retry),
);
const selectable = computed(
    () => can(Permission.SmsCancel) || can(Permission.SmsSend),
);

function apply(): void {
    selected.value = [];
    router.get(index.url(), query.value, {
        preserveState: true,
        replace: true,
    });
}

const applySearch = useDebounceFn(apply, SEARCH_DELAY_MS);

function toggle(id: number, checked: boolean): void {
    selected.value = checked
        ? [...selected.value, id]
        : selected.value.filter((value) => value !== id);
}

function cancelSelected(): void {
    if (
        window.confirm(
            t('sms.cancel_confirm', { count: selected.value.length }),
        )
    ) {
        router.post(
            cancel.url(),
            { ids: selected.value },
            { preserveScroll: true, onSuccess: () => (selected.value = []) },
        );
    }
}

function retrySelected(): void {
    router.post(
        retry.url(),
        { ids: selected.value },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

function statusVariant(
    message: SmsMessage,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (message.status === 'failed') {
        return 'destructive';
    }

    if (message.status === 'sent') {
        return 'secondary';
    }

    return message.status === 'cancelled' ? 'outline' : 'default';
}
</script>

<template>
    <Head :title="t('sms.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('sms.title')"
                :description="t('sms.description')"
            />
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="
                        can(Permission.SmsTemplatesManage) &&
                        feature(FeatureFlag.SmsTemplates)
                    "
                    variant="outline"
                    as-child
                >
                    <Link :href="templatesIndex()">{{
                        t('nav.sms_templates')
                    }}</Link>
                </Button>
                <Button
                    v-if="
                        can(Permission.SmsGroupsManage) &&
                        feature(FeatureFlag.SmsGroups)
                    "
                    variant="outline"
                    as-child
                >
                    <Link :href="groupsIndex()">{{ t('nav.sms_groups') }}</Link>
                </Button>
                <Button v-if="can(Permission.SmsSend)" as-child>
                    <Link :href="create()">{{ t('sms.new') }}</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-4">
            <Input
                v-model="state.search"
                :placeholder="t('sms.search_placeholder')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('sms.all_statuses')"
                @update:model-value="apply"
            />
            <Input
                v-model="state.from"
                type="date"
                :aria-label="t('sms.from')"
                @change="apply"
            />
            <Input
                v-model="state.to"
                type="date"
                :aria-label="t('sms.to')"
                @change="apply"
            />
        </div>

        <div
            v-if="selected.length"
            class="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/40 p-3 text-sm"
        >
            <span>{{ t('sms.selected', { count: selected.length }) }}</span>
            <Button
                v-if="can(Permission.SmsCancel)"
                size="sm"
                variant="outline"
                :disabled="!canCancelSelection"
                @click="cancelSelected"
                >{{ t('sms.cancel') }}</Button
            >
            <Button
                v-if="can(Permission.SmsSend)"
                size="sm"
                :disabled="!canRetrySelection"
                @click="retrySelected"
                >{{ t('sms.retry') }}</Button
            >
            <span class="text-muted-foreground">{{ t('sms.bulk_hint') }}</span>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th v-if="selectable" class="w-8 p-3" />
                        <th class="p-3">{{ t('sms.recipient') }}</th>
                        <th class="p-3">{{ t('sms.message') }}</th>
                        <th class="p-3">{{ t('sms.queued_at') }}</th>
                        <th class="p-3">{{ t('sms.sent_by') }}</th>
                        <th class="p-3">{{ t('sms.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="message in messages.data"
                        :key="message.id"
                        class="border-t"
                    >
                        <td v-if="selectable" class="p-3">
                            <CheckboxField
                                v-if="message.can_cancel || message.can_retry"
                                :model-value="selected.includes(message.id)"
                                label=""
                                @update:model-value="
                                    (checked: boolean) =>
                                        toggle(message.id, checked)
                                "
                            />
                        </td>
                        <td class="p-3">
                            <Link
                                :href="show(message.id)"
                                class="font-medium hover:underline"
                                >{{
                                    message.resident?.full_name ??
                                    message.recipient ??
                                    t('sms.pruned')
                                }}</Link
                            >
                            <div
                                v-if="message.resident && message.recipient"
                                class="text-muted-foreground"
                            >
                                {{ message.recipient }}
                            </div>
                        </td>
                        <td class="max-w-md p-3">
                            <span class="line-clamp-2">{{
                                message.body ?? t('sms.pruned')
                            }}</span>
                        </td>
                        <td class="p-3">
                            {{ new Date(message.queued_at).toLocaleString() }}
                        </td>
                        <td class="p-3">{{ message.queued_by ?? '' }}</td>
                        <td class="p-3">
                            <Badge :variant="statusVariant(message)">{{
                                message.status_label
                            }}</Badge>
                            <div
                                v-if="message.last_error_code"
                                class="text-xs text-muted-foreground"
                            >
                                {{ message.last_error_code }}
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!messages.data.length">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            {{ t('sms.empty') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="messages.meta" />
    </div>
</template>
