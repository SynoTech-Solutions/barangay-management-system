<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';

const props = withDefaults(
    defineProps<{
        releaseUrl: string;
        cancelUrl: string;
        canRelease: boolean;
        canCancel: boolean;
        idsField?: string;
    }>(),
    { idsField: 'ids' },
);

const selected = defineModel<number[]>({ required: true });

const { feature } = useAccess();
const reason = ref('');

function releaseSelected(): void {
    if (
        window.confirm(
            t('documents.release_confirm', { count: selected.value.length }),
        )
    ) {
        router.post(
            props.releaseUrl,
            { [props.idsField]: selected.value },
            { preserveScroll: true, onSuccess: () => (selected.value = []) },
        );
    }
}

function cancelSelected(): void {
    router.post(
        props.cancelUrl,
        { [props.idsField]: selected.value, reason: reason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                reason.value = '';
            },
        },
    );
}
</script>

<template>
    <div
        v-if="selected.length"
        class="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/40 p-3 text-sm"
    >
        <span>{{ t('documents.selected', { count: selected.length }) }}</span>
        <Button
            v-if="canRelease && feature(FeatureFlag.DocumentReleaseStep)"
            size="sm"
            @click="releaseSelected"
            >{{ t('documents.release') }}</Button
        >
        <template v-if="canCancel">
            <Input
                v-model="reason"
                class="max-w-xs"
                :placeholder="t('documents.cancel_reason')"
            />
            <Button
                size="sm"
                variant="outline"
                :disabled="reason.trim() === ''"
                @click="cancelSelected"
                >{{ t('documents.cancel') }}</Button
            >
        </template>
    </div>
</template>
