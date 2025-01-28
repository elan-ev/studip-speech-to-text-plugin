<script setup>
import { computed } from 'vue';

const props = defineProps(['file', 'type']);

const re = /(?:\.([^.]+))?$/;
const ext = computed(() => re.exec(props.file?.name ?? '')[1]);
</script>

<template>
<a :href="file.url" :download="file.name">{{ ext }}</a>
</template>

<style scoped>
a {
    --badge-color: var(--color--button-border);
    --badge-bg: var(--color--button-background);
    --badge-fg: var(--color--button-border);
    --border: 1px;
    --radius-selector: 0.5rem;
    --size: 1.5rem;

    border-radius: var(--radius-selector);
    vertical-align: middle;
    color: var(--badge-fg);
    border: var(--border) solid var(--badge-color);
    width: fit-content;
    padding-inline: calc(.25rem*3 - var(--border));
    background-color: var(--badge-bg);
    height: var(--size);
    justify-content: center;
    align-items: center;
    gap: .5rem;
    font-size: .875rem;
    display: inline-flex;
}
</style>
