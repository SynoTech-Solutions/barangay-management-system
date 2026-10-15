<script setup lang="ts">
import { computed } from 'vue';

type Bar = { key: string; label: string; total: number; color?: string };

const DEFAULT_COLOR = 'var(--primary)';

const props = defineProps<{ bars: Bar[] }>();

const max = computed(() => Math.max(1, ...props.bars.map((bar) => bar.total)));
</script>

<template>
    <ul class="grid gap-1.5 text-sm">
        <li
            v-for="bar in bars"
            :key="bar.key"
            class="grid grid-cols-[minmax(8rem,14rem)_1fr_3rem] items-center gap-2"
        >
            <span class="truncate">{{ bar.label }}</span>
            <span class="h-3 rounded bg-muted">
                <span
                    class="block h-3 rounded"
                    :style="{
                        width: `${(bar.total / max) * 100}%`,
                        backgroundColor: bar.color ?? DEFAULT_COLOR,
                    }"
                />
            </span>
            <span class="text-right tabular-nums">{{ bar.total }}</span>
        </li>
    </ul>
</template>
