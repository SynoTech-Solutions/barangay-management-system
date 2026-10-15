<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import ResidentFacts from '@/components/residents/ResidentFacts.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { create as newClearance } from '@/routes/business-clearances';
import { edit, index, status } from '@/routes/businesses';
import type { Business, BusinessClearance } from '@/types';

const props = defineProps<{
    business: Business;
    clearances: BusinessClearance[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.businesses'), href: index() }] },
});

const { can, feature } = useAccess();
const b = props.business;

const facts = computed<Array<[string, string | number | null]>>(() => [
    [t('businesses.owner'), b.owner_display_name],
    [t('businesses.category'), b.category ?? null],
    [t('businesses.nature'), b.nature ?? null],
    [t('businesses.ownership_type'), b.ownership_type ?? null],
    [t('businesses.purok'), b.purok ?? null],
    [t('businesses.street_address'), b.street_address],
    [t('businesses.plate_number'), b.plate_number],
    [t('businesses.operating_since'), b.operating_since],
    [t('businesses.capitalization'), b.capitalization],
    [t('businesses.gross_receipts'), b.gross_receipts],
]);

function toggleStatus(): void {
    router.post(status.url(b.id), {
        status: b.is_active ? 'inactive' : 'active',
    });
}
</script>

<template>
    <Head :title="business.name" />

    <div class="flex max-w-5xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid gap-2">
                <Heading
                    :title="business.name"
                    :description="business.business_number"
                />
                <Badge
                    :variant="business.is_active ? 'default' : 'outline'"
                    class="w-fit"
                    >{{ business.status_label }}</Badge
                >
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="can(Permission.BusinessesManage)" as-child>
                    <Link :href="edit(business.id)">{{
                        t('common.edit')
                    }}</Link>
                </Button>
                <Button
                    v-if="can(Permission.BusinessesManage)"
                    variant="outline"
                    @click="toggleStatus"
                    >{{
                        business.is_active
                            ? t('businesses.mark_inactive')
                            : t('businesses.mark_active')
                    }}</Button
                >
                <Button
                    v-if="
                        business.is_active &&
                        feature(FeatureFlag.BusinessClearance) &&
                        can(Permission.ClearancesIssue)
                    "
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="
                            newClearance({
                                query: { business_id: business.id },
                            })
                        "
                        >{{ t('business_clearances.new') }}</Link
                    >
                </Button>
            </div>
        </div>

        <ResidentFacts :facts="facts" />

        <section
            v-if="
                feature(FeatureFlag.BusinessClearance) &&
                can(Permission.ClearancesView)
            "
            class="grid gap-3"
        >
            <Heading
                variant="small"
                :title="t('business_clearances.history')"
            />
            <table class="w-full rounded-lg border text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-2">{{ t('clearances.number') }}</th>
                        <th class="p-2">{{ t('documents.status') }}</th>
                        <th class="p-2">{{ t('documents.issued_at') }}</th>
                        <th class="p-2">{{ t('documents.expires_on') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!clearances.length">
                        <td colspan="4" class="p-2 text-muted-foreground">
                            {{ t('business_clearances.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="clearance in clearances"
                        :key="clearance.id"
                        class="border-t"
                    >
                        <td class="p-2 font-mono">
                            {{ clearance.clearance_number }}
                        </td>
                        <td class="p-2">
                            <DocumentStatusBadge
                                :status="clearance.status"
                                :label="clearance.status_label"
                                :expired="clearance.is_expired"
                            />
                        </td>
                        <td class="p-2">
                            {{
                                new Date(
                                    clearance.issued_at,
                                ).toLocaleDateString()
                            }}
                        </td>
                        <td class="p-2">{{ clearance.expires_on }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
