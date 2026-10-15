<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { t } from '@/composables/useI18n';
import { index } from '@/routes/admin/audit';
import type { AuditEvent, Paginated } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.audit'), href: index() }] },
});

defineProps<{
    events: Paginated<AuditEvent>;
}>();

function describeChanges(event: AuditEvent): string {
    if (!event.changes?.after) {
        return '';
    }

    return Object.entries(event.changes.after)
        .map(
            ([key, value]) =>
                `${key}: ${String(event.changes?.before?.[key] ?? '∅')} → ${String(value ?? '∅')}`,
        )
        .join('; ');
}
</script>

<template>
    <Head :title="t('audit.title')" />

    <div class="flex flex-col gap-4 p-4">
        <Heading
            :title="t('audit.title')"
            :description="t('audit.description')"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('audit.when') }}</th>
                        <th class="p-3">{{ t('audit.who') }}</th>
                        <th class="p-3">{{ t('audit.action') }}</th>
                        <th class="p-3">{{ t('audit.subject') }}</th>
                        <th class="p-3">{{ t('audit.changes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="event in events.data"
                        :key="event.id"
                        class="border-t align-top"
                    >
                        <td class="p-3 whitespace-nowrap">
                            {{ new Date(event.created_at).toLocaleString() }}
                        </td>
                        <td class="p-3">
                            {{ event.actor ?? t('audit.system') }}
                        </td>
                        <td class="p-3">{{ event.action_label }}</td>
                        <td class="p-3 font-mono text-xs">
                            {{ event.subject_type }} {{ event.subject_id }}
                        </td>
                        <td class="p-3 text-xs text-muted-foreground">
                            {{ describeChanges(event) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="events.meta" />
    </div>
</template>
