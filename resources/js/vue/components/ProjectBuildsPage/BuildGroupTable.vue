<template>
  <div>
    <h3
      class="buildgroupname"
      style="display: flex; align-items: center; justify-content: space-between;"
    >
      <div>
        <a
          :id="`buildgroup-${group.linkname}`"
          :href="`#buildgroup-${group.linkname}`"
          class="grouptrigger cdash-link"
          data-test="build-group-name"
        >
          {{ group.name }}
        </a>
        <span
          class="buildnums"
          style="margin-left: 10px;"
          data-test="build-group-count"
        >
          {{ group.numbuildslabel }}
        </span>
      </div>

      <span style="display: inline-flex; gap: 8px; align-items: center; font-size: 12px;">
        <button
          v-if="settings.admin"
          class="btn btn-xs"
          :class="selecting ? 'btn-info' : 'btn-default'"
          title="Toggle bulk selection mode"
          style="font-size: 12px;"
          data-test="bulk-select-toggle"
          @click="toggleSelectionMode"
        >
          <span
            class="glyphicon"
            :class="selecting ? 'glyphicon-check' : 'glyphicon-unchecked'"
          />
          {{ selecting ? 'Exit Selection' : 'Bulk Select' }}
        </button>
        <a
          class="btn btn-xs btn-default"
          data-test="view-timeline-link"
          :href="timelineUrl"
          style="text-decoration: none; font-size: 12px;"
        >
          <span class="glyphicon glyphicon-stats" />
          View Timeline
        </a>
      </span>
    </h3>

    <div
      v-if="settings.admin && selectedBuilds.length > 0"
      data-test="bulk-actions"
      style="background: linear-gradient(135deg, #f0f8ff 0%, #e6f2ff 100%); border: 2px solid #667eea; border-radius: 8px; padding: 16px; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);"
    >
      <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span
            style="font-weight: 600; color: #667eea; font-size: 14px;"
            data-test="bulk-selected-count"
          >
            <span
              class="glyphicon glyphicon-ok-sign"
              style="margin-right: 5px;"
            />
            {{ selectedBuilds.length }} build(s) selected
          </span>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
          <label style="margin: 0; font-weight: 500;">Move to:</label>
          <select
            v-model="bulkTargetGroup"
            data-test="bulk-move-select"
            style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px;"
          >
            <option value="">
              -- Select Group --
            </option>
            <option
              v-for="targetGroup in settings.allBuildGroups"
              :key="targetGroup.id"
              :value="String(targetGroup.id)"
            >
              {{ targetGroup.name }}
            </option>
          </select>
          <button
            class="btn btn-sm btn-primary"
            data-test="bulk-move-button"
            :disabled="!bulkTargetGroup"
            @click="bulkMove"
          >
            <span class="glyphicon glyphicon-arrow-right" />
            Move
          </button>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
          <!-- Expected builds belong to daily groups. -->
          <button
            v-if="group.type !== 'Latest'"
            class="btn btn-sm btn-success"
            data-test="bulk-mark-expected-button"
            @click="bulkMarkExpected(1)"
          >
            <span class="glyphicon glyphicon-ok" />
            Mark as Expected
          </button>
          <button
            v-if="group.type !== 'Latest'"
            class="btn btn-sm btn-warning"
            data-test="bulk-mark-not-expected-button"
            @click="bulkMarkExpected(0)"
          >
            <span class="glyphicon glyphicon-remove" />
            Mark as Not Expected
          </button>
          <button
            class="btn btn-sm btn-danger"
            data-test="bulk-delete-button"
            @click="bulkDelete"
          >
            <span class="glyphicon glyphicon-trash" />
            Delete
          </button>
        </div>

        <div style="margin-left: auto;">
          <button
            class="btn btn-sm btn-default"
            style="border-color: #dc3545; color: #dc3545;"
            data-test="bulk-clear-selection-button"
            @click="clearSelection"
          >
            <span class="glyphicon glyphicon-remove-circle" />
            Clear Selection
          </button>
        </div>
      </div>
    </div>

    <table
      :id="`project_${settings.projectId}_${group.id}`"
      border="0"
      cellpadding="4"
      cellspacing="0"
      width="100%"
      class="tabb"
      data-test="build-table"
    >
      <thead>
        <tr class="table-heading1">
          <td
            v-if="selecting"
            class="nob"
          />
          <td
            colspan="2"
            class="nob"
          />
          <td
            v-if="group.hasupdatedata"
            :colspan="1 + timeColumns"
            class="center-text botl"
          >
            Update
          </td>
          <td
            v-if="group.hasconfiguredata"
            :colspan="2 + timeColumns"
            class="center-text botl"
          >
            Configure
          </td>
          <td
            v-if="group.hascompilationdata"
            :colspan="2 + timeColumns"
            class="center-text botl"
          >
            Build
          </td>
          <td
            v-if="group.hastestdata"
            :colspan="3 + settings.extraTestColumns"
            class="center-text botl"
          >
            Test
          </td>
          <td
            align="right"
            class="nob"
            :colspan="settings.numColumns"
          />
        </tr>

        <tr class="table-heading">
          <th
            v-if="selecting"
            rowspan="2"
            class="column-header"
            style="width: 30px;"
          >
            <input
              type="checkbox"
              data-test="bulk-select-all"
              title="Select all builds"
              :checked="selectAll"
              @change="toggleSelectAll($event.target.checked)"
            >
          </th>

          <SortableHeader
            v-if="settings.childView"
            colspan="2"
            rowspan="2"
            class="column-header"
            data-test="header-subproject"
            :direction="direction('label')"
            @sort="(append) => sort('label', append)"
          >
            SubProject
          </SortableHeader>

          <template v-else>
            <SortableHeader
              rowspan="2"
              class="column-header"
              data-test="header-site"
              :direction="direction('site')"
              @sort="(append) => sort('site', append)"
            >
              Site
            </SortableHeader>
            <SortableHeader
              rowspan="2"
              class="column-header"
              data-test="header-build-name"
              :direction="direction('buildname')"
              @sort="(append) => sort('buildname', append)"
            >
              Build Name
            </SortableHeader>
          </template>
        </tr>

        <tr class="table-heading">
          <SortableHeader
            v-for="column in columns"
            :key="column.field"
            class="column-header"
            :class="{ nob: column.field === 'label' }"
            :rowspan="column.field === 'label' ? 2 : undefined"
            :data-test="`header-${column.name}`"
            :direction="direction(column.field)"
            @sort="(append) => sort(column.field, append)"
          >
            {{ column.title }}
          </SortableHeader>
        </tr>
      </thead>

      <tbody>
        <BuildRow
          v-for="(build, index) in pageBuilds"
          :key="rowKey(build)"
          :build="build"
          :group="group"
          :settings="settings"
          :selecting="selecting"
          :selected="selectedIds.includes(build.id)"
          :class="index % 2 === 1 ? 'odd' : 'even'"
          valign="middle"
          data-test="build-row"
          @update:selected="(selected) => select(build, selected)"
          @removed="remove(build)"
          @update:build="(changes) => Object.assign(build, changes)"
        />
      </tbody>
    </table>

    <div
      v-if="numPerPage !== -1 && builds.length > numPerPage"
      data-test="pagination"
    >
      <TablePagination
        v-model:page="page"
        :page-count="pageCount"
      />
    </div>

    <div v-if="group.builds.length > 10">
      <ItemsPerPage
        v-model="numPerPage"
        :options="[10, 25, 50, 100]"
        cookie="num_builds_per_page"
      />
      <br>
      <br>
    </div>
  </div>
</template>

<script>
import orderBy from '../shared/legacy/sorting/angularOrderBy';
import { useSortFields } from '../shared/legacy/sorting/useSortFields';
import SortableHeader from '../shared/legacy/sorting/SortableHeader.vue';
import { pageCount, pageRows } from '../shared/legacy/pagination/paging';
import TablePagination from '../shared/legacy/pagination/TablePagination.vue';
import ItemsPerPage from '../shared/legacy/pagination/ItemsPerPage.vue';
import LegacyApi from './legacy/LegacyApi';
import { showEmptyBuildsLast } from './legacy/showEmptyLast';
import { rowKey } from './rowKey';
import BuildRow from './BuildRow.vue';

function sortBuilds(builds, sortFields) {
  return showEmptyBuildsLast(orderBy(builds, sortFields), sortFields);
}

/**
 * A build group's builds, with sorting, paging and bulk actions for project administrators.  The
 * group is described by the legacy index API.
 */
export default {
  name: 'BuildGroupTable',

  components: {
    BuildRow,
    ItemsPerPage,
    SortableHeader,
    TablePagination,
  },

  props: {
    group: {
      type: Object,
      required: true,
    },

    /** The page-wide settings.  See ProjectBuildsPage.vue. */
    settings: {
      type: Object,
      required: true,
    },

    /** The order to show the builds in first, as angularOrderBy.js predicates. */
    initialSort: {
      type: Array,
      required: true,
    },

    /** The cookie which remembers the order the user chooses. */
    sortCookie: {
      type: String,
      required: true,
    },

    /** The number of builds per page, or -1 for all of them. */
    initialNumPerPage: {
      type: Number,
      required: true,
    },
  },

  setup(props) {
    const { sortFields, direction, sort } = useSortFields(() => props.sortCookie, () => props.initialSort);
    return { sortFields, direction, setSortFields: sort };
  },

  data() {
    return {
      builds: sortBuilds(this.group.builds, this.initialSort),
      page: 1,
      numPerPage: this.initialNumPerPage,
      selecting: false,
      selectedIds: [],
      selectAll: false,
      bulkTargetGroup: '',
    };
  },

  computed: {
    /** The number of time columns for each section, which are only shown in the advanced view. */
    timeColumns() {
      return this.settings.showTimeColumns ? 1 : 0;
    },

    /** The sortable columns in the second header row. */
    columns() {
      const group = this.group;
      const settings = this.settings;
      const columns = [
        [group.hasupdatedata, 'update-files', 'update.files', settings.updateType],
        [group.hasupdatedata && settings.showTimeColumns, 'update-time', 'update.timefull', 'Time'],
        [group.hasconfiguredata, 'configure-errors', 'configure.error', 'Error'],
        [group.hasconfiguredata, 'configure-warnings', 'configure.warning', 'Warn'],
        [group.hasconfiguredata && settings.showTimeColumns, 'configure-time', 'configure.timefull', 'Time'],
        [group.hascompilationdata, 'build-errors', 'compilation.error', 'Error'],
        [group.hascompilationdata, 'build-warnings', 'compilation.warning', 'Warn'],
        [group.hascompilationdata && settings.showTimeColumns, 'build-time', 'compilation.timefull', 'Time'],
        [group.hastestdata, 'tests-not-run', 'test.notrun', 'Not Run'],
        [group.hastestdata, 'tests-failed', 'test.fail', 'Fail'],
        [group.hastestdata, 'tests-passed', 'test.pass', 'Pass'],
        [group.hastestdata && settings.showTestTime, 'test-time', 'test.timefull', 'Time'],
        [group.hastestdata && settings.showProcTime, 'proc-time', 'test.procTimeFull', 'Proc Time'],
        [settings.showStartTime, 'start-time', 'builddatefull', 'Start Time'],
        [settings.showOrder, 'order', 'position', 'Order'],
        [settings.showLabels, 'labels', 'label', 'Labels'],
      ];
      return columns
        .filter(([shown]) => shown)
        .map(([, name, field, title]) => ({ name, field, title }));
    },

    pageCount() {
      return pageCount(this.builds.length, this.numPerPage);
    },

    pageBuilds() {
      return pageRows(this.builds, this.page, this.numPerPage);
    },

    selectedBuilds() {
      // A build can be listed more than once in a dynamic group.
      const selected = new Map();
      for (const build of this.builds) {
        if (build.id && this.selectedIds.includes(build.id) && !selected.has(build.id)) {
          selected.set(build.id, build);
        }
      }
      return [...selected.values()];
    },

    timelineUrl() {
      return `viewBuildGroup.php?project=${this.settings.projectNameEncoded}&buildgroup=${this.group.linkname}`
        + `&date=${this.settings.date}${this.settings.filterUrl}`;
    },
  },

  watch: {
    pageCount(pageCount) {
      this.page = Math.min(this.page, pageCount);
    },
  },

  methods: {
    rowKey,

    sort(field, append) {
      this.setSortFields(field, append);
      // Ties keep their previous order.
      this.builds = sortBuilds(this.builds, this.sortFields);
    },

    remove(build) {
      this.builds = this.builds.filter((b) => b !== build);
      this.selectedIds = this.selectedIds.filter((id) => id !== build.id);
    },

    toggleSelectionMode() {
      this.selecting = !this.selecting;
      if (!this.selecting) {
        this.clearSelection();
      }
    },

    select(build, selected) {
      if (selected) {
        this.selectedIds.push(build.id);
      } else {
        this.selectedIds = this.selectedIds.filter((id) => id !== build.id);
        this.selectAll = false;
      }
    },

    /**
     * Select every build on the current page, other than expected builds which haven't submitted.
     */
    toggleSelectAll(selectAll) {
      this.selectAll = selectAll;
      this.selectedIds = selectAll ? [...new Set(this.pageBuilds.filter((build) => build.id).map((build) => build.id))] : [];
    },

    clearSelection() {
      this.selectAll = false;
      this.selectedIds = [];
    },

    /**
     * Run an action on each selected build, then reload the page.
     */
    async bulkAction(action, description) {
      if (this.selectedBuilds.length === 0) {
        return;
      }
      try {
        await Promise.all(this.selectedBuilds.map(action));
        window.location.reload();
      } catch (error) {
        console.error(`Error ${description} builds:`, error);
        alert(`An error occurred while ${description} builds. Please try again.`);
      }
    },

    bulkMove() {
      const newgroupid = Number(this.bulkTargetGroup);
      if (!newgroupid) {
        return;
      }
      return this.bulkAction(async (build) => {
        // Keep whether each build is expected.
        const expected = await LegacyApi.isBuildExpected(build.id);
        await LegacyApi.moveBuild(build.id, newgroupid, expected);
      }, 'moving');
    },

    bulkMarkExpected(expected) {
      return this.bulkAction((build) => LegacyApi.setBuildExpected(build.id, Number(this.group.id), expected), 'updating');
    },

    bulkDelete() {
      if (this.selectedBuilds.length === 0) {
        return;
      }
      if (!confirm(`Are you sure you want to delete ${this.selectedBuilds.length} build(s)? This action cannot be undone.`)) {
        return;
      }
      return this.bulkAction((build) => LegacyApi.deleteBuild(build.id), 'deleting');
    },
  },
};
</script>
