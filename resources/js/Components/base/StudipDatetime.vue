<script setup>
defineProps({
    date: {
        type: Date,
        required: true,
    },
});
const formatDate = (date) => {
    return pad(date.getDate()) + '.'
        + pad(date.getMonth() + 1) + '.'
        + date.getFullYear() + ' '
        + pad(date.getHours()) + ':'
        + pad(date.getMinutes()) + ':'
              + pad(date.getSeconds());
};

const pad = (what) => {
    return what.toString().padStart(2, '0');
};
</script>

<template>
    <time :datetime="date.toISOString()">{{ formatDate(date) }}</time>
</template>
