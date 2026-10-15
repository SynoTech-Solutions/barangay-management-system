<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import type { PaginationMeta } from '@/types';

const props = withDefaults(
    defineProps<{ meta: PaginationMeta; pageName?: string }>(),
    { pageName: 'page' },
);

function go(page: number): void {
    router.get(
        window.location.pathname,
        {
            ...Object.fromEntries(new URLSearchParams(window.location.search)),
            [props.pageName]: page,
        },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <div
        class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
    >
        <span>{{ t('common.total', { total: props.meta.total }) }}</span>
        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="props.meta.current_page <= 1"
                @click="go(props.meta.current_page - 1)"
            >
                {{ t('common.previous') }}
            </Button>
            <span>{{
                t('common.page_of', {
                    current: props.meta.current_page,
                    last: props.meta.last_page,
                })
            }}</span>
            <Button
                variant="outline"
                size="sm"
                :disabled="props.meta.current_page >= props.meta.last_page"
                @click="go(props.meta.current_page + 1)"
            >
                {{ t('common.next') }}
            </Button>
        </div>
    </div>
</template>
