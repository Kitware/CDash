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
          v-if="projects.length === 0 && currentTab === 'ACTIVE'"
          class="tw-italic tw-font-medium tw-text-neutral-500"
          data-test="no-active-projects-message"
        >
          No projects with builds in the last 24 hours...
        </div>
        <div
          v-else-if="projects.length === 0 && currentTab === 'ALL'"
          class="tw-italic tw-font-medium tw-text-neutral-500"
          data-test="no-projects-message"
        >
          No projects to display...
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

export default {
  components: {FontAwesomeIcon, ProjectLogo, LoadingIndicator},
  props: {
    canCreateProjects: {
      type: Boolean,
      required: true,
    },
  },

  data() {
    return {
      currentTab: 'ACTIVE', // Options: 'ACTIVE', 'ALL'
    };
  },

  apollo: {
    allVisibleProjects: {
      query: gql`
        query allVisibleProjects($countBuildsSince: DateTimeTz!) {
          allVisibleProjects: projects {
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
        }
      `,
      variables() {
        return {
          countBuildsSince: DateTime.now().minus({days: 1}).startOf('second').toISO({suppressMilliseconds: true}),
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
      const edges = this.allVisibleProjects?.edges.filter(({node: project}) => this.currentTab === 'ALL' || project.buildCount > 0);
      if (edges === null || edges === undefined) {
        return null;
      }

      edges.sort((a, b) => b.node.buildCount - a.node.buildCount);
      return edges;
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
