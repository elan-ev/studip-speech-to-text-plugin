<script setup>
import { useRoute } from "@elan-ev/studip-named-routes";
import { useQuery, useQueryClient } from "@tanstack/vue-query";
import { computed, onMounted, onBeforeUnmount, ref } from "vue";
import { format } from "@/Composables/use-file-size.js";
import { useApi } from "@/Composables/use-api.js";
import DialogDeleteJob from "@/Components/DialogDeleteJob.vue";
import DialogNewJob from "@/Components/DialogNewJob.vue";
import TranscriptionJob from "@/Components/TranscriptionJob.vue";
import UploadBox from "@/Components/UploadBox.vue";
import UploadIcon from "@/Components/UploadIcon.vue";
import UploadQuota from "@/Components/UploadQuota.vue";

const route = useRoute();
const { fetchJobs, deleteJob } = useApi();

const queryClient = useQueryClient();
const { isPending, isFetching, isError, isSuccess, data, error } = useQuery({
  queryKey: ["jobs"],
  queryFn: async () => {
    const { data } = await fetchJobs();

    return data;
  },
});

const props = defineProps({ usage: Number, MAX_UPLOAD: Number, QUOTA: Number });

const newJobStatus = ref(null);
const showNewJobDialog = ref(false);
const showRemoveJobDialog = ref(false);
const selectedJob = ref(null);
const uploadFile = ref(null);

const latest = computed(() => _.maxBy(data.value, (job) => new Date(job.chdate)));
const sortedJobs = computed(() => _.sortBy(data.value, ["mkdate"]).reverse());

const onReceive = () => {
  queryClient.invalidateQueries({ queryKey: ["jobs"] });
};
const onSend = () => ({ since: latest.value?.chdate ?? null });
onMounted(() => STUDIP.JSUpdater.register("SpeechToTextPlugin", onReceive, onSend));
onBeforeUnmount(() => STUDIP.JSUpdater.unregister("SpeechToTextPlugin"));

const onConfirmTrashJob = (job) => {
  showRemoveJobDialog.value = true;
  selectedJob.value = job;
};

const onTrashJob = () => {
  const id = selectedJob.value.id;

  showRemoveJobDialog.value = false;
  selectedJob.value = null;

  deleteJob(id).then(() => {
    queryClient.invalidateQueries({ queryKey: ["jobs"] });
  });
};

const onUpload = ({ file }) => {
  showNewJobDialog.value = true;
  uploadFile.value = file;
  newJobStatus.value = "configure";
};
</script>

<template>
  <UploadBox @upload="onUpload">
    <template #icon>
      <UploadIcon style="height: 100px; width: 100px" :heartbeat="newJobStatus === 'upload'" />
    </template>

    <template #quota>
      <UploadQuota :quota="QUOTA" :usage="usage" />
    </template>

    <template v-if="!['upload', 'success'].includes(newJobStatus)">
      <strong>{{ "Audio-Datei auswählen oder per Drag & Drop hierher ziehen" }}</strong>
      <p style="margin-block-start: 1em">
        {{ "Maximale Dateigröße für Uploads:" }}
        {{ format(MAX_UPLOAD) }}
      </p>
    </template>

    <template v-if="newJobStatus === 'upload'">
      <p style="margin-block: 1em; font-weight: bold">
        {{ "Datei wird hochgeladen …" }}
      </p>
    </template>

    <template v-if="newJobStatus === 'success'">
      <p style="margin-block-start: 1em; font-weight: bold">
        {{ "Ihre Datei wurde erfolgreich hochgeladen. Die Audio-Transkription erfolgt in Kürze." }}
      </p>
      <p style="margin-block-end: 1.5em; font-weight: bold">
        {{ "Das Transkript erhalten Sie automatisch per E-Mail." }}
      </p>
    </template>
  </UploadBox>

  <div v-if="isSuccess" class="speech-to-text-table-wrapper">
    <table class="default sortable-table" data-sortlist="[[3,1]]" v-if="sortedJobs?.length">
      <thead>
        <tr class="sortable">
          <th data-sort="text">{{ "Audiodatei" }}</th>
          <th data-sort="digit">{{ "Dateigröße" }}</th>
          <th>{{ "Transkription" }}</th>
          <th data-sort="text">{{ "Erstellt am" }}</th>
          <th class="actions">
            <span class="sr-only">{{ "Aktionen" }}</span>
          </th>
        </tr>
      </thead>
      <tbody v-if="!isPending">
        <TranscriptionJob v-for="job in sortedJobs" :job="job" :key="job.id" @trash="onConfirmTrashJob" />
      </tbody>
    </table>
  </div>

  <DialogNewJob v-model:open="showNewJobDialog" :file="uploadFile" v-model:status="newJobStatus" />
  <DialogDeleteJob v-model:open="showRemoveJobDialog" @confirm="onTrashJob" />
</template>

<style>
.speech-to-text-table-wrapper {
  width: 100%;
  max-width: 1080px;
  overflow-x: auto;
}
.speech-to-text-table-wrapper th,
.speech-to-text-table-wrapper td {
  padding: 0.5rem;
}
.speech-to-text-table-wrapper td:first-child {
  min-width: 150px;
  max-width: 150px;
}
.speech-to-text-table-wrapper td:last-child {
  word-break: break-all;
}
</style>