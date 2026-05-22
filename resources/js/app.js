import { createApp, h } from "vue";
import { NamedRoutes } from "@elan-ev/studip-named-routes";
import { VueQueryPlugin } from "@tanstack/vue-query";
import JobsIndex from "./Pages/Jobs/Index.vue";

import "../css/main.css";

// load courseware's CSS
STUDIP.loadChunk("courseware", { silent: true }).catch(() => {});

const element = document.getElementById("app");
const props = JSON.parse(element?.dataset?.page) ?? "{}";
const app = createApp({ render: () => h(JobsIndex, props) });
app.use(NamedRoutes, window.NamedRoutes);
app.use(VueQueryPlugin);
app.mount(element);