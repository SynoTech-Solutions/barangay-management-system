<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Link, router } from '@inertiajs/vue3';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { cancel, edit, print } from '@/routes/clearances';
import type { BarangayClearance } from '@/types';

defineProps<{ clearances: BarangayClearance[] }>();

const { can } = useAccess();

function cancelClearance(clearance: BarangayClearance): void {
    const reason = window.prompt(
        t('clearances.cancel_prompt', { number: clearance.clearance_number }),
    );

    if (reason !== null && reason.trim() !== '') {
        router.post(
            cancel.url(),
            { ids: [clearance.id], reason },
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <table class="w-full rounded-lg border text-sm">
        <thead class="bg-muted/50 text-left">
            <tr>
                <th class="p-2">{{ t('clearances.number') }}</th>
                <th class="p-2">{{ t('clearances.type') }}</th>
                <th class="p-2">{{ t('documents.status') }}</th>
                <th class="p-2">{{ t('documents.issued_at') }}</th>
                <th class="p-2">{{ t('documents.expires_on') }}</th>
                <th class="p-2 text-right">{{ t('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr v-if="!clearances.length">
                <td colspan="6" class="p-2 text-muted-foreground">
                    {{ t('clearances.empty') }}
                </td>
            </tr>
            <tr
                v-for="clearance in clearances"
                :key="clearance.id"
                class="border-t"
            >
                <td class="p-2 font-mono">{{ clearance.clearance_number }}</td>
                <td class="p-2">
                    {{ clearance.clearance_type }}
                    <div class="text-muted-foreground">
                        {{ clearance.purpose }}
                    </div>
                </td>
                <td class="p-2">
                    <DocumentStatusBadge
                        :status="clearance.status"
                        :label="clearance.status_label"
                        :reason="clearance.cancel_reason"
                    />
                </td>
                <td class="p-2">
                    {{ new Date(clearance.issued_at).toLocaleDateString() }}
                </td>
                <td class="p-2">{{ clearance.expires_on }}</td>
                <td class="p-2">
                    <div class="flex justify-end gap-2">
                        <Button
                            v-if="
                                clearance.is_pending &&
                                can(Permission.ClearancesIssue)
                            "
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <Link :href="edit(clearance.id)">{{
                                t('common.edit')
                            }}</Link>
                        </Button>
                        <Button
                            v-if="
                                clearance.status !== 'cancelled' &&
                                can(Permission.ClearancesIssue)
                            "
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <a
                                :href="print.url(clearance.id)"
                                target="_blank"
                                >{{ t('documents.print') }}</a
                            >
                        </Button>
                        <Button
                            v-if="
                                clearance.is_pending &&
                                can(Permission.ClearancesCancel)
                            "
                            variant="outline"
                            size="sm"
                            @click="cancelClearance(clearance)"
                            >{{ t('documents.cancel') }}</Button
                        >
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</template>
