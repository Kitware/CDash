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
        :dashboard-date="dashboardDate"
        :can-update="canUpdate"
      />

      <div class="tw-flex tw-flex-col lg:tw-flex-row tw-gap-8 tw-items-start">
        <div class="tw-flex tw-flex-col tw-gap-6 tw-flex-grow tw-min-w-0 tw-w-full">
          <RecentActivityCard
            :builds-last-day="project.buildsLastDay"
            :builds-last-week="project.buildsLastWeek"
            :last-submission-time="project.mostRecentBuild?.submissionTime ?? null"
            :testing-day-start="testingDayStart"
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
import LoadingIndicator from './shared/LoadingIndicator.vue';
import AdministratorsSection from './ProjectHomePage/AdministratorsSection.vue';
import ProjectHeader from './ProjectHomePage/ProjectHeader.vue';
import RecentActivityCard from './ProjectHomePage/RecentActivityCard.vue';
import ResourcesSection from './ProjectHomePage/ResourcesSection.vue';

export default {
  name: 'ProjectHomePage',

  components: {
    AdministratorsSection,
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

    canUpdate: {
      type: Boolean,
      required: true,
    },
  },

  apollo: {
    project: {
      query: gql`
        query($id: ID, $oneDayAgo: DateTimeTz!, $oneWeekAgo: DateTimeTz!) {
          project(id: $id) {
            id
            name
            description
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
        const now = DateTime.now().startOf('second');
        return {
          id: this.projectId,
          oneDayAgo: now.minus({ days: 1 }).toISO({ suppressMilliseconds: true }),
          oneWeekAgo: now.minus({ days: 7 }).toISO({ suppressMilliseconds: true }),
        };
      },
    },
  },

  computed: {
    administrators() {
      return this.project.administrators.edges.map(({ node }) => node);
    },
  },
};
</script>
