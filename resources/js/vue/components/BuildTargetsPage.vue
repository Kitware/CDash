<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <BuildSummaryCard :build-id="buildId" />

    <FilterBuilder
      filter-type="BuildTargetsFiltersMultiFilterInput"
      primary-record-name="targets"
      :initial-filters="initialFilters"
      :execute-query-link="executeQueryLink"
      @change-filters="filters => changedFilters = filters"
    />

    <LoadingIndicator :is-loading="!targets">
      <DataTable
        v-if="targets.edges.length > 0"
        :column-groups="[
          {
            displayName: 'Targets',
            width: 100,
          }
        ]"
        :columns="[
          {
            name: 'name',
            displayName: 'Name',
          },
          {
            name: 'type',
            displayName: 'Type',
          },
        ]"
        :rows="formattedTargetRows"
        :full-width="true"
        test-id="targets-table"
      />
    </LoadingIndicator>
  </div>
</template>

<script>
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import DataTable from './shared/DataTable.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import gql from 'graphql-tag';
import FilterBuilder from './shared/FilterBuilder.vue';

export default {
  components: {FilterBuilder, LoadingIndicator, DataTable, BuildSummaryCard},

  props: {
    buildId: {
      type: Number,
      required: true,
    },

    initialFilters: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      changedFilters: JSON.parse(JSON.stringify(this.initialFilters)),
    };
  },

  apollo: {
    targets: {
      query: gql`
        query($buildId: ID!, $filters: BuildTargetsFiltersMultiFilterInput, $after: String) {
          build(id: $buildId) {
            id
            targets(filters: $filters, after: $after) {
              edges {
                node {
                  id
                  name
                  type
                }
              }
              pageInfo {
                hasNextPage
                endCursor
              }
            }
          }
        }
      `,
      update: data => data?.build?.targets,
      variables() {
        return {
          buildId: this.buildId,
          filters: this.initialFilters,
        };
      },
      result({data}) {
        if (data && data.build.targets.pageInfo.hasNextPage) {
          this.$apollo.queries.targets.fetchMore({
            variables: {
              buildId: this.buildId,
              filters: this.initialFilters,
              after: data.build.targets.pageInfo.endCursor,
            },
          });
        }
      },
    },
  },

  computed: {
    executeQueryLink() {
      return `${window.location.origin}${window.location.pathname}?filters=${encodeURIComponent(JSON.stringify(this.changedFilters))}`;
    },

    formattedTargetRows() {
      return this.targets.edges?.map(edge => {
        return {
          name: {
            value: edge.node.name,
            text: edge.node.name,
            href: `${this.$baseURL}/targets/${edge.node.id}`,
          },
          type: {
            value: edge.node.type,
            text: this.humanReadableTargetType(edge.node.type),
          },
        };
      });
    },
  },

  methods: {
    humanReadableTargetType(type) {
      switch (type) {
      case 'UNKNOWN':
        return 'Unknown';
      case 'STATIC_LIBRARY':
        return 'Static Library';
      case 'MODULE_LIBRARY':
        return 'Module Library';
      case 'SHARED_LIBRARY':
        return 'Shared Library';
      case 'OBJECT_LIBRARY':
        return 'Object Library';
      case 'INTERFACE_LIBRARY':
        return 'Interface Library';
      case 'EXECUTABLE':
        return 'Executable';
      default:
        return type;
      }
    },
  },
};
</script>
