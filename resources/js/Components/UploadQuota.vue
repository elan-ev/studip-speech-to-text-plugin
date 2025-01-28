<script setup>
import { computed } from 'vue';
import { format } from '../Composables/use-file-size';

defineProps(["quota", "usage"]);
</script>

<template>
<span>Verbrauchtes Kontingent:</span>
<progress class="progress w-56" :value="usage" :max="quota"></progress>
<span>{{ format(usage) }} / {{format(quota)}}</span>
</template>

<style scoped>
.progress {
    appearance: none;
    border-radius: 0.5rem;
    background-color: var(--content-color-10);
    width: 100%;
    height: .5rem;
    position: relative;
    overflow: hidden;
}

.w-56 {
    width: 200px;
}
</style>
