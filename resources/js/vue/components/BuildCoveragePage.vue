<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <BuildSummaryCard :build-id="buildId" />

    <div>
      <span class="tw-font-bold tw-text-xl">
        Coverage Summary
      </span>
      <loading-indicator :is-loading="!coverage">
        <table class="tw-table tw-table-sm tw-w-auto">
          <tbody>
            <tr>
              <th>Line Coverage</th>
              <td data-test="line-coverage-summary">
                <progress
                  class="tw-progress tw-w-24"
                  :class="percentToProgressBarColorClass(totalPercentageOfLinesCovered)"
                  :value="totalPercentageOfLinesCovered"
                  max="100"
                />
                {{ totalPercentageOfLinesCovered }}% ({{ totalLinesCovered }} / {{ totalLinesCovered + totalLinesUncovered }})
              </td>
            </tr>
            <tr v-if="hasBranchCoverage">
              <th>Branch Coverage</th>
              <td data-test="branch-coverage-summary">
                <progress
                  class="tw-progress tw-w-24"
                  :class="percentToProgressBarColorClass(totalPercentageOfBranchesCovered)"
                  :value="totalPercentageOfBranchesCovered"
                  max="100"
                />
                {{ totalPercentageOfBranchesCovered }}% ({{ totalBranchesCovered }} / {{ totalBranchesCovered + totalBranchesUncovered }})
              </td>
            </tr>
            <tr>
              <th>File Coverage</th>
              <td data-test="file-coverage-summary">
                <progress
                  class="tw-progress tw-w-24"
                  :class="percentToProgressBarColorClass(totalPercentageOfFilesCovered)"
                  :value="totalPercentageOfFilesCovered"
                  max="100"
                />
                {{ totalPercentageOfFilesCovered }}% ({{ totalFilesCovered }} / {{ totalFilesCovered + totalFilesUncovered }})
              </td>
            </tr>
          </tbody>
        </table>
      </loading-indicator>
    </div>

    <filter-builder
      filter-type="BuildCoverageFiltersMultiFilterInput"
      primary-record-name="coverage files"
      :initial-filters="initialFilters"
      :execute-query-link="executeQueryLink"
      @change-filters="filters => changedFilters = filters"
    />

    <div>
      <div class="tw-flex tw-flex-row tw-gap-2 tw-items-center">
        <button
          class="tw-btn tw-btn-xs"
          :class="{ 'tw-btn-disabled': currentPrefix === '' }"
          data-test="breadcrumbs-back-button"
          @click="currentPrefix = directoryAbovePath(currentPrefix)"
        >
          <font-awesome-icon :icon="FA.faReply" />
          Back
        </button>
        <div
          class="tw-breadcrumbs"
          data-test="breadcrumbs"
        >
          <ul>
            <li>
              <a
                href=""
                class="tw-italic"
                @click.prevent="currentPrefix = ''"
              >{{ projectName }}</a>
            </li>
            <li
              v-for="[index, dir_segment] of currentPrefix.split('/').slice(0, -1).entries()"
              :key="dir_segment"
            >
              <a
                href=""
                @click.prevent="currentPrefix = currentPrefix.split('/').slice(0, index + 1).join('/') + '/'"
              >
                <font-awesome-icon
                  :icon="FA.faFolder"
                  class="tw-mr-1"
                />
                {{ dir_segment }}
              </a>
            </li>
          </ul>
        </div>
      </div>
      <loading-indicator :is-loading="!coverage">
        <data-table
          :columns="[
            ...(hasSubProjects ? [{
              name: 'subProject',
              displayName: 'SubProject',
            }] : []),
            {
              name: 'path',
              displayName: 'Name',
              expand: true,
            },
            {
              name: 'linePercentage',
              displayName: 'Percentage',
            },
            {
              name: 'lines',
              displayName: 'Lines Tested',
            },
            ...(hasBranchCoverage ? [{
              name: 'branchPercentage',
              displayName: 'Branch Percentage',
            }, {
              name: 'branches',
              displayName: 'Branches Tested',
            }] : []),
          ]"
          :rows="formattedTableRows"
          :full-width="true"
          test-id="coverage-table"
          initial-sort-column="linePercentage"
          :initial-sort-asc="false"
        >
          <template #path="{ props: obj }">
            <a
              v-if="obj.isDirectory"
              href=""
              data-test="coverage-directory-link"
              @click.prevent="currentPrefix += obj.path + '/';"
            >
              <font-awesome-icon :icon="FA.faFolder" /> {{ obj.path }}
            </a>
            <a
              v-else
              :href="`${$baseURL}/builds/${buildId}/coverage/${obj.fileId}`"
              data-test="coverage-file-link"
            >
              <font-awesome-icon :icon="FA.faFile" /> {{ obj.path }}
            </a>
          </template>
          <template #linePercentage="{ props: { text: pct } }">
            <progress
              class="tw-progress tw-w-24"
              :class="percentToProgressBarColorClass(pct)"
              :value="pct"
              max="100"
            /> {{ pct }}%
          </template>
          <template #branchPercentage="{ props: { text: pct } }">
            <progress
              class="tw-progress tw-w-24"
              :class="percentToProgressBarColorClass(pct)"
              :value="pct"
              max="100"
            /> {{ pct }}%
          </template>
        </data-table>
      </loading-indicator>
    </div>
  </div>
</template>

<script>
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import DataTable from './shared/DataTable.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import gql from 'graphql-tag';
import FilterBuilder from './shared/FilterBuilder.vue';
import {faFolder, faReply} from '@fortawesome/free-solid-svg-icons';
import {faFile} from '@fortawesome/free-regular-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';

export default {
  components: {FontAwesomeIcon, FilterBuilder, LoadingIndicator, DataTable, BuildSummaryCard},

  props: {
    buildId: {
      type: Number,
      required: true,
    },

    initialFilters: {
      type: Object,
      required: true,
    },

    projectName: {
      type: String,
      required: true,
    },

    coveragePercentCutoff: {
      type: Number,
      required: true,
    },
  },

  apollo: {
    coverage: {
      query: gql`
        query(
          $buildid: ID,
          $filters: BuildCoverageFiltersMultiFilterInput,
        ) {
          build(id: $buildid) {
            id
            coverage(filters: $filters, first: 1000000) {
              edges {
                node {
                  id
                  filePath
                  linesOfCodeTested
                  linesOfCodeUntested
                  branchesTested
                  branchesUntested
                }
              }
            }
            children(first: 100000) {
              edges {
                node {
                  id
                  coverage(filters: $filters, first: 1000000) {
                    edges {
                      node {
                        id
                        filePath
                        linesOfCodeTested
                        linesOfCodeUntested
                        branchesTested
                        branchesUntested
                      }
                    }
                  }
                  subProject {
                    id
                    name
                  }
                }
              }
            }
          }
        }
      `,
      update: (data) => {
        let coverage = [...data.build.coverage.edges];
        data.build.children.edges.forEach((child) => {
          coverage = coverage.concat(
            child.node.coverage.edges.map((coverage) => ({
              ...coverage,
              subProject: child.node.subProject.name,
            })),
          );
        });
        return coverage;
      },
      variables() {
        return {
          buildid: this.buildId,
          filters: this.initialFilters,
        };
      },
    },
  },

  data() {
    return {
      changedFilters: JSON.parse(JSON.stringify(this.initialFilters)),
      currentPrefix: '',
    };
  },

  computed: {
    FA() {
      return {
        faFolder,
        faFile,
        faReply,
      };
    },

    hasSubProjects() {
      return this.coverage?.some((element) => element.subProject) ?? false;
    },

    executeQueryLink() {
      return `${window.location.origin}${window.location.pathname}?filters=${encodeURIComponent(JSON.stringify(this.changedFilters))}`;
    },

    hasBranchCoverage() {
      return this.coverage?.some((edge) => edge.node.branchesTested + edge.node.branchesUntested > 0) ?? false;
    },

    totalLinesCovered() {
      return this.coverage?.reduce((total, edge) => total + edge.node.linesOfCodeTested, 0) ?? 0;
    },

    totalLinesUncovered() {
      return this.coverage?.reduce((total, edge) => total + edge.node.linesOfCodeUntested, 0) ?? 0;
    },

    totalPercentageOfLinesCovered() {
      if (this.totalLinesCovered + this.totalLinesUncovered === 0) {
        return 100;
      }

      return ((this.totalLinesCovered / (this.totalLinesCovered + this.totalLinesUncovered)) * 100).toFixed(2);
    },

    totalBranchesCovered() {
      return this.coverage?.reduce((total, edge) => total + edge.node.branchesTested, 0) ?? 0;
    },

    totalBranchesUncovered() {
      return this.coverage?.reduce((total, edge) => total + edge.node.branchesUntested, 0) ?? 0;
    },

    totalPercentageOfBranchesCovered() {
      if (this.totalBranchesCovered + this.totalBranchesUncovered === 0) {
        return 100;
      }

      return ((this.totalBranchesCovered / (this.totalBranchesCovered + this.totalBranchesUncovered)) * 100).toFixed(2);
    },

    totalFilesCovered() {
      return this.coverage?.reduce((total, edge) => total + (edge.node.linesOfCodeTested + edge.node.linesOfCodeUntested > 0 ? 1 : 0), 0) ?? 0;
    },

    totalFilesUncovered() {
      return this.coverage?.reduce((total, edge) => total + (edge.node.linesOfCodeTested + edge.node.linesOfCodeUntested === 0 ? 1 : 0), 0) ?? 0;
    },

    totalPercentageOfFilesCovered() {
      if (this.totalFilesCovered + this.totalFilesUncovered === 0) {
        return 100;
      }

      return ((this.totalFilesCovered / (this.totalFilesCovered + this.totalFilesUncovered)) * 100).toFixed(2);
    },

    formattedTableRows() {
      const coverageByPrefix = {};
      this.coverage?.filter(edge => {
        return edge.node.filePath.startsWith(this.currentPrefix) || edge.node.filePath.startsWith(`./${this.currentPrefix}`);
      }).forEach((edge) => {
        const pathWithoutPrefix = edge.node.filePath.replace(/^.\//, '').slice(this.currentPrefix.length);
        const pathComponents = pathWithoutPrefix.split('/');
        const cleanedPath = pathComponents[0];
        const isDirectory = pathComponents.length > 1;

        if (cleanedPath in coverageByPrefix) {
          coverageByPrefix[cleanedPath].linesOfCodeTested += edge.node.linesOfCodeTested;
          coverageByPrefix[cleanedPath].linesOfCodeUntested += edge.node.linesOfCodeUntested;
          coverageByPrefix[cleanedPath].branchesTested += edge.node.branchesTested;
          coverageByPrefix[cleanedPath].branchesUntested += edge.node.branchesUntested;
        }
        else {
          coverageByPrefix[cleanedPath] = {
            subProject: !isDirectory && edge.subProject ? edge.subProject : '',
            path: {
              path: cleanedPath,
              isDirectory: isDirectory,
              fileId: !isDirectory ? edge.node.id : '',
            },
            linesOfCodeTested: edge.node.linesOfCodeTested,
            linesOfCodeUntested: edge.node.linesOfCodeUntested,
            branchesTested: edge.node.branchesTested,
            branchesUntested: edge.node.branchesUntested,
          };
        }
      });

      return Object.values(coverageByPrefix).map(obj => {
        const linePct = this.computePercentage(obj.linesOfCodeTested, obj.linesOfCodeUntested);
        const branchPct = this.computePercentage(obj.branchesTested, obj.branchesUntested);

        return {
          ...obj,
          linePercentage: {
            text: linePct,
            value: parseFloat(linePct),
          },
          lines: {
            text: `${obj.linesOfCodeTested} / ${obj.linesOfCodeTested + obj.linesOfCodeUntested}`,
            value: parseInt(obj.linesOfCodeTested),
          },
          branchPercentage: {
            text: branchPct,
            value: parseFloat(branchPct),
          },
          branches: {
            text: `${obj.branchesTested} / ${obj.branchesTested + obj.branchesUntested}`,
            value: parseInt(obj.branchesTested),
          },
        };
      });
    },
  },

  methods: {
    computePercentage(covered, uncovered) {
      if (covered + uncovered === 0) {
        return 100;
      }

      return ((covered / (covered + uncovered)) * 100).toFixed(1);
    },

    percentToProgressBarColorClass(pct) {
      if (pct >= this.coveragePercentCutoff) {
        return 'tw-progress-success';
      }
      else if (pct >= 0.7 * this.coveragePercentCutoff) {
        return 'tw-progress-warning';
      }
      else {
        return 'tw-progress-error';
      }
    },

    directoryAbovePath(path) {
      const pathComponents = path.endsWith('/') ? path.split('/').slice(0, -1) : path.split('/');

      if (pathComponents.length === 1) {
        return '';
      }

      return `${pathComponents.slice(0, -1).join('/')}/`;
    },
  },
};
</script>
