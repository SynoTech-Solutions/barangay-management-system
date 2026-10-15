<script setup lang="ts">
defineProps<{
    caption: string;
    name: string | null;
    title: string | null;
    signature?: string | null;
}>();
</script>

<template>
    <div class="flex flex-col items-center text-center">
        <p class="self-start text-[9pt]">{{ caption }}</p>
        <img v-if="signature" :src="signature" alt="" class="h-[14mm]" />
        <div v-else class="h-[14mm]" />
        <p class="w-full border-t border-black pt-1 font-bold uppercase">
            {{ name ?? '' }}
        </p>
        <p class="text-[9pt]">{{ title ?? '' }}</p>
    </div>
</template>
