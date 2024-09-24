<template>
  <loading-indicator :is-loading="!result">
    <h1
      v-if="result.projects.edges.length === 0"
      class="text-info text-center"
    >
      No Projects Found
    </h1>

    <div v-else-if="error">Unable to load projects!</div>

    <div v-else>
      <DataTable
        :columns="[
          {
            name: 'project',
            displayName: 'Project',
          },
          {
            name: 'description',
            displayName: 'Description',
            expand: true,
          },
          {
            name: 'last_submission',
            displayName: 'Last Submission',
          },
          {
            name: 'activity',
            displayName: 'Activity',
          }
        ]"
        :rows="formatProjectResults(result.projects.edges)"
        :full-width="true"
        class="projects-table"
        data-cy="all-projects"
      >
        <template #last_submission="{ props: { project } }" >
          <a
            v-if="project.mostRecentBuild"
            :href="$baseURL + '/index.php?project=' + project.name + '&date=' + DateTime.fromISO(project.mostRecentBuild.startTime).toISODate()"
          >
            {{ DateTime.fromISO(project.mostRecentBuild.startTime).toRelative() }}
          </a>
          <span v-else>never</span>
        </template>
        <template #activity="{ props: { activity_level } }">
          <img
            alt="Activity level"
            :src="$baseURL + '/img/cleardot.gif'"
            :class="'activity-level-' + activity_level"
          >
        </template>
      </DataTable>
      <div
        v-if="result.projects.edges.length > 0"
        style="float: right;"
      >
        <a
          v-if="show_all"
          :href="$baseURL + '/projects'"
        >Show Active Projects</a>
        <a
          v-else
          :href="$baseURL + '/projects/all'"
        >Show All Projects</a>
      </div>
    </div>
  </loading-indicator>
</template>

<script>

import LoadingIndicator from "./shared/LoadingIndicator.vue";
import DataTable from "./shared/DataTable.vue";
import gql from "graphql-tag";
import { useQuery } from "@vue/apollo-composable";
import { DateTime } from "luxon";

export default {
  name: 'AllProjects',
  computed: {
    DateTime() {
      return DateTime
    }
  },

  components: {
    DataTable,
    LoadingIndicator,
  },

  props: {
    show_all: {
      type: Boolean,
      default: false,
    },
  },

  setup() {
    const { result, loading, error, onResult, fetchMore } = useQuery(gql`
      query($after: String) {
        projects(after: $after) {
          edges {
            node {
              id
              name
              description
              mostRecentBuild {
                startTime
              }
              buildCount(filters: {
                gt: {
                  submissionTime: "${DateTime.now().minus({days: 7}).startOf('second').toISO({suppressMilliseconds: true})}"
                }
              })
            }
          }
          pageInfo {
            hasNextPage
            hasPreviousPage
            startCursor
            endCursor
          }
        }
      }
    `);

    onResult(async queryResult => {
      if (queryResult.data && queryResult.data.projects.pageInfo.hasNextPage) {
        fetchMore({
          variables: {
            after: queryResult.data.projects.pageInfo.endCursor,
          },
        });
      }
    })

    return {
      result,
      loading,
      error,
    };
  },

  methods: {
    formatProjectResults: function (project_edges) {
      return project_edges
        .filter((project) => this.show_all ? true : project.node.buildCount > 0)
        .map((project) => {
          const num_builds_in_last_week = project.node.buildCount;

          let activity_level;
          if (num_builds_in_last_week >= 70) {
            activity_level = 'high';
          } else if (num_builds_in_last_week >= 20) {
            activity_level = 'medium';
          } else if (num_builds_in_last_week > 0) {
            activity_level = 'low';
          } else {
            activity_level = 'none';
          }

          return {
            project: {
              value: project.node.id,
              text: project.node.name,
              href: `${this.$baseURL}/index.php?project=${project.node.name}`,
            },
            description: project.node.description,
            last_submission: {
              value: project.node.mostRecentBuild?.startTime ?? '',
              project: project.node,
            },
            activity: {
              value: num_builds_in_last_week,
              activity_level: activity_level,
            },
          };
        });
    }
  }
}
</script>
