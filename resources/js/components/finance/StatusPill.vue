<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{ label: string; muted?: boolean }>();
</script>

<template>
    <Badge :variant="muted ? 'outline' : 'secondary'" class="ml-2">{{
        label
    }}</Badge>
</template>
