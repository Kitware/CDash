<template>
  <table
    class="tabb striped"
    :class="{ 'full-width': fullWidth }"
    data-cy="data-table"
  >
    <thead>
      <tr
        v-if="columnGroups.length > 0"
        class="table-heading1"
      >
        <th
          v-for="group in columnGroups"
          :colspan="group.width"
        >
          {{ group.displayName }}
        </th>
      </tr>
      <tr
        v-if="columns.length > 0"
        :class="{ 'table-heading': columnGroups.length > 0, 'table-heading1': columnGroups.length === 0}"
      >
        <th
          v-for="column in columns"
          :class="{ shrink: !column.expand }"
          data-cy="column-header"
          @click="toggleSort(column.name)"
        >
          <font-awesome-icon
            v-if="sortable"
            :icon="sortColumn === column.name ? (sortAsc ? 'fa-caret-up' : 'fa-caret-down') : 'fa-sort'"
          />
          {{ column.displayName }}
        </th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="row in (sortable ? sortedRows : rows)"
        data-cy="data-table-row"
      >
        <td
          v-for="column in columns"
          :class="{ shrink: !column.expand }"
          data-cy="data-table-cell"
        >
          <!--
            Display a custom template for each table cell, or a default template
            if a custom template is not provided.
          -->
          <slot
            :name="column.name"
            :props="row[column.name]"
          >
            <!-- Rows with the href attribute are links. -->
            <a
              v-if="Object.hasOwn(row[column.name], 'href')"
              :href="row[column.name].href"
            >
              {{ row[column.name].value }}
            </a>
            <!-- If this is a text value, just display it. -->
            <template v-else>
              {{ row[column.name] }}
            </template>
          </slot>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

export default {
  components: {
    FontAwesomeIcon,
  },

  props: {
    /**
     * An array of columns.  displayName is the string to be displayed, while name is the identifier for the column.
     *
     * The expected input is of the form:
     *   columns: [
     *   |   {
     *   |   |   displayName: <String>
     *   |   |   name: <String>
     *   |   |   ?expand: <Boolean> = false
     *   |   },
     *   |   ...
     *   | ],
     *   },
     */
    columns: {
      type: Array,
      default: () => [],
    },

    /**
     * An array of column "groups", with a string name and the number of columns to group.
     *
     * The expected input is of the form:
     *   columnGroups: [
     *   |   {
     *   |   |   displayName: <String>
     *   |   |   width: <Integer>
     *   |   },
     *   |   ...
     *   | ],
     *   },
     */
    columnGroups: {
      type: Array,
      default: () => [],
    },

    /**
     * A boolean indicating whether this table should maximize its width
     */
    fullWidth: {
      type: Boolean,
      default: false,
    },

    /**
     * An array of objects of the form:
     *   rows: [
     *   |   {
     *   |   |   <column name>: String | { metadata object },
     *   |   |   ...
     *   |   }
     *   ]
     *
     * Use metadata objects of the following form to specify links:
     *   {
     *       value: String
     *       href: String
     *   }
     *
     * Custom metadata objects can be use to provide props to custom templates passed via slots.
     *
     * A "value" key is required in the metadata object.  The associated value will be used for sorting.
     * In the case of a pure text item, the object will be sorted by text value.
     */
    rows: {
      type: Array,
      default: () => [],
    },

    /**
     * A boolean indicating whether columns should be sortable, and whether the component should emit sorting events.
     */
    sortable: {
      type: Boolean,
      default: true,
    },

    /**
     * If this table is sortable, specifies the initial sort direction.
     */
    initialSortAsc: {
      type: Boolean,
      default: true,
    },

    /**
     * If this table is sortable, specifies the column to sort by initially.  Defaults to the first column.
     */
    initialSortColumn: {
      type: String,
      default: null,
    },
  },

  data() {
    return {
      sortAsc: this.initialSortAsc,
      sortColumn: this.initialSortColumn ?? this.columns[0]['name'],
    };
  },

  computed: {
    sortedRows() {
      return [...this.rows].sort((row1, row2) => {
        // The "value" attribute is required.  Fail if it is not provided.
        // If this is a text-only column, use the text instead.
        const row1_sort = row1[this.sortColumn].value ?? row1[this.sortColumn];
        const row2_sort = row2[this.sortColumn].value ?? row2[this.sortColumn];

        const return_modifier = this.sortAsc ? 1 : -1;
        if (row1_sort > row2_sort) {
          return 1 * return_modifier;
        }
        else if (row1_sort < row2_sort) {
          return -1 * return_modifier;
        }
        return 0;
      });
    },
  },

  methods: {
    toggleSort(column_name) {
      if (this.sortColumn === column_name) {
        this.sortAsc = !this.sortAsc;
      }
      else {
        this.sortAsc = true;
        this.sortColumn = column_name;
      }
    },
  },
};
</script>

<style scoped>
/* TODO: Move the relevant CSS from common.css to this file */

.shrink {
  width: 1px;
  white-space: nowrap;
  text-align: center;
}

.table-heading1 > th:not(.shrink) {
  text-align: left;
}

.table-heading1 > th {
  padding: 6px;
  font-size: 16px;
}

.table-heading th {
  text-align: center;
}

.full-width {
  width: 100%;
}

</style>
