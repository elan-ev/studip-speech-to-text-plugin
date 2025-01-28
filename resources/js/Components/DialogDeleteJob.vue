<script setup>
import StudipDialog from './base/StudipDialog.vue';

defineProps(['open']);
const emit = defineEmits(['update:open', 'trash']);

const setIsOpen = (value) => emit('update:open', value);
</script>

<template>
    <StudipDialog
        :open="open"
        :title="$gettext('Transkription löschen')"
        :description="$gettext('Bestätigen Sie, dass Sie diese Transkription löschen wollen.')"
        :question="$gettext('Möchten Sie diese Transkription unwiderruflich löschen?')"
        :height="200"
        @update:open="setIsOpen"
        @confirm="$emit('trash')"
    >
    </StudipDialog>
</template>
