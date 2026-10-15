<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/composables/useI18n';
import { create, index } from '@/routes/central/barangays';
import { update as updateStatus } from '@/routes/central/barangays/status';
import { store as switchTo } from '@/routes/central/switch';
import type { BarangaySummary, Paginated } from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.barangays'), href: index() }] },
});

const props = defineProps<{
    barangays: Paginated<BarangaySummary>;
    filters: { search: string };
}>();

const search = ref(props.filters.search);

const applySearch = useDebounceFn(() => {
    router.get(
        index.url(),
        { search: search.value },
        { preserveState: true, replace: true },
    );
}, SEARCH_DELAY_MS);

function toggleStatus(barangay: BarangaySummary): void {
    router.put(
        updateStatus.url(barangay.id),
        { status: barangay.status === 'active' ? 'suspended' : 'active' },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('barangays.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="t('barangays.title')"
                :description="t('barangays.description')"
            />
            <Button as-child>
                <Link :href="create()">{{ t('barangays.new') }}</Link>
            </Button>
        </div>

        <Input
            v-model="search"
            :placeholder="t('common.search_placeholder')"
            class="max-w-sm"
            @input="applySearch"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('barangays.name') }}</th>
                        <th class="p-3">{{ t('barangays.municipality') }}</th>
                        <th class="p-3">{{ t('barangays.psgc') }}</th>
                        <th class="p-3">{{ t('barangays.status') }}</th>
                        <th class="p-3 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="barangay in barangays.data"
                        :key="barangay.id"
                        class="border-t"
                    >
                        <td class="p-3 font-medium">{{ barangay.name }}</td>
                        <td class="p-3">{{ barangay.municipality }}</td>
                        <td class="p-3 font-mono">{{ barangay.psgc_code }}</td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    barangay.status === 'active'
                                        ? 'default'
                                        : 'outline'
                                "
                            >
                                {{ t(`barangays.status_${barangay.status}`) }}
                            </Badge>
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        router.post(switchTo.url(barangay.id))
                                    "
                                    >{{ t('barangays.open') }}</Button
                                >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="toggleStatus(barangay)"
                                >
                                    {{
                                        barangay.status === 'active'
                                            ? t('barangays.suspend')
                                            : t('barangays.activate')
                                    }}
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="barangays.meta" />
    </div>
</template>
