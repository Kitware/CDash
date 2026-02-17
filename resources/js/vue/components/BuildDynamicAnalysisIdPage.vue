<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <build-summary-card :build-id="buildId" />

    <loading-indicator :is-loading="!dynamicAnalysis">
      <div class="tw-border-base-300 tw-bg-base-200 tw-border tw-rounded tw-p-2 tw-flex tw-flex-row tw-w-full tw-gap-1">
        <div class="tw-font-mono tw-link tw-link-hover">
          <a :href="link">
            {{ dynamicAnalysis.name }}
          </a>
        </div>
        <div class="tw-flex-grow" />
        <span
          class="tw-badge"
          :class="statusColor"
        />
        <span>{{ status }}</span>
      </div>

      <code-box :text="dynamicAnalysis.log" />
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
import CodeBox from './shared/CodeBox.vue';

export default {
  components: {CodeBox, LoadingIndicator, BuildSummaryCard},
  props: {
    buildId: {
      type: Number,
      required: true,
    },

    dynamicAnalysisId: {
      type: Number,
      required: true,
    },

    link: {
      type: String,
      required: true,
    },
  },

  apollo: {
    dynamicAnalysis: {
      query: gql`
        query($dynamicAnalysisId: ID) {
          dynamicAnalysis(id: $dynamicAnalysisId) {
            id
            name
            status
            log
          }
        }
      `,
      variables() {
        return {
          dynamicAnalysisId: this.dynamicAnalysisId,
        };
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

    status() {
      switch (this.dynamicAnalysis.status) {
      case 'passed':
        return 'Passed';
      case 'notrun':
        return 'Not Run';
      case 'failed':
        return 'Failed';
      default:
        return this.dynamicAnalysis.status;
      }
    },

    statusColor() {
      switch (this.dynamicAnalysis.status) {
      case 'passed':
        return 'tw-bg-green-400';
      case 'notrun':
        return 'tw-bg-orange-400';
      case 'failed':
        return 'tw-bg-red-400';
      default:
        return this.dynamicAnalysis.status;
      }
    },
  },
};
</script>
