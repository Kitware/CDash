<template>
  <div
    v-if="loadFailed"
    class="tw-border tw-border-base-300 tw-rounded-lg tw-px-4 tw-py-6 tw-text-center tw-text-neutral-500"
    data-test="build-groups-error"
  >
    The build groups could not be loaded.
  </div>
  <LoadingIndicator
    v-else
    :is-loading="!groups"
  >
    <section
      v-if="groups.length > 0"
      class="tw-border tw-border-base-300 tw-rounded-lg tw-overflow-hidden"
      data-test="build-groups-section"
    >
      <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-x-2 tw-gap-y-1 tw-px-4 tw-py-2 tw-bg-base-200 tw-border-b tw-border-base-300">
        <FontAwesomeIcon
          :icon="FA.faLayerGroup"
          class="tw-text-neutral-500"
        />
        <span class="tw-font-bold">Build Groups</span>
        <BuildHistogramLegend class="tw-ml-auto" />
      </div>
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3 tw-p-3 tw-bg-base-200/50">
        <BuildGroupCard
          v-for="group in groups"
          :key="group.id"
          :name="group.name"
          :description="group.description"
          :builds-last-day="group.buildsLastDay"
          :builds-last-week="group.buildsLastWeek"
          :last-submission-time="group.lastSubmissionTime"
          :histogram="group.histogram"
          :histogram-load-failed="histogramsLoadFailed"
          :url="group.url"
        />
      </div>
    </section>
  </LoadingIndicator>
</template>

<script>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faLayerGroup } from '@fortawesome/free-solid-svg-icons';
import LoadingIndicator from '../shared/LoadingIndicator.vue';
import BuildGroupCard from './BuildGroupCard.vue';
import BuildHistogramLegend from './BuildHistogramLegend.vue';

export default {
  name: 'BuildGroupsSection',

  components: {
    BuildGroupCard,
    BuildHistogramLegend,
    FontAwesomeIcon,
    LoadingIndicator,
  },

  props: {
    /**
     * The active build groups, or null while loading.  Each group has the props of a BuildGroupCard.
     *
     * @type {Array<Object>|null}
     */
    groups: {
      type: [Array, null],
      required: true,
    },

    loadFailed: {
      type: Boolean,
      default: false,
    },

    histogramsLoadFailed: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    FA() {
      return {
        faLayerGroup,
      };
    },
  },
};
</script>
