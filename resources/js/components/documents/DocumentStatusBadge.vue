<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { t } from '@/composables/useI18n';
import type { DocumentStatus } from '@/types';

const props = withDefaults(
    defineProps<{
        status: DocumentStatus;
        label: string;
        expired?: boolean;
        reason?: string | null;
    }>(),
    { expired: false, reason: null },
);

const variant = computed<'default' | 'secondary' | 'outline' | 'destructive'>(
    () => {
        if (props.expired) {
            return 'destructive';
        }

        if (props.status === 'released') {
            return 'default';
        }

        return props.status === 'cancelled' ? 'outline' : 'secondary';
    },
);
</script>

<template>
    <Badge :variant="variant">{{
        expired ? t('documents.expired') : label
    }}</Badge>
    <div v-if="reason" class="text-muted-foreground">{{ reason }}</div>
</template>
