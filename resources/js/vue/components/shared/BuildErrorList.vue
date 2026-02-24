<template>
  <div class="tw-flex tw-flex-col tw-gap-4">
    <loading-indicator :is-loading="!build || (!previousBuild && previousBuildId !== null)">
      <div
        v-if="filteredWarnings.length === 0 && filteredErrors.length === 0"
        class="tw-self-center"
      >
        No {{ showNewErrors ? 'new' : (showFixedErrors ? 'fixed' : '') }} errors or warnings for this build.
      </div>

      <div v-if="filteredErrors.length > 0">
        <div class="tw-divider tw-divider-error">
          {{ filteredErrors.length }}
          {{ showNewErrors ? 'NEW' : (showFixedErrors ? 'FIXED' : '') }}
          {{ filteredErrors.length === 1 ? 'ERROR' : 'ERRORS' }}
        </div>
        <div class="tw-flex tw-flex-col tw-gap-2">
          <div
            v-for="{ node: buildError } in filteredErrors"
            :key="buildError.id"
            class="tw-p-2 tw-border-2 tw-rounded-md"
          >
            <build-error-item :build-error="buildError" />
          </div>
        </div>
      </div>

      <div v-if="filteredWarnings.length > 0">
        <div class="tw-divider tw-divider-warning">
          {{ filteredWarnings.length }}
          {{ showNewErrors ? 'NEW' : (showFixedErrors ? 'FIXED' : '') }}
          {{ filteredWarnings.length === 1 ? 'WARNING' : 'WARNINGS' }}
        </div>
        <div class="tw-flex tw-flex-col tw-gap-2">
          <div
            v-for="{ node: buildWarning } in filteredWarnings"
            :key="buildWarning.id"
            class="tw-p-2 tw-border-2 tw-rounded-md"
          >
            <build-error-item :build-error="buildWarning" />
          </div>
        </div>
      </div>
    </loading-indicator>
  </div>
</template>

<script>
import gql from 'graphql-tag';
import LoadingIndicator from './LoadingIndicator.vue';
import BuildErrorItem from './BuildErrorItem.vue';

const BUILD_ERROR_FIELDS = `
  edges {
    node {
      id
      type
      sourceFile
      stdOutput
      stdError
      workingDirectory
      exitCondition
      language
      targetName
      outputFile
      outputType
      sourceLine
      command
      labels {
        edges {
          node {
            id
            text
          }
        }
      }
    }
  }
`;

const BUILD_QUERY = gql`
  query($buildid: ID) {
    build(id: $buildid) {
      id
      buildWarnings: buildErrors(filters: {
        eq: {
          type: WARNING
        }
      }, first: 100000) {
          ${BUILD_ERROR_FIELDS}
      }
      buildErrors: buildErrors(filters: {
        eq: {
          type: ERROR
        }
      }, first: 100000) {
        ${BUILD_ERROR_FIELDS}
      }
    }
  }
`;

export default {
  components: {BuildErrorItem, LoadingIndicator},

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
    filteredWarnings() {
      return this.filterErrors(this.build.buildWarnings.edges, this.previousBuild?.buildWarnings.edges ?? []);
    },

    filteredErrors() {
      return this.filterErrors(this.build.buildErrors.edges, this.previousBuild?.buildErrors.edges ?? []);
    },
  },

  methods: {
    filterErrors(currentErrorList, previousErrorList) {
      // If we weren't explicitly asked to show only the new or fixed errors, return the full list.
      if (!this.showNewErrors && !this.showFixedErrors) {
        return currentErrorList;
      }

      const currentErrorSet = new Set(currentErrorList.map(({ node }) => {
        return this.stringifyError(node);
      }));

      const previousErrorSet = new Set(previousErrorList.map(({ node }) => {
        return this.stringifyError(node);
      }));

      if (this.showNewErrors) {
        return currentErrorList.filter(({ node }) => {
          return !previousErrorSet.has(this.stringifyError(node));
        });
      }
      else if (this.showFixedErrors) {
        // Note: showing the fixed errors actually shows errors for the previous build.
        // This may be confusing to users and should be re-evaluated in the future.
        return previousErrorList.filter(({ node }) => {
          return !currentErrorSet.has(this.stringifyError(node));
        });
      }
      else {
        throw 'Invalid state.';
      }
    },

    stringifyError(error) {
      return JSON.stringify([
        error.type,
        error.sourceFile,
        error.stdError,
        error.language,
        error.targetName,
        error.outputFile,
        error.outputType,
      ]);
    },
  },
};
</script>
