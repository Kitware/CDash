<template>
  <div
    class="tw-flex tw-w-full tw-items-start"
    :data-test="!build ? 'sidebar-loading' : 'sidebar-loaded'"
  >
    <aside class="tw-shrink-0 tw-bg-base-200 tw-border tw-border-base-300 tw-rounded-lg tw-overflow-hidden tw-sticky tw-top-[100px] tw-max-h-[calc(100vh-100px)] tw-overflow-y-auto tw-z-10">
      <nav class="tw-flex tw-flex-col tw-py-2">
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}`"
          title="Summary"
          :icon="FA.faCircleInfo"
          :selected="activeTab === 'summary'"
          :disabled="summaryDisabled"
          data-test="sidebar-summary"
        />
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}/update`"
          title="Update"
          :icon="FA.faCodePullRequest"
          :selected="activeTab === 'update'"
          :disabled="updateDisabled"
          data-test="sidebar-update"
        />
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}/configure`"
          title="Configure"
          :icon="FA.faWrench"
          :selected="activeTab === 'configure'"
          :disabled="configureDisabled"
          :badges="configureBadges"
          data-test="sidebar-configure"
        />
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}/build`"
          title="Build"
          :icon="FA.faHammer"
          :selected="activeTab === 'build'"
          :disabled="errorsDisabled"
          :badges="errorsBadges"
          data-test="sidebar-build"
        />
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}/tests`"
          title="Tests"
          :icon="FA.faFlask"
          :selected="activeTab === 'tests'"
          :disabled="testsDisabled"
          :badges="testsBadges"
          data-test="sidebar-tests"
        />
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}/coverage`"
          title="Coverage"
          :icon="FA.faUmbrella"
          :selected="activeTab === 'coverage'"
          :disabled="coverageDisabled"
          data-test="sidebar-coverage"
        />
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}/dynamic_analysis`"
          title="Dynamic Analysis"
          :icon="FA.faBug"
          :selected="activeTab === 'dynamic_analysis'"
          :disabled="dynamicAnalysisDisabled"
          data-test="sidebar-dynamic-analysis"
        />
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}/files`"
          title="Uploads"
          :icon="FA.faFileArrowUp"
          :selected="activeTab === 'files'"
          :disabled="filesDisabled"
          data-test="sidebar-files"
        />
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}/notes`"
          title="Notes"
          :icon="FA.faNoteSticky"
          :selected="activeTab === 'notes'"
          :disabled="notesDisabled"
          data-test="sidebar-notes"
        />
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}/commands`"
          title="Instrumentation"
          :icon="FA.faGaugeHigh"
          :selected="activeTab === 'instrumentation'"
          :disabled="commandsDisabled"
          data-test="sidebar-instrumentation"
        />
        <build-sidebar-item
          :href="`${$baseURL}/builds/${buildId}/targets`"
          title="Targets"
          :icon="FA.faBullseye"
          :selected="activeTab === 'targets'"
          :disabled="targetsDisabled"
          data-test="sidebar-targets"
        />
      </nav>
    </aside>

    <main class="tw-flex-grow tw-pl-4 tw-min-w-0">
      <slot />
    </main>
  </div>
</template>

<script>
import gql from 'graphql-tag';
import BuildSidebarItem from './BuildSidebarItem.vue';
import {
  faCircleInfo,
  faCodePullRequest,
  faWrench,
  faHammer,
  faTriangleExclamation,
  faFlask,
  faUmbrella,
  faBug,
  faFileArrowUp,
  faNoteSticky,
  faGaugeHigh,
  faBullseye,
} from '@fortawesome/free-solid-svg-icons';

export default {
  name: 'BuildSidebar',
  components: {
    BuildSidebarItem,
  },
  props: {
    buildId: {
      type: Number,
      required: true,
    },
    activeTab: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      build: null,
    };
  },
  apollo: {
    build: {
      query: gql`
        query($buildid: ID) {
          build(id: $buildid) {
            id
            updateStep {
              id
            }
            configureWarningsCount
            configureErrorsCount
            buildWarningsCount
            buildErrorsCount
            passedTestsCount
            notRunTestsCount
            failedTestsCount
            coverage {
              pageInfo {
                total
              }
            }
            dynamicAnalyses {
              pageInfo {
                total
              }
            }
            files {
              pageInfo {
                total
              }
            }
            urls {
              pageInfo {
                total
              }
            }
            notes {
              pageInfo {
                total
              }
            }
            commands {
              pageInfo {
                total
              }
            }
            targets {
              pageInfo {
                total
              }
            }
          }
        }
      `,
      variables() {
        return {
          buildid: this.buildId,
        };
      },
    },
  },
  computed: {
    FA() {
      return {
        faCircleInfo,
        faCodePullRequest,
        faWrench,
        faHammer,
        faTriangleExclamation,
        faFlask,
        faUmbrella,
        faBug,
        faFileArrowUp,
        faNoteSticky,
        faGaugeHigh,
        faBullseye,
      };
    },
    summaryDisabled() {
      return !this.build;
    },
    updateDisabled() {
      return !this.build || this.build.updateStep === null;
    },
    configureDisabled() {
      return !this.build || (this.build.configureWarningsCount === -1 && this.build.configureErrorsCount === -1);
    },
    errorsDisabled() {
      return !this.build || (this.build.buildWarningsCount === -1 && this.build.buildErrorsCount === -1);
    },
    testsDisabled() {
      return !this.build || (
        this.build.failedTestsCount + this.build.notRunTestsCount + this.build.passedTestsCount <= 0
      );
    },
    coverageDisabled() {
      return !this.build || !this.build.coverage || this.build.coverage.pageInfo.total === 0;
    },
    dynamicAnalysisDisabled() {
      return !this.build || !this.build.dynamicAnalyses || this.build.dynamicAnalyses.pageInfo.total === 0;
    },
    filesDisabled() {
      if (!this.build) {
        return true;
      }
      const hasFiles = this.build.files && this.build.files.pageInfo.total > 0;
      const hasUrls = this.build.urls && this.build.urls.pageInfo.total > 0;
      return !hasFiles && !hasUrls;
    },
    notesDisabled() {
      return !this.build || !this.build.notes || this.build.notes.pageInfo.total === 0;
    },
    commandsDisabled() {
      return !this.build || !this.build.commands || this.build.commands.pageInfo.total === 0;
    },
    targetsDisabled() {
      return !this.build || !this.build.targets || this.build.targets.pageInfo.total === 0;
    },
    errorsBadges() {
      if (!this.build) {
        return [];
      }
      const badges = [];
      if (this.build.buildErrorsCount > 0) {
        badges.push({
          count: this.build.buildErrorsCount,
          icon: this.FA.faCircleExclamation,
          colorClass: 'tw-bg-error',
          textClass: 'tw-text-error-content',
        });
      }
      if (this.build.buildWarningsCount > 0) {
        badges.push({
          count: this.build.buildWarningsCount,
          icon: this.FA.faTriangleExclamation,
          colorClass: 'tw-bg-warning',
          textClass: 'tw-text-warning-content',
        });
      }
      return badges;
    },
    configureBadges() {
      if (!this.build) {
        return [];
      }
      const badges = [];
      if (this.build.configureErrorsCount > 0) {
        badges.push({
          count: this.build.configureErrorsCount,
          icon: this.FA.faCircleExclamation,
          colorClass: 'tw-bg-error',
          textClass: 'tw-text-error-content',
        });
      }
      if (this.build.configureWarningsCount > 0) {
        badges.push({
          count: this.build.configureWarningsCount,
          icon: this.FA.faTriangleExclamation,
          colorClass: 'tw-bg-warning',
          textClass: 'tw-text-warning-content',
        });
      }
      return badges;
    },
    testsBadges() {
      if (!this.build) {
        return [];
      }
      const badges = [];
      if (this.build.failedTestsCount > 0) {
        badges.push({
          count: this.build.failedTestsCount,
          icon: this.FA.faCircleExclamation,
          colorClass: 'tw-bg-error',
          textClass: 'tw-text-error-content',
        });
      }
      return badges;
    },
  },
};
</script>
