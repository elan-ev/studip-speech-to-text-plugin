<template>
    <label>
        <div class="holder" :class="{ dragging }">
            <div class="box-centered">
                <div class="icon-upload">
                    <slot name="icon">
                        <StudipIcon shape="upload" :size="100" alt="" :role="dragging ? 'info_alt' : 'clickable' "/>
                    </slot>
                </div>
                <slot />
                <div class="quota" v-if="$slots.quota">
                    <slot name="quota"></slot>
                </div>
                <div class="upload-button-holder">
                    <input type="file" name="file" tabindex="-1" accept="audio/*,video/*" ref="upload"
                           @change="onUpload"
                           @dragenter="setDragging(true)"
                           @dragleave="setDragging(false)"
                    />
                </div>
            </div>
        </div>
    </label>
</template>

<script>
import StudipIcon from './base/StudipIcon.vue';

export default {
    components: { StudipIcon, },
    emits: ['upload'],
    data: () => ({
        dragging: false,
    }),
    methods: {
        onUpload() {
            const files = this.$refs.upload.files;
            const file = files[0];
            this.$emit('upload', { file });
        },
        setDragging(state) {
            this.dragging = state;
        },
    },
};
</script>

<style scoped lang="scss">
label {
    height: 100%;
    margin: -15px;
    padding: 18px 15px 10px;
    text-align: center;
}
.holder {
    align-items: center;
    border-color: var(--content-color-60);
    border-radius: 0.5em;
    border-style: dashed;
    border-width: 1px;
    box-sizing: border-box;
    display: flex;
    height: 100%;
    justify-content: center;
    padding: 0;
    position: relative;

    &.dragging {
        background-color: var(--content-color-40);

        .icon-upload + strong {
            color: var(--white);
        }
    }
}

.box-centered {
    height: auto;
    width: 100%;
    max-height: 100%;
}

.icon-upload + strong {
    color: var(--base-color);
    font-size: 1.5em;
    line-height: 1.2;
    display: block;
    font-weight: 500;
    text-align: center;
    margin: 0 2em 14px;
}

.upload-button-holder input[type='file'] {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;

    opacity: 0;
    width: 100%;
    height: 100%;
    padding: 0;
}

.quota {
    align-items: center;
    color: var(--color--font-secondary);
    display: flex;
    justify-content: center;
    gap: 1rem;
}
</style>
