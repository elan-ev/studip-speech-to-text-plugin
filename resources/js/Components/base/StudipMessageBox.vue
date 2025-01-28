<template>
    <div v-if="!closed"
         role="region"
         :aria-label="label"
         :aria-describedby="`messagebox-${counter}`"
         :class="classNames"
    >
        <div class="messagebox_buttons">
            <a v-if="details.length && hideDetails" class="details" href="" :title="$gettext('Detailanzeige umschalten')" @click.prevent.stop="closedDetails = !closedDetails">
                <span>{{ $gettext('Detailanzeige umschalten') }}</span>
            </a>
            <a v-if="!hideClose" class="close" href="" :title="$gettext('Nachrichtenbox schließen')" @click.prevent="close()">
                <span>{{ $gettext('Nachrichtenbox schließen') }}</span>
            </a>
        </div>
        <div role="status" :id="`messagebox-${counter}`">
            <slot></slot>
            <div v-if="showDetails" class="messagebox_details">
                <slot name="details">
                    <ul>
                        <li v-for="(detail, index) in details" v-html="detail" :key="index"></li>
                    </ul>
                </slot>
            </div>
        </div>
    </div>
</template>

<script>
let counter = 0;

export default {
    name: 'studip-message-box',
    emits: ['close'],
    props: {
        type: {
            type: String, // exception, error, success, info, warning
            default: 'info',
            validator (type) {
                return ['exception', 'error', 'warning', 'success', 'info'].indexOf(type) !== -1;
            }
        },
        details: {
            type: Array,
            default: () => [],
        },
        hideDetails: {
            type: Boolean,
            default: false
        },
        hideClose: {
            type: Boolean,
            default: false,
        },
    },
    computed: {
        classNames() {
            return {
                messagebox: true,
                [`messagebox_${this.type}`]: true,
                details_hidden: this.hasDetails && !this.showDetails,
            };
        },
        hasDetails() {
            return !!this.$slots.details || this.details.length > 0;
        },
        label() {
            const labels = {
                exception: this.$gettext('Systemfehler'),
                error:     this.$gettext('Fehler'),
                warning:   this.$gettext('Warnung'),
                info:      this.$gettext('Hinweis'),
                success:   this.$gettext('Erfolg'),
            }
            return labels[this.type];
        },
        showDetails() {
            return this.hasDetails && !this.closedDetails;
        }
    },
    methods: {
        close() {
            this.closed = true;

            this.$emit('close');
        }
    },
    data() {
        return {
            closed: false,
            closedDetails: this.hideDetails,
            counter: counter++
        };
    },
};
</script>
