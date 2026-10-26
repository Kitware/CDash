<template>
  <div class="tw-flex tw-flex-col sm:tw-flex-row sm:tw-items-start tw-gap-4 tw-pb-6 tw-border-b tw-border-base-300">
    <div class="tw-flex tw-flex-row tw-items-start tw-gap-4 tw-flex-grow tw-min-w-0">
      <ProjectLogo
        :image-url="logoUrl"
        :project-name="projectName"
        class="tw-w-14 tw-h-14 tw-shrink-0 tw-text-2xl"
      />
      <div class="tw-flex tw-flex-col tw-gap-1 tw-min-w-0">
        <div class="tw-flex tw-flex-row tw-flex-wrap tw-items-baseline tw-gap-x-2 tw-gap-y-1">
          <h1
            class="tw-text-2xl tw-font-bold tw-m-0 tw-break-words"
            data-test="project-name"
          >
            {{ projectName }}
          </h1>
          <span
            class="tw-badge tw-badge-outline tw-gap-1 tw-text-neutral-500"
            data-test="project-visibility"
          >
            <FontAwesomeIcon :icon="visibilityDetails.icon" />
            {{ visibilityDetails.label }}
          </span>
        </div>
        <div
          v-if="description"
          class="tw-whitespace-pre-line tw-break-words tw-text-neutral-500"
          data-test="project-description"
        >
          {{ description }}
        </div>
      </div>
    </div>
    <div class="tw-flex tw-flex-row tw-gap-2 tw-shrink-0">
      <a
        v-if="canUpdate"
        class="tw-btn tw-btn-sm tw-btn-ghost tw-border tw-border-base-300 tw-font-medium"
        :href="`${$baseURL}/projects/${projectId}/settings`"
        data-test="settings-button"
      >
        <FontAwesomeIcon :icon="FA.faGear" />
        Settings
      </a>
      <a
        class="tw-btn tw-btn-sm tw-btn-primary tw-font-medium"
        :href="dashboardUrl"
        data-test="view-dashboard-button"
      >
        View Dashboard
        <FontAwesomeIcon :icon="FA.faArrowRight" />
      </a>
    </div>
  </div>
</template>

<script>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
  faArrowRight,
  faEarthAmericas,
  faGear,
  faLock,
  faShieldHalved,
} from '@fortawesome/free-solid-svg-icons';
import ProjectLogo from '../shared/ProjectLogo.vue';

export default {
  name: 'ProjectHeader',

  components: {
    FontAwesomeIcon,
    ProjectLogo,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },

    projectName: {
      type: String,
      required: true,
    },

    description: {
      type: [String, null],
      default: null,
    },

    logoUrl: {
      type: [String, null],
      default: null,
    },

    // One of PUBLIC, PROTECTED, or PRIVATE.
    visibility: {
      type: String,
      required: true,
    },

    dashboardUrl: {
      type: String,
      required: true,
    },

    canUpdate: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    FA() {
      return {
        faArrowRight,
        faGear,
      };
    },

    visibilityDetails() {
      switch (this.visibility) {
        case 'PUBLIC':
          return { label: 'Public', icon: faEarthAmericas };
        case 'PROTECTED':
          return { label: 'Protected', icon: faShieldHalved };
        case 'PRIVATE':
          return { label: 'Private', icon: faLock };
        default:
          throw `Invalid visibility ${this.visibility}`;
      }
    },
  },
};
</script>
