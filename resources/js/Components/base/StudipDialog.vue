<script setup>
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'radix-vue';
import { computed, nextTick, ref } from 'vue';
import { useGettext } from 'vue3-gettext';
import VueResizable from 'vue-resizable';

const { $gettext } = useGettext();
const dialogPadding = 3;

const props = defineProps({
    alert: {
        type: String,
        default: null,
    },
    closeClass: {
        type: String,
        default: null,
    },
    closeText: {
        type: String,
        default: null,
    },
    confirmClass: {
        type: String,
        default: null,
    },
    confirmDisabled: {
        type: Boolean,
        default: false,
    },
    confirmShow: {
        type: Boolean,
        default: true,
    },
    confirmText: {
        type: String,
        default: null,
    },
    description: {
        type: String,
        default: null,
    },
    height: {
        type: Number,
        default: 300,
    },
    message: {
        type: String,
        default: null,
    },
    open: {
        type: Boolean,
        required: true,
    },
    question: {
        type: String,
        default: null,
    },
    title: {
        type: String,
        default: null,
    },
    width: {
        type: Number,
        default: 450,
    },
});

const emit = defineEmits(['confirm', 'update:open']);

const currentHeight = ref(300);
const currentWidth = ref(450);
const footerRef = ref(null);
const footerHeight = ref(68);
const headerRef = ref(null);
const left = ref(0);
const top = ref(0);

const buttonA = computed(() => {
    let button = false;
    if (props.message) {
        return false;
    }
    if (props.question || props.alert) {
        button = {};
        button.text = $gettext('Ja');
        button.class = 'accept';
    }
    if (props.confirmText && props.confirmShow) {
        button = {};
        button.text = props.confirmText;
        button.class = props.confirmClass;
        button.disabled = props.confirmDisabled;
    }

    return button;
});

const buttonB = computed(() => {
    let button = false;
    if (props.message) {
        button = {};
        button.text = $gettext('Ok');
        button.class = '';
    }
    if (props.question || props.alert) {
        button = {};
        button.text = $gettext('Nein');
        button.class = 'cancel';
    }
    if (props.closeText) {
        button = {};
        button.text = props.closeText;
        if (props.closeClass) {
            button.class = props.closeClass;
        } else {
            button.class = 'cancel';
        }
    }

    return button;
});

const dialogTitle = computed(() => {
    if (props.title) {
        return props.title;
    }
    if (props.alert || props.question) {
        return $gettext('Bitte bestätigen Sie die Aktion');
    }
    if (props.message) {
        return $gettext('Information');
    }
    return '';
});
const dialogWidth = computed(() => {
    return currentWidth.value ? currentWidth.value - dialogPadding * 4 + 'px' : 'unset';
});
const dialogHeight = computed(() => {
    return currentHeight.value ? currentHeight.value - headerHeight.value - dialogPadding * 4 + 'px' : 'unset';
});
const contentHeight = computed(() => {
    return currentHeight.value ? currentHeight.value - footerHeight.value + 'px' : 'unset';
});
const headerHeight = computed(() => {
    return headerRef.value?.offsetHeight ?? 0;
});

const initSize = () => {
    nextTick(() => {
        currentWidth.value = props.width;
        currentHeight.value = props.height;
        if (window.innerWidth > currentWidth.value) {
            left.value = (window.innerWidth - currentWidth.value) / 2;
        } else {
            left.value = 5;
            currentWidth.value = window.innerWidth - 16;
        }

        top.value = (window.innerHeight - currentHeight.value) / 2;
        footerHeight.value = footerRef.value.offsetHeight;
    });
};

const resizeHandler = (data) => {
    currentWidth.value = data.width;
    currentHeight.value = data.height;
    left.value = data.left;
    top.value = data.top;
};

const setIsOpen = (value) => emit('update:open', value);
const confirmDialog = () => emit('confirm');
</script>

<template>
    <DialogRoot :open="open">
        <DialogPortal>
            <DialogOverlay
                class="studip-dialog-backdrop studip-dialog-overlay data-[state=open]:animate-overlayShowfixed"
            />
            <DialogContent class="studip-dialog" trap-focus>
                <VueResizable
                    class="resizable"
                    style="position: absolute"
                    drag-selector=".studip-dialog-header"
                    :left="left"
                    :top="top"
                    :width="currentWidth"
                    :height="currentHeight"
                    :min-width="100"
                    :min-height="100"
                    @mount="initSize"
                    @resize:move="resizeHandler"
                    @resize:start="resizeHandler"
                    @resize:end="resizeHandler"
                    @drag:move="resizeHandler"
                    @drag:start="resizeHandler"
                    @drag:end="resizeHandler"
                >
                    <div
                        class="studip-dialog-body"
                        :style="{ height: dialogHeight, width: dialogWidth }"
                        :class="{ 'studip-dialog-warning': question, 'studip-dialog-alert': alert }"
                    >
                        <DialogTitle ref="headerRef" as="header" class="studip-dialog-header">
                            <span class="studip-dialog-title" :title="dialogTitle" role="heading" aria-level="2">
                                {{ dialogTitle }}
                            </span>
                            <slot name="dialogHeader"></slot>
                        </DialogTitle>
                        <DialogDescription class="sr-only"> {{ description }} </DialogDescription>

                        <section class="studip-dialog-content" :style="{ height: contentHeight }">
                            <slot name="dialogContent"></slot>
                            <div v-if="message">{{ message }}</div>
                            <div v-if="question">{{ question }}</div>
                            <div v-if="alert">{{ alert }}</div>
                        </section>
                        <footer ref="footerRef" class="studip-dialog-footer">
                            <div class="studip-dialog-footer-buttonset-left">
                                <slot name="dialogButtonsBefore"></slot>
                            </div>
                            <div class="studip-dialog-footer-buttonset-center">
                                <button
                                    v-if="buttonA"
                                    :title="buttonA.text"
                                    :class="[buttonA.class]"
                                    :disabled="buttonA.disabled"
                                    class="button"
                                    type="button"
                                    @click="confirmDialog"
                                >
                                    {{ buttonA.text }}
                                </button>
                                <slot name="dialogButtons"></slot>
                                <button
                                    v-if="buttonB"
                                    :title="buttonB.text"
                                    :class="[buttonB.class]"
                                    class="button"
                                    type="button"
                                    @click="setIsOpen(false)"
                                >
                                    {{ buttonB.text }}
                                </button>
                            </div>
                            <div class="studip-dialog-footer-buttonset-right">
                                <slot name="dialogButtonsAfter"></slot>
                            </div>
                        </footer>
                    </div>
                </VueResizable>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

<style scoped>
.studip-dialog {
    z-index: 3002;
}
.studip-dialog-content {
    flex-direction: column;
}
.studip-dialog-overlay {
    background-color: var(--base-color);
    opacity: 0.5;
}
</style>
