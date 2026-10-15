<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { create, expired, index } from '@/routes/business-clearances';
import type { Business, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.business_clearances'), href: index() },
            { title: t('nav.expired_businesses'), href: expired() },
        ],
    },
});

defineProps<{ businesses: Paginated<Business> }>();

const { can } = useAccess();
</script>

<template>
    <Head :title="t('business_clearances.expired_title')" />

    <div class="flex flex-col gap-4 p-4">
        <Heading
            :title="t('business_clearances.expired_title')"
            :description="t('business_clearances.expired_description')"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('businesses.number') }}</th>
                        <th class="p-3">{{ t('businesses.name') }}</th>
                        <th class="p-3">{{ t('businesses.category') }}</th>
                        <th class="p-3">{{ t('businesses.owner') }}</th>
                        <th class="p-3">
                            {{ t('business_clearances.expired_on') }}
                        </th>
                        <th class="p-3 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!businesses.data.length">
                        <td colspan="6" class="p-3 text-muted-foreground">
                            {{ t('business_clearances.expired_empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="business in businesses.data"
                        :key="business.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">
                            {{ business.business_number }}
                        </td>
                        <td class="p-3 font-medium">{{ business.name }}</td>
                        <td class="p-3">{{ business.category }}</td>
                        <td class="p-3">{{ business.owner_display_name }}</td>
                        <td class="p-3">{{ business.last_expires_on }}</td>
                        <td class="p-3 text-right">
                            <Button
                                v-if="can(Permission.ClearancesIssue)"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="
                                        create({
                                            query: { business_id: business.id },
                                        })
                                    "
                                    >{{ t('business_clearances.renew') }}</Link
                                >
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="businesses.meta" />
    </div>
</template>
