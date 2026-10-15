<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { search } from '@/routes/central/barangays';
import { destroy, store } from '@/routes/central/switch';
import type { SearchResult } from '@/types';

const SEARCH_DELAY_MS = 250;

const { barangay } = useAccess();
const query = ref('');
const results = ref<SearchResult[]>([]);

const runSearch = useDebounceFn(async () => {
    const response = await fetch(
        search.url({ query: { search: query.value } }),
        {
            headers: { Accept: 'application/json' },
        },
    );
    results.value = response.ok
        ? ((await response.json()) as { data: SearchResult[] }).data
        : [];
}, SEARCH_DELAY_MS);

function choose(id: number): void {
    results.value = [];
    query.value = '';
    router.post(store.url(id));
}

function clear(): void {
    router.delete(destroy.url());
}
</script>

<template>
    <div class="grid gap-2 px-2 py-1 group-data-[collapsible=icon]:hidden">
        <span class="text-xs font-medium text-sidebar-foreground/70">{{
            t('switcher.label')
        }}</span>
        <div class="flex items-center justify-between gap-2 text-sm">
            <span class="truncate font-medium">{{
                barangay?.name ?? t('switcher.none')
            }}</span>
            <button
                v-if="barangay"
                type="button"
                class="text-xs text-muted-foreground underline"
                @click="clear"
            >
                {{ t('switcher.clear') }}
            </button>
        </div>
        <Input
            v-model="query"
            :placeholder="t('switcher.placeholder')"
            class="h-8"
            @input="runSearch"
        />
        <ul
            v-if="results.length"
            class="max-h-56 overflow-y-auto rounded-md border bg-background text-sm"
        >
            <li v-for="result in results" :key="result.id">
                <button
                    type="button"
                    class="w-full px-2 py-1 text-left hover:bg-accent"
                    @click="choose(result.id)"
                >
                    {{ result.label }}
                </button>
            </li>
        </ul>
    </div>
</template>
