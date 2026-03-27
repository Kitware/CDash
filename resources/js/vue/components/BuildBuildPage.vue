<template>
  <BuildSidebar
    :build-id="buildId"
    active-tab="build"
  >
    <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
      <build-summary-card :build-id="buildId" />

      <loading-indicator :is-loading="!build">
        <div
          class="tw-border tw-p-2 tw-rounded-lg tw-flex tw-flex-col tw-gap-2"
          data-test="build-info"
        >
          <div
            v-if="build.compilerName"
            data-test="compiler-name"
          >
            <div class="tw-font-bold">
              Compiler
            </div>
            <code-box :text="build.compilerName" />
          </div>

          <div
            v-if="build.compilerVersion"
            data-test="compiler-version"
          >
            <div class="tw-font-bold">
              Compiler Version
            </div>
            <code-box :text="build.compilerVersion" />
          </div>

          <div
            v-if="build.generator"
            data-test="generator"
          >
            <div class="tw-font-bold">
              Generator
            </div>
            <code-box :text="build.generator" />
          </div>

          <div
            v-if="build.sourceDirectory"
            data-test="source-directory"
          >
            <div class="tw-font-bold">
              Source Directory
            </div>
            <code-box :text="build.sourceDirectory" />
          </div>

          <div
            v-if="build.binaryDirectory"
            data-test="binary-directory"
          >
            <div class="tw-font-bold">
              Binary Directory
            </div>
            <code-box :text="build.binaryDirectory" />
          </div>

          <div
            v-if="build.command"
            data-test="command"
          >
            <div class="tw-font-bold">
              Build Command
            </div>
            <code-box :text="build.command" />
          </div>
        </div>
      </loading-indicator>

      <loading-indicator :is-loading="!buildWithErrors">
        <div
          v-if="buildWithErrors.children.edges.length > 0"
          class="tw-join tw-join-vertical tw-w-full"
        >
          <details
            v-for="{ node: childBuild } in buildWithErrors.children.edges"
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
                :repository-type="repositoryType"
                :repository-url="repositoryUrl"
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
            :repository-type="repositoryType"
            :repository-url="repositoryUrl"
          />
        </div>
      </loading-indicator>
    </div>
  </BuildSidebar>
</template>

<script>
import gql from 'graphql-tag';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSidebar from './shared/BuildSidebar.vue';
import {
  faCircleExclamation,
  faTriangleExclamation,
} from '@fortawesome/free-solid-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import BuildErrorList from './BuildBuildPage/BuildErrorList.vue';
import CodeBox from './shared/CodeBox.vue';

const BUILD_ERRORS_QUERY = gql`
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
  components: {
    CodeBox,
    BuildErrorList,
    FontAwesomeIcon,
    LoadingIndicator,
    BuildSummaryCard,
    BuildSidebar,
  },

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

    repositoryType: {
      type: [String, null],
      required: true,
    },

    repositoryUrl: {
      type: [String, null],
      required: true,
    },
  },

  apollo: {
    build: {
      query: gql`
        query($buildid: ID) {
          build(id: $buildid) {
            id
            command
            sourceDirectory
            binaryDirectory
            generator
            compilerName
            compilerVersion
          }
        }
      `,
      variables() {
        return {
          buildid: this.buildId,
        };
      },
    },

    buildWithErrors: {
      query: BUILD_ERRORS_QUERY,
      update: (data) => data.build,
      variables() {
        return {
          buildid: this.buildId,
        };
      },
    },

    previousBuildWithErrors: {
      query: BUILD_ERRORS_QUERY,
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

      if (this.previousBuildWithErrors) {
        this.buildWithErrors.children.edges.forEach(({node: currentBuildNode}) => {
          if (currentBuildNode.subProject) {
            this.previousBuildWithErrors.children.edges.forEach(({node: previousBuildNode}) => {
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
