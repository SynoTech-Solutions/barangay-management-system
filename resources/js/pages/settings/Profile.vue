<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('settings.profile_title'), href: edit() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const { feature } = useAccess();
</script>

<template>
    <Head :title="t('settings.profile_title')" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="t('settings.profile_heading')"
            :description="t('settings.profile_description')"
        />

        <dl class="grid gap-1 text-sm">
            <dt class="text-muted-foreground">{{ t('users.username') }}</dt>
            <dd class="font-mono">{{ user.username }}</dd>
        </dl>

        <Form
            v-if="feature(FeatureFlag.SelfServiceProfile)"
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t('users.name') }}</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t('users.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :default-value="user.email ?? ''"
                    autocomplete="email"
                />
                <InputError :message="errors.email" />
            </div>

            <Button :disabled="processing" data-test="update-profile-button">{{
                t('common.save')
            }}</Button>
        </Form>
        <p v-else class="text-sm text-muted-foreground">
            {{ t('settings.profile_locked') }}
        </p>
    </div>
</template>
