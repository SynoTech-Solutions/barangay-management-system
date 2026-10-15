<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { create, index, show } from '@/routes/businesses';
import type {
    Business,
    BusinessFormOptions,
    BusinessListFilters,
    Paginated,
} from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.businesses'), href: index() }] },
});

const props = defineProps<{
    businesses: Paginated<Business>;
    filters: BusinessListFilters;
    options: BusinessFormOptions;
}>();

const { can } = useAccess();
const state = reactive({ ...props.filters });

const query = computed(
    () =>
        Object.fromEntries(
            Object.entries(state).filter(
                ([, value]) => value !== null && value !== '',
            ),
        ) as Record<string, string>,
);

function apply(): void {
    router.get(index.url(), query.value, {
        preserveState: true,
        replace: true,
    });
}

const applySearch = useDebounceFn(apply, SEARCH_DELAY_MS);
</script>

<template>
    <Head :title="t('businesses.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('businesses.title')"
                :description="t('businesses.description')"
            />
            <Button v-if="can(Permission.BusinessesManage)" as-child>
                <Link :href="create()">{{ t('businesses.new') }}</Link>
            </Button>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-3">
            <Input
                v-model="state.search"
                :placeholder="t('businesses.search_placeholder')"
                @input="applySearch"
            />
            <OptionSelect
                v-model="state.status"
                :options="options.statuses"
                :placeholder="t('businesses.all_statuses')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.category_id"
                :options="options.lookups['business-category'] ?? []"
                :placeholder="t('businesses.all_categories')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.nature_id"
                :options="options.lookups['business-nature'] ?? []"
                :placeholder="t('businesses.all_natures')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.ownership_type_id"
                :options="options.lookups['business-ownership-type'] ?? []"
                :placeholder="t('businesses.all_ownership_types')"
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.purok_id"
                :options="options.puroks"
                :placeholder="t('businesses.all_puroks')"
                @update:model-value="apply"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('businesses.number') }}</th>
                        <th class="p-3">{{ t('businesses.name') }}</th>
                        <th class="p-3">{{ t('businesses.owner') }}</th>
                        <th class="p-3">{{ t('businesses.category') }}</th>
                        <th class="p-3">{{ t('businesses.purok') }}</th>
                        <th class="p-3">{{ t('businesses.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="business in businesses.data"
                        :key="business.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">
                            {{ business.business_number }}
                        </td>
                        <td class="p-3">
                            <Link
                                :href="show(business.id)"
                                class="font-medium hover:underline"
                                >{{ business.name }}</Link
                            >
                            <div class="text-muted-foreground">
                                {{ business.nature }}
                            </div>
                        </td>
                        <td class="p-3">{{ business.owner_display_name }}</td>
                        <td class="p-3">{{ business.category }}</td>
                        <td class="p-3">{{ business.purok ?? '' }}</td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    business.is_active ? 'default' : 'outline'
                                "
                                >{{ business.status_label }}</Badge
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="businesses.meta" />
    </div>
</template>
