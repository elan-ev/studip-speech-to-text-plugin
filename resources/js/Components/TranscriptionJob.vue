<script setup>
import { computed } from 'vue';
import { useGettext } from 'vue3-gettext';
import OutputBadge from './OutputBadge.vue';
import StudipActionMenu from './base/StudipActionMenu.vue';
import StudipDatetime from './base/StudipDatetime.vue';
import StudipIcon from './base/StudipIcon.vue';
import { format } from '../Composables/use-file-size';

const { $gettext } = useGettext();

const props = defineProps(['job']);
defineEmits(['trash']);

const actionMenu = [{ id: 'trash', label: $gettext('Löschen'), icon: 'trash', emit: 'trash' }];
const inputFileRef = computed(() => props.job?.input_file_ref ?? null);
const status = computed(() => {
    switch (props.job?.status) {
    case 'starting':
    case 'started':
        return $gettext("gestartet");
    case 'processing':
        return $gettext("in Bearbeitung");
    case 'succeeded':
        return $gettext("erfolgreich");
    case 'failed':
        return $gettext("fehlgeschlagen");
    case 'canceled':
        return $gettext("abgebrochen");

    default:
        return '–';
    }
});
</script>

<template>
    <tr>
        <td :data-sort-value="inputFileRef?.name ?? ''">
            <StudipIcon shape="file-sound" role="info" />
            <span>{{ inputFileRef.name }}</span>
        </td>

        <td :data-sort-value="inputFileRef?.filesize ?? 0">
            {{ format(inputFileRef?.filesize ?? 0) }}
        </td>

        <td v-if="Object.keys(job.output_file_refs).length">
            <div>
                <OutputBadge v-for="(fileRef, ext) in job.output_file_refs" :file="fileRef" />
            </div>
        </td>
        <td v-else>
            <span v-if="job.prediction.error">
                {{ $gettext("Fehler") }}
            </span>
            <span v-else>
                {{ status }}
            </span>
        </td>

        <td :data-sort-value="job.mkdate">
            <StudipDatetime :date="new Date(job.mkdate)" />
        </td>

        <td class="actions">
            <StudipActionMenu :items="actionMenu" @trash="$emit('trash', job)" />
        </td>
    </tr>
</template>

<style scoped>
td:nth-child(3) div {
    display: flex;
    gap: 0.5rem;
}
</style>
