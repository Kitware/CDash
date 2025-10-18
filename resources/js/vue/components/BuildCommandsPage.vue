<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <BuildSummaryCard :build-id="buildId" />

    <FilterBuilder
      filter-type="BuildCommandsFiltersMultiFilterInput"
      primary-record-name="commands"
      :initial-filters="initialFilters"
      :execute-query-link="executeQueryLink"
      @change-filters="filters => changedFilters = filters"
    />

    <loading-indicator :is-loading="!formattedChartCommands">
      <CommandGanttChart :commands="formattedChartCommands" />
    </loading-indicator>
  </div>
</template>

<script>
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
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
    allCommands: {
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
      update: (data) => {
        let commands = [...data.build.commands.edges];
        data.build.children.edges.forEach((child) => commands = commands.concat(child.node.commands.edges));
        return commands;
      },
      variables() {
        return {
          buildId: this.buildId,
        };
      },
    },

    filteredCommands: {
      query: gql`
        query($buildId: ID!, $filters: BuildCommandsFiltersMultiFilterInput) {
          build(id: $buildId) {
            id
            commands(filters: $filters, first: 100000) {
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
                  commands(filters: $filters, first: 100000) {
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
      update: (data) => {
        let commands = [...data.build.commands.edges];
        data.build.children.edges.forEach((child) => commands = commands.concat(child.node.commands.edges));
        return commands;
      },
      variables() {
        return {
          buildId: this.buildId,
          filters: this.initialFilters,
        };
      },
    },
  },

  computed: {
    executeQueryLink() {
      return `${window.location.origin}${window.location.pathname}?filters=${encodeURIComponent(JSON.stringify(this.changedFilters))}`;
    },

    visibleCommandIds() {
      if (!this.filteredCommands) {
        return new Set();
      }
      return new Set(this.filteredCommands.map(edge => edge.node.id));
    },

    formattedChartCommands() {
      if (!this.allCommands) {
        return [];
      }

      return this.allCommands?.map(edge => {
        return {
          startTime: DateTime.fromISO(edge.node.startTime),
          duration: Duration.fromMillis(edge.node.duration),
          type: edge.node.type,
          targetName: edge.node.target?.name,
          source: edge.node.source,
          language: edge.node.language,
          config: edge.node.config,
          disabled: !this.visibleCommandIds.has(edge.node.id),
        };
      });
    },
  },
};
</script>
