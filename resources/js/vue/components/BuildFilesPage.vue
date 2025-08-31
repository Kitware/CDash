<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <BuildSummaryCard :build-id="buildId" />

    <div
      v-if="urls && files && urls.edges.length === 0 && files.edges.length === 0"
      data-test="no-urls-or-files-message"
    >
      No URLs or files were uploaded for this build.
    </div>

    <loading-indicator :is-loading="!urls">
      <data-table
        v-if="urls.edges.length > 0"
        :column-groups="[
          {
            displayName: 'URLs',
            width: 100,
          }
        ]"
        :columns="[
          {
            name: 'url',
            displayName: 'URL',
          },
        ]"
        :rows="formattedUrlRows"
        :full-width="true"
        test-id="urls-table"
      />
    </loading-indicator>

    <loading-indicator :is-loading="!files">
      <data-table
        v-if="files.edges.length > 0"
        :column-groups="[
          {
            displayName: 'Files',
            width: 100,
          }
        ]"
        :columns="[
          {
            name: 'name',
            displayName: 'Name',
          },
          {
            name: 'size',
            displayName: 'Size',
          },
          {
            name: 'hash',
            displayName: 'SHA-1',
          },
        ]"
        :rows="formattedFileRows"
        :full-width="true"
        test-id="files-table"
      />
    </loading-indicator>
  </div>
</template>

<script>
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import DataTable from './shared/DataTable.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import gql from 'graphql-tag';

export default {
  components: {LoadingIndicator, DataTable, BuildSummaryCard},

  props: {
    buildId: {
      type: Number,
      required: true,
    },
  },

  apollo: {
    urls: {
      query: gql`
        query($buildId: ID!, $after: String) {
          build(id: $buildId) {
            id
            urls(after: $after) {
              edges {
                node {
                  id
                  href
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
      update: data => data?.build?.urls,
      variables() {
        return {
          buildId: this.buildId,
        };
      },
      result({data}) {
        if (data && data.build.urls.pageInfo.hasNextPage) {
          this.$apollo.queries.urls.fetchMore({
            variables: {
              buildId: this.buildId,
              after: data.build.urls.pageInfo.endCursor,
            },
          });
        }
      },
    },

    files: {
      query: gql`
        query($buildId: ID!, $after: String) {
          build(id: $buildId) {
            id
            files(after: $after) {
              edges {
                node {
                  id
                  name
                  size
                  sha1sum
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
      update: data => data?.build?.files,
      variables() {
        return {
          buildId: this.buildId,
        };
      },
      result({data}) {
        if (data && data.build.files.pageInfo.hasNextPage) {
          this.$apollo.queries.files.fetchMore({
            variables: {
              buildId: this.buildId,
              after: data.build.files.pageInfo.endCursor,
            },
          });
        }
      },
    },
  },

  computed: {
    formattedUrlRows() {
      return this.urls.edges?.map(edge => {
        return {
          url: {
            value: edge.node.href,
            text: edge.node.href,
            href: edge.node.href,
          },
        };
      });
    },

    formattedFileRows() {
      return this.files.edges?.map(edge => {
        return {
          name: {
            value: edge.node.name,
            text: edge.node.name,
            href: `${this.$baseURL}/builds/${this.buildId}/files/${edge.node.id}`,
          },
          size: {
            value: edge.node.size,
            text: this.humanReadableFileSize(edge.node.size),
          },
          hash: {
            value: edge.node.sha1sum,
            text: edge.node.sha1sum,
          },
        };
      });
    },
  },

  methods: {
    humanReadableFileSize(bytes) {
      if (bytes < 1024) {
        return `${bytes} bytes`;
      }
      else if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(2)} KiB`;
      }
      else if (bytes < 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(2)} MiB`;
      }
      else if (bytes < 1024 * 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GiB`;
      }
      else {
        return `${(bytes / (1024 * 1024 * 1024 * 1024)).toFixed(2)} TiB`;
      }
    },
  },
};
</script>
