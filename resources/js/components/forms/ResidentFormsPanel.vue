<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Link } from '@inertiajs/vue3';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { create, edit, print } from '@/routes/forms/transactions';
import type { FormTransaction } from '@/types';

defineProps<{ forms: FormTransaction[]; residentId: number }>();

const { can, feature } = useAccess();
</script>

<template>
    <div class="grid gap-2">
        <div
            v-if="
                can(Permission.FormTransactionsCreate) &&
                feature(FeatureFlag.FormsEngine)
            "
        >
            <Button size="sm" as-child>
                <Link :href="create({ query: { resident_id: residentId } })">{{
                    t('forms.transactions.new')
                }}</Link>
            </Button>
        </div>
        <table class="w-full rounded-lg border text-sm">
            <thead class="bg-muted/50 text-left">
                <tr>
                    <th class="p-2">{{ t('forms.transactions.number') }}</th>
                    <th class="p-2">{{ t('forms.transactions.template') }}</th>
                    <th class="p-2">{{ t('documents.status') }}</th>
                    <th class="p-2">{{ t('documents.issued_at') }}</th>
                    <th class="p-2 text-right">{{ t('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!forms.length">
                    <td colspan="5" class="p-2 text-muted-foreground">
                        {{ t('forms.transactions.empty') }}
                    </td>
                </tr>
                <tr v-for="form in forms" :key="form.id" class="border-t">
                    <td class="p-2 font-mono">
                        {{ form.transaction_number }}
                    </td>
                    <td class="p-2">{{ form.template }}</td>
                    <td class="p-2">
                        <DocumentStatusBadge
                            :status="form.status"
                            :label="form.status_label"
                            :reason="form.cancel_reason"
                        />
                    </td>
                    <td class="p-2">
                        {{ new Date(form.issued_at).toLocaleDateString() }}
                    </td>
                    <td class="p-2 text-right">
                        <div
                            v-if="can(Permission.FormTransactionsCreate)"
                            class="flex justify-end gap-2"
                        >
                            <Button
                                v-if="form.is_pending"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link :href="edit(form.id)">{{
                                    t('common.edit')
                                }}</Link>
                            </Button>
                            <Button
                                v-if="form.status !== 'cancelled'"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <a :href="print.url(form.id)" target="_blank">{{
                                    t('documents.print')
                                }}</a>
                            </Button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
