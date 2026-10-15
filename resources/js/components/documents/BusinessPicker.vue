<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/composables/useI18n';
import { getJson } from '@/lib/http';
import { search } from '@/routes/businesses';
import type { BusinessOption, Paginated } from '@/types';

const SEARCH_DELAY_MS = 250;

defineProps<{ id?: string }>();

const model = defineModel<BusinessOption | null>({ required: true });

const query = ref('');
const results = ref<BusinessOption[]>([]);
const searched = ref(false);

const runSearch = useDebounceFn(async () => {
    if (query.value.trim() === '') {
        results.value = [];
        searched.value = false;

        return;
    }

    const page = await getJson<Paginated<BusinessOption>>(
        search.url({ query: { q: query.value } }),
    );
    results.value = page?.data ?? [];
    searched.value = true;
}, SEARCH_DELAY_MS);

function choose(option: BusinessOption): void {
    model.value = option;
    query.value = '';
    results.value = [];
    searched.value = false;
}

function describe(option: BusinessOption): string {
    return [option.business_number, option.owner_name, option.address]
        .filter((part) => part)
        .join(' · ');
}
</script>

<template>
    <div
        v-if="model"
        class="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm"
    >
        <div>
            <div class="font-medium">{{ model.name }}</div>
            <div class="text-muted-foreground">{{ describe(model) }}</div>
        </div>
        <Button type="button" variant="outline" size="sm" @click="model = null">
            {{ t('picker.clear') }}
        </Button>
    </div>
    <div v-else class="relative">
        <Input
            :id="id"
            v-model="query"
            :placeholder="t('business_picker.placeholder')"
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
                    <span class="font-medium">{{ option.name }}</span>
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
</template>
