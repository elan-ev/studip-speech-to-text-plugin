<script setup>
import { useRoute } from '@elan-ev/studip-named-routes';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { format } from '../../Composables/use-file-size.js';
import DialogDeleteJob from '../../Components/DialogDeleteJob.vue';
import DialogNewJob from '../../Components/DialogNewJob.vue';
import TranscriptionJob from '../../Components/TranscriptionJob.vue';
import UploadBox from '../../Components/UploadBox.vue';
import UploadIcon from '../../Components/UploadIcon.vue';
import UploadQuota from '../../Components/UploadQuota.vue';

const route = useRoute();

const props = defineProps({ jobs: Array, usage: Number, MAX_UPLOAD: Number, QUOTA: Number });

const newJobStatus = ref(null);
const showNewJobDialog = ref(false);
const showRemoveJobDialog = ref(false);
const selectedJob = ref(null);
const uploadFile = ref(null);

const latest = computed(() => _.maxBy(props.jobs, (job) => new Date(job.chdate)));
const sortedJobs = computed(() => _.sortBy(props.jobs, ['mkdate']).reverse());

const onReceive = () => {
    router.reload({ only: ['jobs'] });
};
const onSend = () => ({ since: latest.value?.chdate ?? null });
onMounted(() => STUDIP.JSUpdater.register('SpeechToTextPlugin', onReceive, onSend));
onBeforeUnmount(() => STUDIP.JSUpdater.unregister('SpeechToTextPlugin'));

const onConfirmTrashJob = (job) => {
    showRemoveJobDialog.value = true;
    selectedJob.value = job;
};

const onTrashJob = () => {
    const id = selectedJob.value.id;

    showRemoveJobDialog.value = false;
    selectedJob.value = null;

    router.delete(route('jobs.delete', { id }));
}

const onUpload = ({ file }) => {
    showNewJobDialog.value = true;
    uploadFile.value = file;
    newJobStatus.value = 'configure';
};
</script>

<template>
    <Head :title="'pages.welcome.title'" />

    <UploadBox @upload="onUpload">
        <template #icon>
            <UploadIcon
                style="height: 100px; width:100px;"
                :heartbeat="newJobStatus === 'upload'"
                />
        </template>

        <template #quota>
            <UploadQuota :quota="QUOTA" :usage="usage" />
        </template>

        <template  v-if="!['upload', 'success'].includes(newJobStatus)">
            <strong>{{ $gettext("Audio-Datei auswählen oder per Drag & Drop hierher ziehen") }}</strong>
            <p style="margin-block-start: 1em;">
                {{ $gettext("Maximale Dateigröße für Uploads:") }}
                {{ format(MAX_UPLOAD) }}
            </p>
        </template>

        <template  v-if="newJobStatus === 'upload'">
            <p style="margin-block: 1em; font-weight: bold;">
                {{ $gettext('Datei wird hochgeladen …') }}
            </p>
        </template>

        <template  v-if="newJobStatus === 'success'">
            <p style="margin-block-start: 1em; font-weight: bold;">
                {{ $gettext('Ihre Datei wurde erfolgreich hochgeladen. Die Audio-Transkription erfolgt in Kürze.') }}
            </p>
            <p style="margin-block-end: 1.5em; font-weight: bold;">
                {{ $gettext('Das Transkript erhalten Sie automatisch per E-Mail.') }}
            </p>
        </template>
    </UploadBox>

    <div class="speech-to-text-table-wrapper">
    <table class="default sortable-table" data-sortlist="[[3,1]]" v-if="sortedJobs.length">
        <thead>
            <tr class="sortable">
                <th data-sort="text">{{ $gettext('Audiodatei') }}</th>
                <th data-sort="digit">{{ $gettext('Dateigröße') }}</th>
                <th>{{ $gettext('Transkription') }}</th>
                <th data-sort="text">{{ $gettext('Erstellt am') }}</th>
                <th class="actions"><span class="sr-only">{{ $gettext('Aktionen') }}</span></th>
            </tr>
        </thead>
        <tbody>
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
