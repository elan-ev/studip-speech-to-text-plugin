import { computed } from 'vue';

export function format(bytes, decimals = 2) {
    if (bytes === 0) {
        return '0 Bytes';
    }
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

export function useFileSize(file) {

    const formatFileSize = computed(() => {
        const filesize = file.value?.size;
        if (!filesize || filesize <= 0) {
            return "0 Bytes";
        }

        return format(filesize, 1);
    });

    return {
        formatFileSize
    };
}
