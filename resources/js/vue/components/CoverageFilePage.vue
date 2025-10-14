<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <BuildSummaryCard :build-id="buildId" />

    <LoadingIndicator :is-loading="!coverage">
      <div class="tw-border-base-300 tw-bg-base-200 tw-border tw-rounded tw-p-2 tw-flex tw-flex-row tw-w-full tw-gap-4">
        <div class="tw-font-mono">
          {{ coverage.filePath }}
        </div>
        <div class="tw-flex-grow" />
        <div>
          {{ coverage.linesOfCodeTested }} / {{ totalLines }} lines covered
          <template v-if="!isNaN(percentLinesCovered)">
            ({{ percentLinesCovered }}%)
          </template>
        </div>
      </div>

      <CoverageViewer
        :file="coverage.file"
        :coverage-lines="coverage.coveredLines"
      />
    </LoadingIndicator>
  </div>
</template>

<script>
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import gql from 'graphql-tag';
import CoverageViewer from './shared/CoverageViewer.vue';

export default {
  components: {CoverageViewer, LoadingIndicator, BuildSummaryCard},

  props: {
    buildId: {
      type: Number,
      required: true,
    },

    fileId: {
      type: Number,
      required: true,
    },
  },

  computed: {
    totalLines() {
      return parseInt(this.coverage.linesOfCodeTested) + parseInt(this.coverage.linesOfCodeUntested);
    },

    percentLinesCovered() {
      return parseFloat((parseInt(this.coverage.linesOfCodeTested) / this.totalLines) * 100).toFixed(2);
    },

  },

  apollo: {
    coverage: {
      query: gql`
        query($buildId: ID!, $fileId: ID!) {
          build(id: $buildId) {
            id
            coverage(filters: {
              eq: {
                id: $fileId
              }
            }) {
              edges {
                node {
                  id
                  file
                  filePath
                  linesOfCodeTested
                  linesOfCodeUntested
                  coveredLines {
                    lineNumber
                    timesHit
                    totalBranches
                    branchesHit
                  }
                }
              }
            }
          }
        }
      `,
      update: data => data?.build?.coverage?.edges?.[0]?.node,
      variables() {
        return {
          buildId: this.buildId,
          fileId: this.fileId,
        };
      },
    },
  },
};
</script>
