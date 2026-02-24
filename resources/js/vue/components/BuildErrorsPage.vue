<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <build-summary-card :build-id="buildId" />

    <loading-indicator :is-loading="!build">
      <div
        v-if="build.children.edges.length > 0"
        class="tw-join tw-join-vertical tw-w-full"
      >
        <details
          v-for="{ node: childBuild } in build.children.edges"
          class="tw-collapse tw-collapse-plus tw-join-item tw-border"
          :data-test="'collapse-' + childBuild.subProject.id"
        >
          <summary class="tw-collapse-title tw-text-xl tw-font-medium">
            <span>{{ childBuild.subProject.name }}</span>
            <span
              v-if="childBuild.buildErrorsCount > 0"
              class="tw-badge tw-ml-2 tw-bg-error"
              :data-test="'errors-' + childBuild.subProject.id"
            ><font-awesome-icon :icon="FA.faCircleExclamation" /> {{ childBuild.buildErrorsCount }}</span>
            <span
              v-if="childBuild.buildWarningsCount > 0"
              class="tw-badge tw-ml-1 tw-bg-warning"
              :data-test="'warnings-' + childBuild.subProject.id"
            ><font-awesome-icon :icon="FA.faTriangleExclamation" /> {{ childBuild.buildWarningsCount }}</span>
          </summary>
          <div class="tw-collapse-content">
            <build-error-list
              :build-id="parseInt(childBuild.id)"
              :previous-build-id="buildIdsToPreviousBuildIds[parseInt(childBuild.id)] ?? null"
              :show-new-errors="showNewErrors"
              :show-fixed-errors="showFixedErrors"
            />
          </div>
        </details>
      </div>
      <div v-else>
        <build-error-list
          :build-id="buildId"
          :previous-build-id="previousBuildId"
          :show-new-errors="showNewErrors"
          :show-fixed-errors="showFixedErrors"
        />
      </div>
    </loading-indicator>
  </div>
</template>

<script>
import gql from 'graphql-tag';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import {
  faCircleExclamation,
  faTriangleExclamation,
} from '@fortawesome/free-solid-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import BuildErrorList from './shared/BuildErrorList.vue';

const BUILD_QUERY = gql`
  query($buildid: ID) {
    build(id: $buildid) {
      id
      children(first: 100000) {
        edges {
          node {
            id
            buildWarningsCount
            buildErrorsCount
            subProject {
              id
              name
            }
          }
        }
      }
    }
  }
`;

export default {
  components: {BuildErrorList: BuildErrorList, FontAwesomeIcon, LoadingIndicator, BuildSummaryCard},
  props: {
    buildId: {
      type: Number,
      required: true,
    },

    previousBuildId: {
      type: [Number, null],
      required: false,
      default: null,
    },

    showNewErrors: {
      type: Boolean,
      required: false,
      default: false,
    },

    showFixedErrors: {
      type: Boolean,
      required: false,
      default: false,
    },
  },

  apollo: {
    build: {
      query: BUILD_QUERY,
      variables() {
        return {
          buildid: this.buildId,
        };
      },
    },
    previousBuild: {
      query: BUILD_QUERY,
      update: (data) => data.build,
      variables() {
        return {
          buildid: this.previousBuildId,
        };
      },
      skip() {
        return this.previousBuildId === null;
      },
    },
  },

  computed: {
    FA() {
      return {
        faCircleExclamation,
        faTriangleExclamation,
      };
    },

    buildIdsToPreviousBuildIds() {
      const retVal = {
        [parseInt(this.buildId)]: parseInt(this.previousBuildId),
      };

      if (this.previousBuild) {
        this.build.children.edges.forEach(({node: currentBuildNode}) => {
          if (currentBuildNode.subProject) {
            this.previousBuild.children.edges.forEach(({node: previousBuildNode}) => {
              if (parseInt(currentBuildNode.subProject.id) === parseInt(previousBuildNode.subProject.id)) {
                retVal[parseInt(currentBuildNode.id)] = parseInt(previousBuildNode.id);
              }
            });
          }
        });
      }

      return retVal;
    },
  },
};
</script>
