<script setup lang="ts">
defineProps<{ facts: Array<[string, string | number | null | undefined]> }>();
</script>

<template>
    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="[label, value] in facts" :key="label">
            <dt class="text-muted-foreground">{{ label }}</dt>
            <dd class="font-medium">{{ value ?? '' }}</dd>
        </div>
    </dl>
</template>
