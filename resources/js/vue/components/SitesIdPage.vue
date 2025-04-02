<template>
  <div class="tw-flex tw-flex-row tw-gap-4 tw-flex-wrap md:tw-flex-nowrap">
    <div class="tw-flex tw-flex-grow tw-flex-col tw-gap-2">
      <div
        class="tw-flex tw-flex-col tw-border tw-rounded tw-p-4"
        data-test="site-details"
      >
        <div class="tw-text-2xl tw-font-black">
          Site Details
        </div>
        <loading-indicator :is-loading="$apollo.queries.mostRecentInformation.loading">
          <template v-if="mostRecentInformation === null">
            No information available for this site.
          </template>
          <template v-else>
            <div class="tw-flex tw-flex-row tw-gap-4 tw-flex-wrap md:tw-flex-nowrap">
              <div>
                <div class="tw-text-lg tw-font-black">
                  System Information
                </div>
                <table
                  class="tw-table tw-w-auto tw-table-xs tw-text-left tw-text-nowrap"
                  data-test="site-details-table"
                >
                  <tbody>
                    <tr v-for="field in Object.keys(mostRecentInformation).filter(i => !['id', 'description', 'timestamp', '__typename'].includes(i))">
                      <th>{{ humanReadableSiteFieldName(field) }}</th>
                      <td
                        v-if="mostRecentInformation[field]"
                        data-test="site-details-table-cell"
                      >
                        {{ humanReadableSiteFieldValue(field, mostRecentInformation[field]) }}
                      </td>
                      <td
                        v-else
                        class="tw-italic"
                        data-test="site-details-table-cell"
                      >
                        Unknown
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div
                data-test="site-description"
                class="tw-grow"
              >
                <div class="tw-flex tw-flex-row">
                  <div class="tw-text-lg tw-font-black">
                    Description
                  </div>
                  <div
                    v-if="userId"
                    class="tw-ml-auto"
                  >
                    <template v-if="editingDescription">
                      <button
                        class="tw-btn tw-btn-outline tw-btn-sm"
                        data-test="cancel-edit-description-button"
                        @click="cancelEditDescription"
                      >
                        Cancel
                      </button>
                      <button
                        class="tw-ml-1 tw-btn tw-btn-success tw-btn-sm"
                        data-test="save-description-button"
                        @click="saveDescription"
                      >
                        Save
                      </button>
                    </template>
                    <button
                      v-else
                      class="tw-btn tw-btn-outline tw-btn-sm"
                      data-test="edit-description-button"
                      @click="editDescription"
                    >
                      <font-awesome-icon icon="fa-pencil" /> Edit
                    </button>
                  </div>
                </div>
                <div v-if="editingDescription">
                  <textarea
                    v-model="currentDescription"
                    class="tw-textarea tw-textarea-bordered tw-w-full tw-mt-1 tw-min-h-60"
                    placeholder="Description..."
                    data-test="edit-description-textarea"
                    maxlength="255"
                  />
                </div>
                <div v-else>
                  <div
                    v-if="mostRecentInformation.description"
                    class="tw-font-medium tw-text-neutral-500"
                  >
                    {{ mostRecentInformation.description }}
                  </div>
                  <div
                    v-else
                    class="tw-italic tw-font-medium tw-text-neutral-500"
                  >
                    No description provided...
                  </div>
                </div>
              </div>
            </div>
          </template>
        </loading-indicator>
      </div>
      <div class="tw-flex tw-flex-col tw-border tw-rounded tw-p-4">
        <div class="tw-text-2xl tw-font-black">
          Projects
        </div>
        <loading-indicator :is-loading="!projects">
          <table
            class="tw-table"
            data-test="site-projects-table"
          >
            <tbody>
              <tr v-for="project in projects.edges.filter(x => x.node.sites.edges.length > 0)">
                <th>
                  <a
                    :href="$baseURL + '/index.php?project=' + project.node.name"
                    class="tw-link tw-link-hover"
                  >
                    {{ project.node.name }}
                  </a>
                </th>
                <td class="tw-hidden sm:tw-block">
                  {{ project.node.description }}
                </td>
              </tr>
            </tbody>
          </table>
        </loading-indicator>
      </div>
      <div class="tw-flex tw-flex-col tw-border tw-rounded tw-p-4">
        <div class="tw-flex tw-flex-row">
          <div class="tw-text-2xl tw-font-black">
            Maintainers
          </div>
          <template v-if="userId && siteMaintainers">
            <button
              v-if="siteMaintainers.site.maintainers.edges.some(({ node }) => parseInt(node.id) === userId)"
              class="tw-ml-auto tw-btn tw-btn-outline tw-btn-sm"
              data-test="unclaim-site-button"
              @click="unclaimSite"
            >
              Unclaim Site
            </button>
            <button
              v-else
              class="tw-ml-auto tw-btn tw-btn-outline tw-btn-sm"
              data-test="claim-site-button"
              @click="claimSite"
            >
              Claim Site
            </button>
          </template>
        </div>
        <loading-indicator :is-loading="!siteMaintainers">
          <table
            class="tw-table"
            data-test="site-maintainers-table"
          >
            <tbody>
              <tr v-for="maintainer in siteMaintainers.site.maintainers.edges">
                <th>
                  {{ maintainer.node.firstname }} {{ maintainer.node.lastname }}
                  <template v-if="maintainer.node.institution">
                    ({{ maintainer.node.institution }})
                  </template>
                </th>
              </tr>
            </tbody>
          </table>
        </loading-indicator>
      </div>
    </div>
    <div
      class="tw-text-nowrap tw-flex-shrink-0 tw-max-w-80"
      data-test="site-history"
    >
      <div class="tw-text-2xl tw-font-black">
        History
      </div>
      <loading-indicator :is-loading="!site">
        <ul class="tw-timeline tw-timeline-snap-icon tw-timeline-compact tw-timeline-vertical">
          <li
            v-for="(information, index) in deduplicatedInformation"
            data-test="site-history-item"
          >
            <div class="tw-timeline-middle">
              <font-awesome-icon icon="circle-check" />
            </div>
            <div class="tw-timeline-end tw-mb-4">
              <time class="tw-font-mono tw-italic tw-text-neutral-500">{{ humanReadableTimestamp(information.node.timestamp) }}</time>
              <template v-if="index === deduplicatedInformation.length - 1">
                <div class="tw-text-lg tw-font-black">
                  Site Created
                </div>
                <table class="tw-table tw-table-xs tw-text-left">
                  <tbody>
                    <tr v-for="field in Object.keys(information.node).filter(i => !['id', 'description', 'timestamp', '__typename'].includes(i))">
                      <th>{{ humanReadableSiteFieldName(field) }}</th>
                      <td v-if="information.node[field]">
                        {{ humanReadableSiteFieldValue(field, information.node[field]) }}
                      </td>
                      <td
                        v-else
                        class="tw-italic"
                      >
                        Unknown
                      </td>
                    </tr>
                  </tbody>
                </table>
              </template>
              <template v-else-if="onlyDescriptionUpdated(information.node, deduplicatedInformation[index + 1].node)">
                <div class="tw-text-lg tw-font-black">
                  Description Changed
                </div>
                <div
                  v-if="information.node.description"
                  class="tw-font-medium tw-text-neutral-500 tw-text-wrap"
                  data-test="new-description"
                >
                  {{ information.node.description }}
                </div>
                <div
                  v-else
                  class="tw-italic tw-font-medium tw-text-neutral-500 tw-text-wrap"
                >
                  No description provided...
                </div>
                <div
                  v-if="deduplicatedInformation[index + 1].node.description"
                  class="tw-line-through tw-font-medium tw-text-neutral-500 tw-text-wrap"
                  data-test="old-description"
                >
                  {{ deduplicatedInformation[index + 1].node.description }}
                </div>
              </template>
              <template v-else>
                <div class="tw-text-lg tw-font-black">
                  System Update
                </div>
                <table class="tw-table tw-w-auto tw-table-xs tw-text-left">
                  <tbody>
                    <tr v-for="field in Object.keys(information.node).filter(i => !['id', 'description', 'timestamp', '__typename'].includes(i))">
                      <th>{{ humanReadableSiteFieldName(field) }}</th>
                      <td v-if="information.node[field]">
                        {{ humanReadableSiteFieldValue(field, information.node[field]) }}
                      </td>
                      <td
                        v-else
                        class="tw-italic"
                      >
                        Unknown
                      </td>
                    </tr>
                  </tbody>
                </table>
              </template>
            </div>
            <hr>
          </li>
        </ul>
      </loading-indicator>
    </div>
  </div>
</template>

<script>

import gql from 'graphql-tag';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import { DateTime } from 'luxon';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

const SITE_MAINTAINERS_QUERY = gql`
  query($siteid: ID) {
    site(id: $siteid) {
      id
      maintainers {
        edges {
          node {
            id
            firstname
            lastname
            institution
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
`;

const SITE_INFORMATION_QUERY = gql`
  query($siteid: ID, $after: String) {
    site(id: $siteid) {
      id
      information(after: $after, first: 100) {
        edges {
          node {
            id
            timestamp
            description
            processorVendor
            processorVendorId
            processorFamilyId
            processorModelId
            processorModelName
            processorCacheSize
            numberLogicalCpus
            numberPhysicalCpus
            totalVirtualMemory
            totalPhysicalMemory
            processorClockFrequency
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
`;

export default {
  components: {
    LoadingIndicator,
    FontAwesomeIcon,
  },

  props: {
    siteId: {
      type: Number,
      required: true,
    },

    userId: {
      type: [Number, null],
      required: true,
    },
  },

  data() {
    return {
      editingDescription: false,
      currentDescription: '',
    };
  },

  apollo: {
    site: {
      query: SITE_INFORMATION_QUERY,
      variables() {
        return {
          siteid: this.siteId,
        };
      },
      result({data}) {
        if (data && data.site.information.pageInfo.hasNextPage) {
          this.$apollo.queries.site.fetchMore({
            variables: {
              after: data.site.information.pageInfo.endCursor,
            },
          });
        }
      },
    },

    mostRecentInformation: {
      query: gql`
        query($siteid: ID) {
          site(id: $siteid) {
            id
            mostRecentInformation {
              id
              description
              processorVendor
              processorVendorId
              processorFamilyId
              processorModelId
              processorModelName
              processorCacheSize
              numberLogicalCpus
              numberPhysicalCpus
              totalVirtualMemory
              totalPhysicalMemory
              processorClockFrequency
            }
          }
        }
      `,
      update: data => data?.site?.mostRecentInformation,
      variables() {
        return {
          siteid: this.siteId,
        };
      },
    },

    siteMaintainers: {
      query: SITE_MAINTAINERS_QUERY,
      update: data => data,
      variables() {
        return {
          siteid: this.siteId,
        };
      },
      result({data}) {
        if (data && data.site.maintainers.pageInfo.hasNextPage) {
          this.$apollo.queries.siteMaintainers.site.maintainers.fetchMore({
            variables: {
              after: data.site.maintainers.pageInfo.endCursor,
            },
          });
        }
      },
    },

    projects: {
      query: gql`
        query($siteid: ID) {
          projects {
            edges {
              node {
                id
                name
                description
                sites(filters: {
                  eq: {
                    id: $siteid
                  }
                }) {
                  edges {
                    node {
                      id
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
      `,
      variables() {
        return {
          siteid: this.siteId,
        };
      },
      result({data}) {
        if (data && data.projects.pageInfo.hasNextPage) {
          this.$apollo.queries.projects.fetchMore({
            variables: {
              after: data.projects.pageInfo.endCursor,
            },
          });
        }
      },
    },
  },

  computed: {
    deduplicatedInformation() {
      return this.site?.information?.edges.filter((node, index, edges) => index === edges.length - 1 || !this.informationIsIdentical(node.node, edges[index + 1].node));
    },
  },

  methods: {
    humanReadableTimestamp(timestamp) {
      return DateTime.fromISO(timestamp).toLocaleString(DateTime.DATETIME_SHORT_WITH_SECONDS);
    },

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

    humanReadableClockFrequency(inputInMhz) {
      if (inputInMhz > 1000) {
        return `${inputInMhz / 1000} GHz`;
      }
      else {
        return `${inputInMhz} MHz`;
      }
    },

    informationIsIdentical(information1, information2) {
      const information1_copy = JSON.parse(JSON.stringify(information1));
      const information2_copy = JSON.parse(JSON.stringify(information2));
      information1_copy.timestamp = '';
      information2_copy.timestamp = '';
      information1_copy.id = '';
      information2_copy.id = '';

      return JSON.stringify(information1_copy) === JSON.stringify(information2_copy);
    },

    onlyDescriptionUpdated(information1, information2) {
      const information1_copy = JSON.parse(JSON.stringify(information1));
      const information2_copy = JSON.parse(JSON.stringify(information2));
      information1_copy.description = null;
      information2_copy.description = null;

      return this.informationIsIdentical(information1_copy, information2_copy) && information1.description !== information2.description;
    },

    humanReadableSiteFieldName(name) {
      switch (name) {
      case 'processorVendor':
        return 'Processor Vendor';
      case 'processorVendorId':
        return 'Processor Vendor ID';
      case 'processorFamilyId':
        return 'Processor Family ID';
      case 'processorModelId':
        return 'Processor Model ID';
      case 'processorModelName':
        return 'Processor Model Name';
      case 'processorCacheSize':
        return 'Processor Cache Size';
      case 'numberLogicalCpus':
        return 'Logical CPUs';
      case 'numberPhysicalCpus':
        return 'Physical CPUs';
      case 'totalVirtualMemory':
        return 'Virtual Memory';
      case 'totalPhysicalMemory':
        return 'Physical Memory';
      case 'processorClockFrequency':
        return 'Clock Frequency';
      default:
        return name;
      }
    },

    humanReadableSiteFieldValue(field, value) {
      switch (field) {
      case 'totalVirtualMemory':
        return this.humanReadableMemory(value);
      case 'totalPhysicalMemory':
        return this.humanReadableMemory(value);
      case 'processorClockFrequency':
        return this.humanReadableClockFrequency(value);
      default:
        return value;
      }
    },

    claimSite() {
      this.$apollo.mutate({
        mutation: gql`mutation ($siteid: ID!) {
          claimSite(input: {siteId: $siteid}) {
            user {
              id
              firstname
              lastname
              institution
            }
          }
        }`,
        variables: {
          siteid: this.siteId,
        },
        update: (cache, { data: { claimSite } }) => {
          const data = JSON.parse(JSON.stringify(cache.readQuery({
            query: SITE_MAINTAINERS_QUERY,
            variables: {
              siteid: this.siteId,
            },
          })));
          data.site.maintainers.edges.push({
            node: {
              ...claimSite.user,
            },
          });
          cache.writeQuery({ query: SITE_MAINTAINERS_QUERY, data: data });
        },
        optimisticResponse: {
          __typename: 'Mutation',
          claimSite: {
            __typename: 'ClaimSiteMutationPayload',
            user: {
              __typename: 'User',
              id: this.userId,
              institution: '',
              firstname: '',
              lastname: '',
            },
          },
        },
      }).catch((error) => {
        console.error(error);
      });
    },

    unclaimSite() {
      this.$apollo.mutate({
        mutation: gql`mutation ($siteid: ID!) {
          unclaimSite(input: {siteId: $siteid}) {
            message
          }
        }`,
        variables: {
          siteid: this.siteId,
        },
        update: (cache) => {
          const data = JSON.parse(JSON.stringify(cache.readQuery({
            query: SITE_MAINTAINERS_QUERY,
            variables: {
              siteid: this.siteId,
            },
          })));
          data.site.maintainers.edges = data.site.maintainers.edges.filter(({ node }) => parseInt(node.id) !== this.userId);
          cache.writeQuery({ query: SITE_MAINTAINERS_QUERY, data: data });
        },
        optimisticResponse: {
          __typename: 'Mutation',
          unclaimSite: {
            __typename: 'UnclaimSiteMutationPayload',
            message: '',
          },
        },
      }).catch((error) => {
        console.error(error);
      });
    },

    editDescription() {
      this.editingDescription = true;
      this.currentDescription = this.mostRecentInformation?.description;
    },

    cancelEditDescription() {
      this.editingDescription = false;
    },

    saveDescription() {
      this.editingDescription = false;

      this.$apollo.mutate({
        mutation: gql`mutation ($siteid: ID!, $description: String!) {
          updateSiteDescription(input: {
            siteId: $siteid
            description: $description
          }) {
            site {
              id
              mostRecentInformation {
                id
                timestamp
                description
                processorVendor
                processorVendorId
                processorFamilyId
                processorModelId
                processorModelName
                processorCacheSize
                numberLogicalCpus
                numberPhysicalCpus
                totalVirtualMemory
                totalPhysicalMemory
                processorClockFrequency
              }
            }
          }
        }`,
        variables: {
          siteid: this.siteId,
          description: this.currentDescription,
        },
        update: (cache, { data: { updateSiteDescription } }) => {
          const data = JSON.parse(JSON.stringify(cache.readQuery({
            query: SITE_INFORMATION_QUERY,
            variables: {
              siteid: this.siteId,
            },
          })));
          data.site.information.edges.unshift({
            node: {
              ...updateSiteDescription.site.mostRecentInformation,
            },
          });
          cache.writeQuery({ query: SITE_INFORMATION_QUERY, data: data });
        },
        optimisticResponse: {
          __typename: 'Mutation',
          updateSiteDescription: {
            __typename: 'UnclaimSiteMutationPayload',
            site: {
              __typename: 'Site',
              id: this.siteId,
              mostRecentInformation: {
                __typename: 'SiteInformation',
                id: -1,
                timestamp: DateTime.now().toISODate(),
                description: this.currentDescription,
                processorVendor: this.mostRecentInformation.processorVendor,
                processorVendorId:  this.mostRecentInformation.processorVendorId,
                processorFamilyId:  this.mostRecentInformation.processorFamilyId,
                processorModelId:  this.mostRecentInformation.processorModelId,
                processorModelName:  this.mostRecentInformation.processorModelName,
                processorCacheSize:  this.mostRecentInformation.processorCacheSize,
                numberLogicalCpus:  this.mostRecentInformation.numberLogicalCpus,
                numberPhysicalCpus:  this.mostRecentInformation.numberPhysicalCpus,
                totalVirtualMemory:  this.mostRecentInformation.totalVirtualMemory,
                totalPhysicalMemory:  this.mostRecentInformation.totalPhysicalMemory,
                processorClockFrequency:  this.mostRecentInformation.processorClockFrequency,
              },
            },
          },
        },
      }).catch((error) => {
        console.error(error);
      });
    },
  },
};
</script>
