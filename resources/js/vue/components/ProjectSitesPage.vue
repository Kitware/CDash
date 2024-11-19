<template>
  <div data-test="all-sites-table">
    <loading-indicator :is-loading="!allSites">
      <data-table
        :column-groups="[
          {
            displayName: 'All Sites',
            width: 100,
          }
        ]"
        :columns="[
          {
            name: 'name',
            displayName: 'Name',
          },
          {
            name: 'processors',
            displayName: 'Processors',
          },
          {
            name: 'memory',
            displayName: 'Memory',
          },
          {
            name: 'maintainers',
            displayName: 'Maintainers',
          },
          {
            name: 'description',
            displayName: 'Description',
            expand: true,
          },
        ]"
        :rows="formattedSiteRows"
        :full-width="true"
      />
    </loading-indicator>
  </div>
</template>

<script>

import DataTable from './shared/DataTable.vue';
import gql from 'graphql-tag';
import FilterBuilder from './shared/FilterBuilder.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';

export default {
  components: {
    LoadingIndicator,
    DataTable,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },
  },

  apollo: {
    allSites: {
      query: gql`
        query($projectid: ID, $after: String) {
          allSites: project(id: $projectid) {
            sites(after: $after, first: 100) {
              edges {
                node {
                  id
                  name
                  mostRecentInformation {
                    description
                    numberPhysicalCpus
                    totalPhysicalMemory
                  }
                  maintainers {
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
              pageInfo {
                hasNextPage
                hasPreviousPage
                startCursor
                endCursor
              }
            }
          }
        }
      `,
      variables() {
        return {
          projectid: this.projectId,
        };
      },
      result({data}) {
        if (data && data.allSites.sites.pageInfo.hasNextPage) {
          this.$apollo.queries.allSites.fetchMore({
            variables: {
              after: data.allSites.sites.pageInfo.endCursor,
            },
          });
        }
      },
    },
  },

  computed: {
    formattedSiteRows() {
      return this.allSites.sites.edges?.map(edge => {
        return {
          name: {
            value: edge.node.name,
            text: edge.node.name,
            href: `${this.$baseURL}/sites/${edge.node.id}`,
          },
          processors: {
            value: edge.node.mostRecentInformation?.numberPhysicalCpus,
            text: edge.node.mostRecentInformation?.numberPhysicalCpus,
          },
          memory: {
            value: edge.node.mostRecentInformation?.totalPhysicalMemory,
            text: this.humanReadableMemory(edge.node.mostRecentInformation?.totalPhysicalMemory),
          },
          maintainers: edge.node.maintainers.edges.map(maintainer => {
            let maintainerName = '';
            if (maintainer.node.firstname) {
              maintainerName += maintainer.node.firstname;
            }
            if (maintainer.node.firstname && maintainer.node.lastname) {
              maintainerName += ' ';
            }
            if (maintainer.node.lastname) {
              maintainerName += maintainer.node.lastname;
            }
            return maintainerName;
          }).join(', '),
          description: {
            value: edge.node.mostRecentInformation?.description,
            text: edge.node.description,
          },
        };
      });
    },
  },

  methods: {
    humanReadableMemory(inputInMiB) {
      if (!inputInMiB) {
        return '';
      }

      if (inputInMiB < 1024) {
        return `${inputInMiB} MiB`;
      }
      else if (inputInMiB < 1024 * 1024) {
        return `${(inputInMiB / 1024).toFixed(2)} GiB`;
      }
      else {
        return `${(inputInMiB / (1024 * 1024)).toFixed(2)} TiB`;
      }
    },
  },
};
</script>
