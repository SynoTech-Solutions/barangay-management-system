<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { dashboard } from '@/routes';
import {
    expired as expiredBusinesses,
    index as businessClearancesIndex,
} from '@/routes/business-clearances';
import { index as blottersIndex } from '@/routes/blotters';
import { index as cedulasIndex } from '@/routes/cedulas';
import { index as clearancesIndex } from '@/routes/clearances';
import { index as checksIndex } from '@/routes/finance/checks';
import { index as liquidationsIndex } from '@/routes/finance/liquidations';
import { index as vouchersIndex } from '@/routes/finance/vouchers';
import { index as idCardsIndex } from '@/routes/id-cards';
import { index as formTransactionsIndex } from '@/routes/forms/transactions';
import { index as smsIndex } from '@/routes/sms';
import { todayIsoDate } from '@/lib/format';
import type { DashboardCounter } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.dashboard'), href: dashboard() }],
    },
});

const props = defineProps<{ counters: DashboardCounter[] }>();

const PENDING = { status: 'issued' };

const page = usePage();
const today = todayIsoDate();
const issuedToday = { from: today, to: today };

const counterLinks: Record<string, string> = {
    'clearances-for-release': clearancesIndex.url({ query: PENDING }),
    'clearances-issued-today': clearancesIndex.url({ query: issuedToday }),
    'business-clearances-for-release': businessClearancesIndex.url({
        query: PENDING,
    }),
    'business-clearances-issued-today': businessClearancesIndex.url({
        query: issuedToday,
    }),
    'business-clearances-expired': expiredBusinesses.url(),
    'id-cards-for-release': idCardsIndex.url({ query: PENDING }),
    'id-cards-issued-today': idCardsIndex.url({ query: issuedToday }),
    'cedulas-for-release': cedulasIndex.url({ query: PENDING }),
    'cedulas-issued-today': cedulasIndex.url({ query: issuedToday }),
    'open-blotters': blottersIndex.url({ query: { status: 'open' } }),
    'form-transactions-for-release': formTransactionsIndex.url({
        query: PENDING,
    }),
    'vouchers-for-verification': vouchersIndex.url({
        query: { status: 'prepared' },
    }),
    'vouchers-for-approval': vouchersIndex.url({
        query: { status: 'verified' },
    }),
    'checks-for-release': checksIndex.url({ query: { status: 'for-release' } }),
    'unliquidated-cash-advances': liquidationsIndex.url(),
    'liquidations-for-clearing': liquidationsIndex.url({
        query: { status: 'for-clearing' },
    }),
    'sms-queued': smsIndex.url({ query: { status: 'queued' } }),
    'sms-failed': smsIndex.url({ query: { status: 'failed' } }),
};
const { barangay } = useAccess();

const enabledModules = computed(() =>
    Object.entries(page.props.features)
        .filter(([, enabled]) => enabled)
        .map(([flag]) => flag),
);
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            :title="
                barangay
                    ? t('dashboard.working_in', { name: barangay.name })
                    : t('dashboard.no_barangay')
            "
            :description="
                barangay ? undefined : t('dashboard.no_barangay_help')
            "
        />

        <div
            v-if="props.counters.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <Link
                v-for="counter in props.counters"
                :key="counter.key"
                :href="counterLinks[counter.key] ?? dashboard().url"
                class="rounded-xl border p-4 transition hover:bg-accent"
            >
                <p class="text-sm text-muted-foreground">
                    {{ counter.label }}
                </p>
                <p class="text-3xl font-semibold tabular-nums">
                    {{ counter.count }}
                </p>
            </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('dashboard.modules_title') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <Badge
                        v-for="flag in enabledModules"
                        :key="flag"
                        variant="secondary"
                        >{{ flag }}</Badge
                    >
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>{{
                        t('dashboard.permissions_title')
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    {{
                        t('dashboard.permission_count', {
                            count: page.props.auth.permissions.length,
                        })
                    }}
                </CardContent>
            </Card>
        </div>
    </div>
</template>
