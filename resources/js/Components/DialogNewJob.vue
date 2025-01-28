<script setup>
import { computed, watch } from "vue";
import { useRoute } from "@elan-ev/studip-named-routes";
import { useForm } from "@inertiajs/vue3";
import { DEFAULT_LANGUAGE, LANGUAGES } from "../config.js";
import StudipDialog from "./base/StudipDialog.vue";
import MessageBox from "./base/StudipMessageBox.vue";
import ProgressIndicator from "./base/StudipProgressIndicator.vue";
import { format } from "../Composables/use-file-size";

const props = defineProps(["open", "file", "status"]);
const emit = defineEmits(["update:open", "update:status"]);

const form = useForm({
    audio: null,
    language: DEFAULT_LANGUAGE,
});
const route = useRoute();

const setIsOpen = (value) => emit("update:open", value);

const onConfirm = () => {
    form.transform((data) => ({ ...data, audio: props.file })).post(route("jobs.store"), {
        forceFormData: true,
        onStart() {
            emit("update:status", "upload");
        },
        onSuccess() {
            setIsOpen(false);
            emit("update:status", "success");
        },
        onError(errors) {
            emit("update:status", "error");
        },
    });
};

const filesize = computed(() => props.file?.size ?? 0);

watch(
    () => props.open,
    (newOpen, oldOpen) => {
        if (newOpen && !oldOpen) {
            emit("update:status", "configure");
        }
    },
);
</script>

<template>
    <StudipDialog
        :open="open"
        :title="$gettext('Transkription erstellen')"
        :description="$gettext('Laden Sie eine Audiodatei hoch und klicken Sie dann auf \'Transkription erstellen\'.')"
        :confirm-text="status === 'configure' ? $gettext('Transkription erstellen') : null"
        confirm-class="accept"
        :confirm-disabled="form.processing"
        :close-text="$gettext('Abbrechen')"
        @update:open="setIsOpen"
        @confirm="onConfirm"
    >
        <template #dialogContent>
            <form class="default studipform" @submit.prevent="onConfirm" v-if="file">
                <template v-if="status === 'configure'">
                    <div class="formpart">
                        <label class="studiprequired">
                            <span class="textlabel">
                                {{ $gettext("Audiodatei") }}
                            </span>
                            <span class="asterisk" :title="$gettext('Dies ist ein Pflichtfeld')" aria-hidden="true"
                                >*</span
                            >
                            <div>
                                {{ file.name }} ({{ format(filesize) }})
                                <input
                                    hidden
                                    type="file"
                                    accept="audio/*"
                                    required="required"
                                    :value="form.audio"
                                    @input="form.audio = $event.target.files[0]"
                                />
                            </div>
                        </label>
                    </div>

                    <div class="formpart">
                        <label class="studiprequired">
                            <span class="textlabel">
                                {{ $gettext("Sprache der Datei") }}
                            </span>
                            <span class="asterisk" :title="$gettext('Dies ist ein Pflichtfeld')" aria-hidden="true"
                                >*</span
                            >
                            <div>
                                <select v-model="form.language">
                                    <option v-for="[key, entry] of Object.entries(LANGUAGES)" :value="key">
                                        {{ entry.label }}
                                    </option>
                                </select>
                            </div>
                        </label>
                    </div>
                </template>

                <template v-if="status === 'upload'">
                    <ProgressIndicator description="Uploading …" />
                </template>

                <template v-if="status === 'success'"> Success! </template>

                <template v-if="status === 'error'">
                    <MessageBox v-for="(entry, key) of form.errors" :key="key" type="error" hide-details hide-close>{{
                        entry
                    }}</MessageBox>
                </template>
            </form>
        </template>
    </StudipDialog>
</template>
