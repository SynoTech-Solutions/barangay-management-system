<script setup lang="ts">
import { Permission, FeatureFlag } from '@/contracts/generated';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import QuickAddDialog from '@/components/residents/QuickAddDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { getJson } from '@/lib/http';
import { search } from '@/routes/residents';
import type { Paginated, ResidentOption } from '@/types';

const SEARCH_DELAY_MS = 250;

const props = withDefaults(
    defineProps<{
        id?: string;
        includeDeceased?: boolean;
        excludeId?: number | null;
        allowQuickAdd?: boolean;
        forIssuance?: boolean;
        withMobile?: boolean;
        placeholder?: string;
    }>(),
    {
        id: undefined,
        includeDeceased: false,
        excludeId: null,
        allowQuickAdd: false,
        forIssuance: false,
        withMobile: false,
        placeholder: undefined,
    },
);

const model = defineModel<ResidentOption | null>({ required: true });

const { can, feature } = useAccess();
const query = ref('');
const results = ref<ResidentOption[]>([]);
const searched = ref(false);
const quickAddOpen = ref(false);

const runSearch = useDebounceFn(async () => {
    if (query.value.trim() === '') {
        results.value = [];
        searched.value = false;

        return;
    }

    const page = await getJson<Paginated<ResidentOption>>(
        search.url({
            query: {
                q: query.value,
                include_deceased: props.includeDeceased,
                exclude_id: props.excludeId ?? undefined,
                for_issuance: props.forIssuance ? 1 : undefined,
                with_mobile: props.withMobile ? 1 : undefined,
            },
        }),
    );
    results.value = page?.data ?? [];
    searched.value = true;
}, SEARCH_DELAY_MS);

function choose(option: ResidentOption): void {
    model.value = option;
    query.value = '';
    results.value = [];
    searched.value = false;
}

function describe(option: ResidentOption): string {
    return [option.resident_number, option.birthdate, option.purok]
        .filter((part) => part)
        .join(' · ');
}
</script>

<template>
    <div class="grid gap-2">
        <div
            v-if="model"
            class="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm"
        >
            <div>
                <div class="font-medium">
                    {{ model.full_name }}
                    <Badge v-if="model.on_hold" variant="destructive">{{
                        t('picker.on_hold')
                    }}</Badge>
                </div>
                <div class="text-muted-foreground">{{ describe(model) }}</div>
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="model = null"
            >
                {{ t('picker.clear') }}
            </Button>
        </div>
        <div v-else class="relative">
            <Input
                :id="id"
                v-model="query"
                :placeholder="placeholder ?? t('picker.placeholder')"
                autocomplete="off"
                @input="runSearch"
            />
            <ul
                v-if="results.length || searched"
                class="absolute top-full z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border bg-background text-sm shadow"
            >
                <li v-for="option in results" :key="option.id">
                    <button
                        type="button"
                        class="w-full px-3 py-1.5 text-left hover:bg-accent"
                        @click="choose(option)"
                    >
                        <span class="font-medium">{{ option.full_name }}</span>
                        <Badge
                            v-if="option.on_hold"
                            variant="destructive"
                            class="ml-1"
                            >{{ t('picker.on_hold') }}</Badge
                        >
                        <span
                            v-if="option.is_deceased"
                            class="ml-1 text-muted-foreground"
                            >({{ t('picker.deceased') }})</span
                        >
                        <span class="block text-muted-foreground">{{
                            describe(option)
                        }}</span>
                    </button>
                </li>
                <li
                    v-if="searched && !results.length"
                    class="px-3 py-1.5 text-muted-foreground"
                >
                    {{ t('picker.no_results') }}
                </li>
            </ul>
        </div>
        <div
            v-if="
                !model &&
                allowQuickAdd &&
                can(Permission.ResidentsCreate) &&
                feature(FeatureFlag.ResidentQuickAdd)
            "
        >
            <Button
                type="button"
                variant="link"
                size="sm"
                class="px-0"
                @click="quickAddOpen = true"
            >
                {{ t('picker.quick_add') }}
            </Button>
            <QuickAddDialog v-model:open="quickAddOpen" @created="choose" />
        </div>
    </div>
</template>
