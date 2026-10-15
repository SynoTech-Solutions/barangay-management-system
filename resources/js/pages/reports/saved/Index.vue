<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { create, edit, index, show } from '@/routes/reports/saved';
import type { SavedReport } from '@/types';

defineProps<{ reports: SavedReport[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.saved_reports'), href: index() }],
    },
});

const { can } = useAccess();
</script>

<template>
    <Head :title="t('saved_reports.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('saved_reports.title')"
                :description="t('saved_reports.description')"
            />
            <Button v-if="can(Permission.ReportsDesign)" as-child>
                <Link :href="create()">{{ t('saved_reports.new') }}</Link>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('saved_reports.name') }}</th>
                        <th class="p-3">{{ t('saved_reports.dataset') }}</th>
                        <th class="p-3">{{ t('saved_reports.updated') }}</th>
                        <th class="p-3 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!reports.length">
                        <td colspan="4" class="p-3 text-muted-foreground">
                            {{ t('saved_reports.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="report in reports"
                        :key="report.id"
                        class="border-t"
                    >
                        <td class="p-3">
                            <Link
                                :href="show(report.id)"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ report.name }}</Link
                            >
                        </td>
                        <td class="p-3">{{ report.dataset_label }}</td>
                        <td class="p-3">
                            {{
                                report.updated_at
                                    ? new Date(
                                          report.updated_at,
                                      ).toLocaleString()
                                    : ''
                            }}
                        </td>
                        <td class="p-3 text-right">
                            <Button
                                v-if="can(Permission.ReportsDesign)"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link :href="edit(report.id)">{{
                                    t('common.edit')
                                }}</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
