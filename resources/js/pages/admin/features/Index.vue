<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { index, update } from '@/routes/admin/features';
import type { FeatureState } from '@/types';

type OverrideState = 'on' | 'off' | 'default';

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.features'), href: index() }] },
});

const props = defineProps<{
    scope: 'barangay' | 'global';
    features: FeatureState[];
    canManageGlobal: boolean;
}>();

const { can } = useAccess();
const reason = ref('');

const canChange = computed(() =>
    props.scope === 'global'
        ? props.canManageGlobal
        : can(Permission.FeaturesManage),
);

const groups = computed(() => {
    const byArea = new Map<string, { label: string; items: FeatureState[] }>();
    for (const feature of props.features) {
        const group = byArea.get(feature.area) ?? {
            label: feature.area_label,
            items: [],
        };
        group.items.push(feature);
        byArea.set(feature.area, group);
    }

    return [...byArea.values()];
});

function set(flag: string, state: OverrideState): void {
    router.put(
        update.url(),
        { flag, state, scope: props.scope, reason: reason.value || null },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('features.title')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="`${t('features.title')} · ${scope === 'global' ? t('features.scope_global') : t('features.scope_barangay')}`"
            :description="
                scope === 'global'
                    ? t('features.description_global')
                    : t('features.description_barangay')
            "
        />

        <Input
            v-if="canChange"
            v-model="reason"
            :placeholder="t('common.reason')"
            class="max-w-md"
        />

        <section v-for="group in groups" :key="group.label" class="grid gap-2">
            <h3 class="text-sm font-semibold text-muted-foreground">
                {{ group.label }}
            </h3>
            <div class="divide-y rounded-lg border">
                <div
                    v-for="feature in group.items"
                    :key="feature.flag"
                    class="flex flex-wrap items-center justify-between gap-4 p-3"
                >
                    <div class="grid gap-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ feature.label }}</span>
                            <Badge
                                :variant="
                                    feature.enabled ? 'default' : 'outline'
                                "
                            >
                                {{
                                    feature.enabled
                                        ? t('features.on')
                                        : t('features.off')
                                }}
                            </Badge>
                            <Badge variant="secondary">{{
                                feature.source_label
                            }}</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ feature.description }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <span class="font-mono">{{ feature.flag }}</span>
                            ·
                            {{
                                t('features.default', {
                                    state: feature.default_enabled
                                        ? t('features.on')
                                        : t('features.off'),
                                })
                            }}
                            <template v-if="feature.requires.length">
                                ·
                                {{
                                    t('features.requires', {
                                        flags: feature.requires.join(', '),
                                    })
                                }}</template
                            >
                        </p>
                    </div>
                    <div v-if="canChange" class="flex gap-1">
                        <Button
                            size="sm"
                            :variant="
                                feature.source.endsWith('override') &&
                                feature.enabled
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="set(feature.flag, 'on')"
                        >
                            {{ t('features.on') }}
                        </Button>
                        <Button
                            size="sm"
                            :variant="
                                feature.source.endsWith('override') &&
                                !feature.enabled
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="set(feature.flag, 'off')"
                        >
                            {{ t('features.off') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="set(feature.flag, 'default')"
                            >{{ t('features.use_default') }}</Button
                        >
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
