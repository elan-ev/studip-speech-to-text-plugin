<script setup>
defineProps({
    msgCompanion: {
        type: String,
        required: true,
    },
    mood: {
        type: String,
        default: 'default',
        validator: (value) => {
            return ['default', 'unsure', 'special', 'sad', 'pointing', 'curious'].includes(value);
        },
    },
});
</script>

<template>
    <div class="cw-companion-box" :class="[mood]">
        <div>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <p v-html="msgCompanion"></p>
            <slot name="companionActions"></slot>
        </div>
    </div>
</template>
