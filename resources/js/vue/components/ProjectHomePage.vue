<template>
  <LoadingIndicator :is-loading="!project">
    <div
      class="tw-flex tw-flex-col tw-w-full tw-max-w-6xl tw-mx-auto tw-gap-6 tw-p-4"
      data-test="project-home-page"
    >
      <ProjectHeader
        :project-id="projectId"
        :project-name="project.name"
        :description="project.description"
        :logo-url="project.logoUrl"
        :visibility="project.visibility"
        :dashboard-url="dashboardUrl"
        :can-update="canUpdate"
      />

      <div class="tw-flex tw-flex-col lg:tw-flex-row tw-gap-8 tw-items-start">
        <div class="tw-flex tw-flex-col tw-gap-6 tw-flex-grow tw-min-w-0 tw-w-full">
          <RecentActivityCard
            :builds-last-day="project.buildsLastDay"
            :builds-last-week="project.buildsLastWeek"
            :last-submission-time="project.mostRecentBuild?.submissionTime ?? null"
            :histogram="histogram"
            :histogram-load-failed="buildHistogramsLoadFailed"
            :testing-day-start="testingDayStart"
            :dashboard-url="dashboardUrl"
          />
          <BuildGroupsSection
            :groups="buildGroupCards"
            :load-failed="buildGroupsLoadFailed"
            :histograms-load-failed="buildGroupHistogramsLoadFailed"
          />
        </div>

        <aside class="tw-flex tw-flex-col tw-w-full lg:tw-w-72 tw-shrink-0 tw-divide-y tw-divide-base-300">
          <ResourcesSection
            :project-id="projectId"
            :can-update="canUpdate"
            :vcs-url="project.vcsUrl"
            :vcs-viewer="project.vcsViewer"
            :home-url="project.homeUrl"
            :documentation-url="project.documentationUrl"
            :bug-tracker-url="project.bugTrackerUrl"
            :bug-tracker-new-issue-url="project.bugTrackerNewIssueUrl"
            :security-policy-url="project.securityPolicyUrl"
            :test-data-url="project.testDataUrl"
          />
          <ContactSection
            v-if="project.contactInfo || canUpdate"
            :project-id="projectId"
            :can-update="canUpdate"
            :contact-info="project.contactInfo"
          />
          <AdministratorsSection
            v-if="administrators.length > 0"
            :administrators="administrators"
          />
        </aside>
      </div>
    </div>
  </LoadingIndicator>
</template>

<script>
import gql from 'graphql-tag';
import { DateTime } from 'luxon';
import { markRaw } from 'vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import AdministratorsSection from './ProjectHomePage/AdministratorsSection.vue';
import BuildGroupsSection from './ProjectHomePage/BuildGroupsSection.vue';
import BuildHistogram from './ProjectHomePage/BuildHistogram';
import ContactSection from './ProjectHomePage/ContactSection.vue';
import ProjectHeader from './ProjectHomePage/ProjectHeader.vue';
import RecentActivityCard from './ProjectHomePage/RecentActivityCard.vue';
import ResourcesSection from './ProjectHomePage/ResourcesSection.vue';

export default {
  name: 'ProjectHomePage',

  components: {
    AdministratorsSection,
    BuildGroupsSection,
    ContactSection,
    LoadingIndicator,
    ProjectHeader,
    RecentActivityCard,
    ResourcesSection,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },

    // The most recent testing day with builds, or null if the project has no builds.
    dashboardDate: {
      type: [String, null],
      required: true,
    },

    testingDayStart: {
      type: [String, null],
      required: true,
    },

    // The time of day when testing days start, e.g. "21:00:00".
    nightlyTime: {
      type: String,
      required: true,
    },

    // The time zone of the nightly time, e.g. "America/New_York".
    nightlyTimezone: {
      type: String,
      required: true,
    },

    canUpdate: {
      type: Boolean,
      required: true,
    },
  },

  data() {
    return {
      // The window doesn't change, so it doesn't need to be reactive.
      histogramWindowStart: markRaw(BuildHistogram.windowStart(this.nightlyTime, this.nightlyTimezone)),
      buildHistogramsLoadFailed: false,
      buildGroupsLoadFailed: false,
      buildGroupHistogramsLoadFailed: false,
    };
  },

  apollo: {
    project: {
      query: gql`
        query($id: ID, $oneDayAgo: DateTimeTz!, $oneWeekAgo: DateTimeTz!) {
          project(id: $id) {
            id
            name
            description
            contactInfo
            logoUrl
            visibility
            homeUrl
            documentationUrl
            vcsUrl
            vcsViewer
            bugTrackerUrl
            bugTrackerNewIssueUrl
            securityPolicyUrl
            testDataUrl
            buildsLastDay: buildCount(filters: {
              gt: {
                submissionTime: $oneDayAgo
              }
            })
            buildsLastWeek: buildCount(filters: {
              gt: {
                submissionTime: $oneWeekAgo
              }
            })
            mostRecentBuild {
              id
              submissionTime
            }
            administrators {
              edges {
                node {
                  id
                  firstname
                  lastname
                }
              }
            }
          }
        }
      `,
      variables() {
        return {
          id: this.projectId,
          ...this.recentSubmissionVariables(),
        };
      },
    },

    // Loaded separately so the rest of the page doesn't wait for the histograms.
    buildHistograms: {
      query: gql`
        query(
          $id: ID
          $timezone: String!
          $dayStart: Time!
          $histogramFilters: ProjectBuildCountHistogramFiltersMultiFilterInput
          $failedHistogramFilters: ProjectBuildCountHistogramFiltersMultiFilterInput
        ) {
          project(id: $id) {
            id
            buildHistogram: buildCountHistogram(
              filters: $histogramFilters
              interval: DAY
              by: START_TIME
              timezone: $timezone
              dayStart: $dayStart
            ) {
              start
              count
            }
            failedBuildHistogram: buildCountHistogram(
              filters: $failedHistogramFilters
              interval: DAY
              by: START_TIME
              timezone: $timezone
              dayStart: $dayStart
            ) {
              start
              count
            }
          }
        }
      `,
      variables() {
        return {
          id: this.projectId,
          ...BuildHistogram.queryVariables(this.nightlyTime, this.nightlyTimezone, this.histogramWindowStart),
        };
      },
      update: (data) => data.project,
      error() {
        this.buildHistogramsLoadFailed = true;
      },
    },

    // The build group queries only need the project ID, so they start alongside the project query.
    buildGroups: {
      query: gql`
        query($id: ID, $oneDayAgo: DateTimeTz!, $oneWeekAgo: DateTimeTz!, $windowStart: DateTimeTz!) {
          project(id: $id) {
            id
            buildGroups(filters: {
              eq: {
                endTime: null
              }
            }) {
              edges {
                node {
                  id
                  name
                  description
                  buildsLastDay: buildCount(filters: {
                    gt: {
                      submissionTime: $oneDayAgo
                    }
                  })
                  buildsLastWeek: buildCount(filters: {
                    gt: {
                      submissionTime: $oneWeekAgo
                    }
                  })
                  # Only the histogram window is searched.  Without a time limit, finding a group's most
                  # recent build reads every build in the group.
                  mostRecentBuilds: builds(
                    first: 1
                    filters: {
                      ge: {
                        submissionTime: $windowStart
                      }
                    }
                    orderBy: [{ column: SUBMISSION_TIME, order: DESC }]
                  ) {
                    edges {
                      node {
                        id
                        submissionTime
                      }
                    }
                  }
                }
              }
            }
          }
        }
      `,
      variables() {
        return {
          id: this.projectId,
          ...this.recentSubmissionVariables(),
          windowStart: this.histogramWindowStart.toISO({ suppressMilliseconds: true }),
        };
      },
      update: (data) => data.project?.buildGroups.edges.map(({ node }) => node),
      error() {
        this.buildGroupsLoadFailed = true;
      },
    },

    // Loaded separately so the build group cards don't wait for the histograms.
    buildGroupHistograms: {
      query: gql`
        query(
          $id: ID
          $timezone: String!
          $dayStart: Time!
          $histogramFilters: BuildGroupBuildCountHistogramFiltersMultiFilterInput
          $failedHistogramFilters: BuildGroupBuildCountHistogramFiltersMultiFilterInput
        ) {
          project(id: $id) {
            id
            buildGroups(filters: {
              eq: {
                endTime: null
              }
            }) {
              edges {
                node {
                  id
                  buildHistogram: buildCountHistogram(
                    filters: $histogramFilters
                    interval: DAY
                    by: START_TIME
                    timezone: $timezone
                    dayStart: $dayStart
                  ) {
                    start
                    count
                  }
                  failedBuildHistogram: buildCountHistogram(
                    filters: $failedHistogramFilters
                    interval: DAY
                    by: START_TIME
                    timezone: $timezone
                    dayStart: $dayStart
                  ) {
                    start
                    count
                  }
                }
              }
            }
          }
        }
      `,
      variables() {
        return {
          id: this.projectId,
          ...BuildHistogram.queryVariables(this.nightlyTime, this.nightlyTimezone, this.histogramWindowStart),
        };
      },
      update: (data) => data.project && new Map(data.project.buildGroups.edges.map(({ node }) => [node.id, node])),
      error() {
        this.buildGroupHistogramsLoadFailed = true;
      },
    },
  },

  computed: {
    dashboardUrl() {
      return this.dashboardPageUrl('index.php');
    },

    histogram() {
      return this.buildHistograms ? this.histogramDays(this.buildHistograms, 'index.php') : null;
    },

    buildGroupCards() {
      if (!this.buildGroups) {
        return null;
      }
      return this.buildGroups.map((group) => {
        const groupParameter = `&buildgroup=${encodeURIComponent(group.name)}`;
        return {
          ...group,
          lastSubmissionTime: group.mostRecentBuilds.edges[0]?.node.submissionTime ?? null,
          // A group which became active after the histograms were loaded isn't in them.
          histogram: this.buildGroupHistograms
            ? this.histogramDays(this.buildGroupHistograms.get(group.id), 'viewBuildGroup.php', groupParameter)
            : null,
          url: this.dashboardPageUrl('viewBuildGroup.php', groupParameter),
        };
      });
    },

    administrators() {
      return this.project.administrators.edges.map(({ node }) => node);
    },
  },

  methods: {
    // Variables for counting the builds submitted in the last day and week.
    recentSubmissionVariables() {
      const now = DateTime.now().startOf('second');
      return {
        oneDayAgo: now.minus({ days: 1 }).toISO({ suppressMilliseconds: true }),
        oneWeekAgo: now.minus({ days: 7 }).toISO({ suppressMilliseconds: true }),
      };
    },

    /**
     * Passing and failing builds for each testing day in the histogram window, and the dashboard page each
     * day links to.
     *
     * @param {{buildHistogram: Array, failedBuildHistogram: Array}|undefined} histograms
     * @param {string} page
     * @param {string} parameters Additional encoded query parameters, each starting with "&".
     */
    histogramDays(histograms, page, parameters = '') {
      const days = BuildHistogram.dailyCounts(
        this.nightlyTime,
        this.nightlyTimezone,
        this.histogramWindowStart,
        histograms?.buildHistogram ?? [],
        histograms?.failedBuildHistogram ?? [],
      );
      return days.map((day) => ({ ...day, url: this.dashboardPageUrl(page, parameters, day.date.toISODate()) }));
    },

    /**
     * A link to a dashboard page for a testing day.
     *
     * @param {string} page
     * @param {string} parameters Additional encoded query parameters, each starting with "&".
     * @param {string|null} date The testing day, or null for the current one.  Defaults to the most recent
     *                           testing day with builds.
     */
    dashboardPageUrl(page, parameters = '', date = this.dashboardDate) {
      let url = `${this.$baseURL}/${page}?project=${encodeURIComponent(this.project.name)}${parameters}`;
      if (date !== null) {
        url += `&date=${date}`;
      }
      return url;
    },
  },
};
</script>
