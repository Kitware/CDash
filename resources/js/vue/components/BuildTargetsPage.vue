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

    <loading-indicator :is-loading="!build || !targets">
      <div class="tw-w-full tw-bg-base-100 tw-flex tw-flex-col tw-rounded-lg tw-border tw-border-gray-200 tw-p-4">
        <CommandGanttChart :commands="formattedCommands" />
      </div>
    </loading-indicator>

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
import CommandGanttChart from './shared/CommandGanttChart.vue';
import { DateTime, Duration } from 'luxon';

export default {
  components: {
    CommandGanttChart,
    FilterBuilder,
    LoadingIndicator,
    DataTable,
    BuildSummaryCard,
  },

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
        query($buildId: ID!, $filters: BuildTargetsFiltersMultiFilterInput) {
          build(id: $buildId) {
            id
            targets(filters: $filters, first: 100000) {
              edges {
                node {
                  id
                  name
                  type
                }
              }
            }
            children {
              edges {
                node {
                  id
                  targets(filters: $filters, first: 100000) {
                    edges {
                      node {
                        id
                        name
                        type
                      }
                    }
                  }
                }
              }
            }
          }
        }
      `,
      update: data => {
        let targets = data?.build?.targets?.edges || [];

        if (data?.build?.children) {
          data.build.children.edges.forEach(childEdge => {
            targets = targets.concat(childEdge?.node?.targets?.edges || []);
          });
        }

        return { edges: targets };
      },
      variables() {
        return {
          buildId: this.buildId,
          filters: this.initialFilters,
        };
      },
    },

    build: {
      query: gql`
        query($buildId: ID!) {
          build(id: $buildId) {
            id
            commands(first: 100000) {
              edges {
                node {
                  id
                  startTime
                  duration
                  source
                  language
                  config
                  type
                  target {
                    id
                    name
                  }
                }
              }
            }

            children(first: 100000) {
              edges {
                node {
                  id
                  commands(first: 100000) {
                    edges {
                      node {
                        id
                        startTime
                        duration
                        source
                        language
                        config
                        type
                        target {
                          id
                          name
                        }
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
          buildId: this.buildId,
        };
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
          },
          type: {
            value: edge.node.type,
            text: this.humanReadableTargetType(edge.node.type),
          },
        };
      });
    },

    visibleTargetIds() {
      if (!this.targets || !this.targets.edges) {
        return new Set();
      }
      return new Set(this.targets.edges.map(edge => edge.node.id));
    },

    formattedCommands() {
      if (!this.build) {
        return [];
      }
      let commands = [...this.build.commands.edges];
      this.build.children.edges.forEach((child) => commands = commands.concat(child.node.commands.edges));

      const visibleIds = this.visibleTargetIds;

      return commands?.map(edge => {
        const targetId = edge.node.target?.id;
        return {
          id: edge.node.id,
          startTime: DateTime.fromISO(edge.node.startTime),
          duration: Duration.fromMillis(edge.node.duration),
          type: edge.node.type,
          targetName: edge.node.target?.name,
          source: edge.node.source,
          language: edge.node.language,
          config: edge.node.config,
          disabled: !targetId || !visibleIds.has(targetId),
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
