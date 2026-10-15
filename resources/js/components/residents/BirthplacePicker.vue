<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/composables/useI18n';
import { getJson } from '@/lib/http';
import { search } from '@/routes/residents/birthplaces';
import type { SearchResult, SelectOption } from '@/types';

const SEARCH_DELAY_MS = 250;

defineProps<{ id?: string }>();

const model = defineModel<SelectOption | null>({ required: true });
const query = ref('');
const results = ref<SearchResult[]>([]);

const runSearch = useDebounceFn(async () => {
    const response = await getJson<{ data: SearchResult[] }>(
        search.url({ query: { search: query.value } }),
    );
    results.value = response?.data ?? [];
}, SEARCH_DELAY_MS);

function choose(result: SearchResult): void {
    model.value = { value: result.id, label: result.label };
    query.value = '';
    results.value = [];
}
</script>

<template>
    <div
        v-if="model"
        class="flex items-center justify-between gap-3 rounded-md border px-3 py-1.5 text-sm"
    >
        <span>{{ model.label }}</span>
        <Button type="button" variant="outline" size="sm" @click="model = null">
            {{ t('picker.clear') }}
        </Button>
    </div>
    <div v-else class="relative">
        <Input
            :id="id"
            v-model="query"
            :placeholder="t('residents.fields.birthplace_placeholder')"
            autocomplete="off"
            @input="runSearch"
        />
        <ul
            v-if="results.length"
            class="absolute top-full z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-background text-sm shadow"
        >
            <li v-for="result in results" :key="result.id">
                <button
                    type="button"
                    class="w-full px-3 py-1.5 text-left hover:bg-accent"
                    @click="choose(result)"
                >
                    {{ result.label }}
                </button>
            </li>
        </ul>
    </div>
</template>
