<script setup>
import { computed, reactive, toRaw, watch } from "vue";
import { useRoute } from "@elan-ev/studip-named-routes";
import { useQueryClient } from "@tanstack/vue-query";
import { useApi } from "@/Composables/use-api.js";
import { DEFAULT_LANGUAGE, LANGUAGES } from "../config.js";
import StudipDialog from "./base/StudipDialog.vue";
import MessageBox from "./base/StudipMessageBox.vue";
import ProgressIndicator from "./base/StudipProgressIndicator.vue";
import { format } from "../Composables/use-file-size";

const props = defineProps(["open", "file", "status"]);
const emit = defineEmits(["update:open", "update:status"]);

const form = reactive({
  audio: null,
  diarize: true,
  language: DEFAULT_LANGUAGE,
  processing: false,
  errors: null,
});

const route = useRoute();
const { createJob } = useApi();
const queryClient = useQueryClient();

const setIsOpen = (value) => emit("update:open", value);

const updateStatus = (status) => emit("update:status", status);

const onConfirm = async () => {
  form.processing = true;
  updateStatus("upload");
  try {
    const response = await createJob({ ...toRaw(form), audio: props.file });

    if (!response.ok) {
      const { errors } = await response.json();
      form.errors = errors;
      throw new Error();
    }
    setIsOpen(false);
    updateStatus("success");
  } catch (error) {
    updateStatus("error");
  } finally {
    form.processing = false;
    queryClient.invalidateQueries({ queryKey: ["jobs"] });
  }
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
    :title="'Transkription erstellen'"
    :description="'Laden Sie eine Audiodatei hoch und klicken Sie dann auf \'Transkription erstellen\'.'"
    :confirm-text="status === 'configure' ? 'Transkription erstellen' : null"
    confirm-class="accept"
    :confirm-disabled="form.processing"
    :close-text="'Abbrechen'"
    :height="350"
    @update:open="setIsOpen"
    @confirm="onConfirm"
  >
    <template #dialogContent>
      <form class="default studipform" @submit.prevent="onConfirm" v-if="file">
        <template v-if="status === 'configure'">
          <div class="formpart">
            <label class="studiprequired">
              <span class="textlabel">
                {{ "Audiodatei" }}
              </span>
              <span class="asterisk" :title="'Dies ist ein Pflichtfeld'" aria-hidden="true">*</span>
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
                {{ "Sprache der Datei" }}
              </span>
              <span class="asterisk" :title="'Dies ist ein Pflichtfeld'" aria-hidden="true">*</span>
              <div>
                <select v-model="form.language">
                  <option v-for="[key, entry] of Object.entries(LANGUAGES)" :value="key">
                    {{ entry.label }}
                  </option>
                </select>
              </div>
            </label>
          </div>

          <div class="formpart">
            <label class="studiprequired">
              <input
                v-model="form.diarize"
                type="checkbox"
                name="diarize"
                required
                aria-required="true"
                :true-value="1"
                :false-value="0"
              />
              <span class="textlabel">
                {{ "Identifizierung der Sprechenden" }}
              </span>
              <span class="asterisk" :title="'Dies ist ein Pflichtfeld'" aria-hidden="true">*</span>
            </label>
          </div>
        </template>

        <template v-if="status === 'upload'">
          <ProgressIndicator description="Uploading …" />
        </template>

        <template v-if="status === 'success'"> Success! </template>

        <template v-if="status === 'error'">
          <MessageBox v-for="entry in form.errors" :key="entry.id" type="error" hide-details hide-close>{{
            entry.title
          }}</MessageBox>
        </template>
      </form>
    </template>
  </StudipDialog>
</template>

<style scoped>
.formpart + .formpart {
  margin-block-start: 1rem;
}
</style>