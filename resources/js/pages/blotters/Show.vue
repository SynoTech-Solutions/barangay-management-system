<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import BlotterStatusBadge from '@/components/documents/BlotterStatusBadge.vue';
import ResidentFacts from '@/components/residents/ResidentFacts.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { cancel, edit, index, resolve } from '@/routes/blotters';
import {
    destroy as destroyPhoto,
    show as photoUrl,
    store as storePhoto,
} from '@/routes/blotters/photos';
import { show as residentShow } from '@/routes/residents';
import type { Blotter } from '@/types';

const props = defineProps<{ blotter: Blotter }>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.blotters'), href: index() }] },
});

const { can, feature } = useAccess();
const b = props.blotter;
const notes = ref('');
const reason = ref('');
const photoForm = useForm<{ photo: File | null }>({ photo: null });

const facts = computed<Array<[string, string | null]>>(() => [
    [t('blotters.incident_type'), b.incident_type],
    [t('blotters.incident_at'), new Date(b.incident_at).toLocaleString()],
    [t('blotters.place'), b.place],
    [t('blotters.reported_at'), new Date(b.reported_at).toLocaleString()],
    [t('blotters.recorded_by'), b.recorded_by],
    [
        t('blotters.resolved_at'),
        b.resolved_at ? new Date(b.resolved_at).toLocaleString() : null,
    ],
    [t('blotters.resolution_notes'), b.resolution_notes],
    [t('blotters.cancel_reason'), b.cancel_reason],
]);

const canManage = computed(() => b.is_open && can(Permission.BlotterManage));
const showPhotos = computed(() => feature(FeatureFlag.BlotterPhotoAttachment));

function markResolved(): void {
    router.post(
        resolve.url(b.id),
        { notes: notes.value },
        { preserveScroll: true },
    );
}

function cancelBlotter(): void {
    router.post(
        cancel.url(b.id),
        { reason: reason.value },
        { preserveScroll: true },
    );
}

function pick(event: Event): void {
    photoForm.photo = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function upload(): void {
    photoForm.post(storePhoto.url(b.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => photoForm.reset(),
    });
}

function removePhoto(photoId: number): void {
    if (window.confirm(t('blotters.remove_photo_confirm'))) {
        router.delete(destroyPhoto.url({ blotter: b.id, photo: photoId }), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="blotter.blotter_number" />

    <div class="flex max-w-5xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid gap-2">
                <Heading
                    :title="blotter.blotter_number"
                    :description="t('blotters.show_description')"
                />
                <BlotterStatusBadge
                    :status="blotter.status"
                    :label="blotter.status_label"
                />
            </div>
            <Button v-if="canManage" as-child>
                <Link :href="edit(blotter.id)">{{ t('common.edit') }}</Link>
            </Button>
        </div>

        <ResidentFacts :facts="facts" />

        <section class="grid gap-2">
            <Heading variant="small" :title="t('blotters.parties')" />
            <ul class="divide-y rounded-lg border text-sm">
                <li
                    v-for="party in blotter.parties"
                    :key="party.id"
                    class="flex items-center justify-between gap-3 p-2"
                >
                    <span>
                        <Link
                            v-if="party.resident"
                            :href="residentShow(party.resident.id)"
                            class="font-medium hover:underline"
                            >{{ party.display_name }}</Link
                        >
                        <span v-else class="font-medium">{{
                            party.display_name
                        }}</span>
                        <span
                            v-if="party.address"
                            class="ml-2 text-muted-foreground"
                            >{{ party.address }}</span
                        >
                    </span>
                    <span class="text-muted-foreground">{{
                        party.role_label
                    }}</span>
                </li>
            </ul>
        </section>

        <section class="grid gap-2">
            <Heading variant="small" :title="t('blotters.narrative')" />
            <p class="rounded-lg border p-3 text-sm whitespace-pre-line">
                {{ blotter.narrative }}
            </p>
        </section>

        <section v-if="showPhotos" class="grid gap-3">
            <Heading variant="small" :title="t('blotters.photos')" />
            <div class="flex flex-wrap gap-3">
                <figure
                    v-for="photo in blotter.photos ?? []"
                    :key="photo.id"
                    class="grid gap-1"
                >
                    <img
                        :src="
                            photoUrl.url({
                                blotter: blotter.id,
                                photo: photo.id,
                            })
                        "
                        alt=""
                        class="h-32 w-32 rounded-md border object-cover"
                    />
                    <Button
                        v-if="canManage"
                        size="sm"
                        variant="ghost"
                        @click="removePhoto(photo.id)"
                        >{{ t('blotters.remove_photo') }}</Button
                    >
                </figure>
                <p
                    v-if="!(blotter.photos ?? []).length"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('blotters.no_photos') }}
                </p>
            </div>
            <form
                v-if="canManage"
                class="flex flex-wrap items-center gap-2"
                @submit.prevent="upload"
            >
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="text-sm"
                    @change="pick"
                />
                <Button
                    type="submit"
                    size="sm"
                    :disabled="photoForm.processing || photoForm.photo === null"
                    >{{ t('blotters.add_photo') }}</Button
                >
                <InputError :message="photoForm.errors.photo" />
            </form>
        </section>

        <section
            v-if="blotter.is_open"
            class="grid gap-3 rounded-lg border p-3 md:grid-cols-2"
        >
            <div v-if="can(Permission.BlotterResolve)" class="grid gap-2">
                <Input
                    v-model="notes"
                    :placeholder="t('blotters.resolution_notes')"
                />
                <Button class="w-fit" @click="markResolved">{{
                    t('blotters.resolve')
                }}</Button>
            </div>
            <div v-if="can(Permission.BlotterManage)" class="grid gap-2">
                <Input
                    v-model="reason"
                    :placeholder="t('blotters.cancel_reason')"
                />
                <Button
                    class="w-fit"
                    variant="outline"
                    :disabled="reason.trim() === ''"
                    @click="cancelBlotter"
                    >{{ t('blotters.cancel') }}</Button
                >
            </div>
        </section>
    </div>
</template>
