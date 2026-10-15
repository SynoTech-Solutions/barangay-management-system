<script setup lang="ts">
import { Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import {
    create,
    deactivate,
    edit,
    index,
    reactivate,
} from '@/routes/admin/users';
import type { ManagedUser, Paginated } from '@/types';

const SEARCH_DELAY_MS = 300;

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.users'), href: index() }] },
});

const props = defineProps<{
    users: Paginated<ManagedUser>;
    filters: { search: string };
}>();

const { can } = useAccess();
const search = ref(props.filters.search);

const applySearch = useDebounceFn(() => {
    router.get(
        index.url(),
        { search: search.value },
        { preserveState: true, replace: true },
    );
}, SEARCH_DELAY_MS);
</script>

<template>
    <Head :title="t('users.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="t('users.title')"
                :description="t('users.description')"
            />
            <Button v-if="can(Permission.UsersManage)" as-child>
                <Link :href="create()">{{ t('users.new') }}</Link>
            </Button>
        </div>

        <Input
            v-model="search"
            :placeholder="t('common.search_placeholder')"
            class="max-w-sm"
            @input="applySearch"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('users.name') }}</th>
                        <th class="p-3">{{ t('users.username') }}</th>
                        <th class="p-3">{{ t('users.roles') }}</th>
                        <th class="p-3">{{ t('users.last_login') }}</th>
                        <th class="p-3">{{ t('users.status') }}</th>
                        <th class="p-3 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-t"
                    >
                        <td class="p-3">
                            <div class="font-medium">{{ user.name }}</div>
                            <div class="text-muted-foreground">
                                {{ user.designation }}
                            </div>
                        </td>
                        <td class="p-3 font-mono">{{ user.username }}</td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="role in user.roles ?? []"
                                    :key="role.id"
                                    variant="secondary"
                                    >{{ role.label }}</Badge
                                >
                            </div>
                        </td>
                        <td class="p-3">
                            {{
                                user.last_login_at
                                    ? new Date(
                                          user.last_login_at,
                                      ).toLocaleString()
                                    : t('common.never')
                            }}
                        </td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    user.is_active ? 'default' : 'outline'
                                "
                            >
                                {{
                                    user.is_active
                                        ? t('common.active')
                                        : t('common.inactive')
                                }}
                            </Badge>
                        </td>
                        <td class="p-3 text-right">
                            <div
                                v-if="can(Permission.UsersManage)"
                                class="flex justify-end gap-2"
                            >
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="edit(user.id)">{{
                                        t('common.edit')
                                    }}</Link>
                                </Button>
                                <Button
                                    v-if="user.is_active"
                                    variant="outline"
                                    size="sm"
                                    @click="
                                        router.post(
                                            deactivate.url(user.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    "
                                >
                                    {{ t('users.deactivate') }}
                                </Button>
                                <Button
                                    v-else
                                    variant="outline"
                                    size="sm"
                                    @click="
                                        router.post(
                                            reactivate.url(user.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    "
                                >
                                    {{ t('users.reactivate') }}
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="users.meta" />
    </div>
</template>
