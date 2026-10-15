<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { BlotterStatus } from '@/types';

const props = defineProps<{ status: BlotterStatus; label: string }>();

const variant = computed<'default' | 'outline' | 'destructive'>(() => {
    if (props.status === 'open') {
        return 'destructive';
    }

    return props.status === 'resolved' ? 'default' : 'outline';
});
</script>

<template>
    <Badge :variant="variant">{{ label }}</Badge>
</template>
