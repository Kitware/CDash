<template>
  <div
    class="tw-flex tw-flex-row tw-gap-4"
    data-test="projects-page"
  >
    <div class="tw-flex tw-flex-col tw-gap-4 tw-w-full">
      <div class="tw-flex tw-flex-row">
        <div
          role="tablist"
          class="tw-tabs tw-tabs-bordered"
        >
          <a
            v-if="isLoggedIn"
            role="tab"
            class="tw-tab"
            :class="{'tw-tab-active': currentTab === 'MEMBER', 'tw-font-bold': currentTab === 'MEMBER' }"
            data-test="member-tab"
            @click="currentTab = 'MEMBER'"
          >
            Member
          </a>
          <a
            role="tab"
            class="tw-tab"
            :class="{'tw-tab-active': currentTab === 'ACTIVE', 'tw-font-bold': currentTab === 'ACTIVE' }"
            data-test="active-tab"
            @click="currentTab = 'ACTIVE'"
          >
            Active
          </a>
          <a
            role="tab"
            class="tw-tab"
            :class="{'tw-tab-active': currentTab === 'ALL', 'tw-font-bold': currentTab === 'ALL' }"
            data-test="all-tab"
            @click="currentTab = 'ALL'"
          >
            All
          </a>
        </div>
        <span class="tw-flex-grow" />
        <a
          v-if="canCreateProjects"
          role="button"
          class="tw-btn"
          :href="$baseURL + '/projects/new'"
          data-test="create-project-button"
        >
          Create Project
        </a>
      </div>
      <loading-indicator :is-loading="projects === null">
        <div
          v-if="noProjectsMessage !== null"
          class="tw-italic tw-font-medium tw-text-neutral-500"
          data-test="no-projects-message"
        >
          {{ noProjectsMessage }}
        </div>
        <table
          v-else
          class="tw-table tw-w-full"
          data-test="projects-table"
        >
          <tbody>
            <tr
              v-for="{ node: project } in projects"
              data-test="projects-table-row"
            >
              <td>
                <project-logo
                  :image-url="project.logoUrl"
                  :project-name="project.name"
                  class="tw-w-8 tw-h-8"
                />
              </td>
              <td class="tw-align-middle tw-w-full">
                <div class="tw-flex tw-flex-col tw-justify-center tw-h-full">
                  <a
                    class="tw-link tw-link-hover tw-font-bold"
                    :href="$baseURL + '/index.php?project=' + project.name"
                    data-test="project-name"
                  >
                    {{ project.name }}
                    <font-awesome-icon
                      :icon="projectVisibilityToIcon(project.visibility)"
                      class="tw-text-neutral-500"
                    />
                  </a>
                  <div
                    v-if="project.description"
                    class="tw-text-neutral-500"
                  >
                    {{ project.description }}
                  </div>
                </div>
              </td>
              <td>
                <span
                  v-if="project.mostRecentBuild"
                  class="tw-text-nowrap tw-text-neutral-500"
                >
                  <font-awesome-icon :icon="FA.faCalendar" /> {{ DateTime.fromISO(project.mostRecentBuild?.submissionTime).toRelative() }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </loading-indicator>
    </div>
  </div>
</template>

<script>

import LoadingIndicator from './shared/LoadingIndicator.vue';
import gql from 'graphql-tag';
import {DateTime} from 'luxon';
import ProjectLogo from './shared/ProjectLogo.vue';
import {
  faCalendar,
  faEarthAmericas,
  faShieldHalved,
  faLock,
} from '@fortawesome/free-solid-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';

const PROJECT_LIST_QUERY = `
  projects {
    edges {
      node {
        id
        name
        description
        logoUrl
        visibility
        buildCount(filters: {
          gt: {
            submissionTime: $countBuildsSince
          }
        })
        mostRecentBuild {
          id
          startTime
          submissionTime
        }
      }
    }
  }
`;

export default {
  components: {FontAwesomeIcon, ProjectLogo, LoadingIndicator},

  props: {
    isLoggedIn: {
      type: Boolean,
      required: true,
    },

    canCreateProjects: {
      type: Boolean,
      required: true,
    },
  },

  data() {
    return {
      currentTab: this.isLoggedIn ? 'MEMBER' : 'ACTIVE', // Options: 'MEMBER', 'ACTIVE', 'ALL'
    };
  },

  apollo: {
    allVisibleProjects: {
      query: gql`
        query allVisibleProjects($countBuildsSince: DateTimeTz!) {
          allVisibleProjects: ${PROJECT_LIST_QUERY}
        }
      `,
      variables() {
        return {
          countBuildsSince: this.oneDayAgo,
        };
      },
    },

    myProjects: {
      query: gql`
        query myProjects($countBuildsSince: DateTimeTz!) {
          me {
            id
            ${PROJECT_LIST_QUERY}
          }
        }
      `,
      update: data => data?.me?.projects,
      variables() {
        return {
          countBuildsSince: this.oneDayAgo,
        };
      },
    },
  },

  computed: {
    DateTime() {
      return DateTime;
    },

    FA() {
      return {
        faCalendar,
        faEarthAmericas,
        faShieldHalved,
        faLock,
      };
    },

    projects() {
      let edges;
      if (this.currentTab === 'MEMBER') {
        edges = this.myProjects?.edges.map(x => x);
      }
      else if (this.currentTab === 'ACTIVE') {
        edges = this.allVisibleProjects?.edges.filter(({node: project}) => project.buildCount > 0);
      }
      else {
        edges = this.allVisibleProjects?.edges.map(x => x);
      }

      if (edges === null || edges === undefined) {
        return null;
      }

      edges.sort((a, b) => b.node.buildCount - a.node.buildCount);
      return edges;
    },

    noProjectsMessage() {
      if (this.projects.length > 0) {
        return null;
      }

      switch (this.currentTab) {
      case 'MEMBER':
        return 'You are not a member of any projects yet...';
      case 'ACTIVE':
        return 'No projects with builds in the last 24 hours...';
      case 'ALL':
        return 'No projects to display...';
      default:
        return null;
      }
    },

    oneDayAgo() {
      return DateTime.now().minus({days: 1}).startOf('second').toISO({suppressMilliseconds: true});
    },
  },

  methods: {
    projectVisibilityToIcon(visibility) {
      switch (visibility) {
      case 'PUBLIC':
        return this.FA.faEarthAmericas;
      case 'PROTECTED':
        return this.FA.faShieldHalved;
      case 'PRIVATE':
        return this.FA.faLock;
      default:
        throw `Invalid visibility ${visibility}`;
      }
    },
  },
};
</script>
