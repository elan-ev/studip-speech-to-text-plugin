import { useRoute } from "@elan-ev/studip-named-routes";
import Kitsu from "kitsu";

const absoluteUriStudip = new URL(window.STUDIP.ABSOLUTE_URI_STUDIP);
const baseURL = `${absoluteUriStudip.pathname}jsonapi.php/v1/`;
const api = new Kitsu({ baseURL, camelCaseTypes: false, pluralize: false, resourceCase: "kebab" });

export function useApi() {
    const route = useRoute();

    const fetchJobs = () => {
        return api.get("speechtotext-jobs", {
            params: {
                page: {
                    limit: 100000,
                    offset: 0,
                },
            },
        });
    };

    const createJob = ({ audio, diarize, language }) => {
        const formData = new FormData();
        formData.append("diarize", diarize);
        formData.append("language", language);
        formData.append("audio", audio);

        return fetch(route("jobs.store"), {
            method: "POST",
            body: formData,
        });
    };

    const deleteJob = (id) => {
        return fetch(route("jobs.delete", { id }), {
            method: "DELETE",
        });
    };

    return {
        createJob,
        deleteJob,
        fetchJobs,
    };
}