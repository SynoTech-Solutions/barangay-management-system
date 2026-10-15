<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import { destroy, show, update } from '@/routes/residents/photo';
import type { ResidentDetail } from '@/types';

const props = defineProps<{
    resident: ResidentDetail;
    maxKilobytes: number;
}>();

const form = useForm<{ photo: File | null }>({ photo: null });

const source = computed(() =>
    props.resident.has_photo
        ? show.url(props.resident.id, {
              query: { v: props.resident.photo_version ?? 0 },
          })
        : null,
);

function pick(event: Event): void {
    form.photo = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function upload(): void {
    form.post(update.url(props.resident.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(): void {
    router.delete(destroy.url(props.resident.id), { preserveScroll: true });
}
</script>

<template>
    <section class="grid gap-4">
        <Heading
            variant="small"
            :title="t('photo.title')"
            :description="t('photo.hint', { size: maxKilobytes })"
        />
        <img
            v-if="source"
            :src="source"
            :alt="t('photo.alt', { name: resident.full_name })"
            class="h-48 w-48 rounded-md border object-cover"
        />
        <p v-else class="text-sm text-muted-foreground">
            {{ t('photo.none') }}
        </p>
        <form class="grid max-w-sm gap-3" @submit.prevent="upload">
            <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="text-sm"
                @change="pick"
            />
            <InputError :message="form.errors.photo" />
            <div class="flex gap-2">
                <Button
                    type="submit"
                    :disabled="form.processing || form.photo === null"
                    >{{ t('photo.upload') }}</Button
                >
                <Button
                    v-if="resident.has_photo"
                    type="button"
                    variant="outline"
                    @click="remove"
                    >{{ t('photo.remove') }}</Button
                >
            </div>
        </form>
    </section>
</template>
