<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportSheet from '@/components/finance/ReportSheet.vue';
import { t } from '@/composables/useI18n';
import type { PrintHeader, Vendor } from '@/types';

defineProps<{
    header: PrintHeader;
    vendors: Vendor[];
    limit: number;
    printedAt: string;
}>();
</script>

<template>
    <Head :title="t('finance.vendors.print_title')" />

    <ReportSheet
        :header="header"
        :title="t('finance.vendors.print_title')"
        :printed-at="printedAt"
        :truncated-at="vendors.length >= limit ? limit : null"
    >
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-1">{{ t('finance.vendors.name') }}</th>
                    <th class="py-1">{{ t('finance.vendors.address') }}</th>
                    <th class="py-1">
                        {{ t('finance.vendors.contact_number') }}
                    </th>
                    <th class="py-1">{{ t('finance.vendors.email') }}</th>
                    <th class="py-1">
                        {{ t('finance.vendors.contact_person') }}
                    </th>
                    <th class="py-1">{{ t('finance.vendors.position') }}</th>
                    <th class="py-1">{{ t('finance.vendors.tin') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="vendor in vendors" :key="vendor.id" class="border-b">
                    <td class="py-1">{{ vendor.name }}</td>
                    <td class="py-1">{{ vendor.address }}</td>
                    <td class="py-1">{{ vendor.contact_number }}</td>
                    <td class="py-1">{{ vendor.email }}</td>
                    <td class="py-1">{{ vendor.contact_person }}</td>
                    <td class="py-1">{{ vendor.position }}</td>
                    <td class="py-1">{{ vendor.tin }}</td>
                </tr>
            </tbody>
        </table>
    </ReportSheet>
</template>
