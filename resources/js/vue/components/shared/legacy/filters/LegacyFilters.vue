<template>
  <div id="div_showfilters">
    <div
      v-show="show"
      data-test="filters"
    >
      <div class="table-heading0">
        <a
          class="cdash-link"
          :href="`http://public.kitware.com/Wiki/CDash:Documentation#Filters_on_${pageName}`"
          target="_blank"
          rel="noopener noreferrer"
          style="float: right;"
        >Help</a>
        <h3>Filters</h3>
      </div>
      <ul v-if="filterdata">
        <li
          v-for="(filter, index) in filterdata.filters"
          :id="`filter${index + 1}`"
          :key="filter.key"
          class="filterFields"
          :class="index % 2 === 0 ? 'treven' : 'trodd'"
          data-test="filter"
        >
          <div v-if="'filters' in filter">
            <ul>
              <li
                v-for="(subfilter, subindex) in filter.filters"
                :id="`subfilter${subindex + 1}`"
                :key="subfilter.key"
                class="filterFields"
                data-test="subfilter"
              >
                <LegacyFilterRow
                  :filter="subfilter"
                  :index="subindex"
                  :available-filters="nonContentFilters"
                  :removable="filter.filters.length > 1"
                  @update="(changes) => updateFilter(subfilter, changes)"
                  @add="addFilter(filter.filters, subindex)"
                  @remove="filter.filters.splice(subindex, 1)"
                />
                <span
                  v-if="subindex < filter.filters.length - 1"
                  data-test="filter-block-combine"
                >
                  {{ filterdata.othercombine }}
                </span>
              </li>
            </ul>
            <button
              class="btn btn-default btn-xs"
              data-test="filter-block-remove"
              :disabled="filterdata.filters.length === 1"
              @click="filterdata.filters.splice(index, 1)"
            >
              <span class="smallicon glyphicon glyphicon-minus" />
            </button>
            <button
              class="btn btn-default btn-xs"
              style="margin-left: 0.25em;"
              data-test="filter-block-add"
              @click="addFilter(filterdata.filters, index)"
            >
              <span class="smallicon glyphicon glyphicon-plus" />
            </button>
            <LegacyFilterButtons
              :index="index"
              :count="filterdata.filters.length"
              :filtercombine="filterdata.filtercombine"
              @add-block="addFilterBlock(index)"
              @update:filtercombine="updateCombine"
            />
          </div>

          <div v-else-if="filter.field">
            <LegacyFilterRow
              :filter="filter"
              :index="index"
              :available-filters="filterdata.availablefilters"
              :removable="filterdata.filters.length > 1"
              @update="(changes) => updateFilter(filter, changes)"
              @add="addFilter(filterdata.filters, index)"
              @remove="filterdata.filters.splice(index, 1)"
            />
            <LegacyFilterButtons
              :index="index"
              :count="filterdata.filters.length"
              :filtercombine="filterdata.filtercombine"
              @add-block="addFilterBlock(index)"
              @update:filtercombine="updateCombine"
            />
          </div>
        </li>
      </ul>

      <div v-if="filterdata">
        <div v-if="Number(filterdata.showlimit) === 1">
          Limit results to
          <input
            id="id_limit"
            v-model="limit"
            type="text"
            size="3"
            name="limit"
            align="center"
            data-test="filter-limit"
          >
          rows (0 for unlimited)
        </div>
        <div v-else>
          <input
            id="id_limit"
            type="hidden"
            name="limit"
            :value="limit"
            data-test="filter-limit"
          >
        </div>
      </div>

      <div
        v-if="filterdata?.showdaterange"
        class="form-group"
      >
        <DateRangeInputs
          v-model:begin="begin"
          v-model:end="end"
          :default-date="date"
        />
      </div>

      <div>
        <input
          type="button"
          name="apply"
          value="Apply"
          data-test="filters-apply"
          :disabled="filterdata === null"
          @click="applyFilters"
        >
        &nbsp;
        <input
          type="button"
          name="clear"
          value="Clear"
          data-test="filters-clear"
          :disabled="filterdata === null"
          @click="clearFilters"
        >
        &nbsp;
        <input
          type="button"
          name="create_hyperlink"
          value="Create Hyperlink"
          data-test="filters-create-hyperlink"
          :disabled="filterdata === null"
          @click="hyperlink = createHyperlink()"
        >
      </div>

      <div
        id="div_filtersAsUrl"
        data-test="filters-hyperlink"
      >
        <a
          v-if="hyperlink"
          class="cdash-link"
          :href="hyperlink"
        >{{ hyperlink }}</a>
      </div>
    </div>
  </div>
</template>

<script>
import { legacyRequest, queryParams } from '../legacyRequest';
import { filterString, filtersUrl } from './filterUrls';
import { COMPARISONS, FILTER_DEFINITIONS } from './filterDefinitions';
import LegacyFilterRow from './LegacyFilterRow.vue';
import LegacyFilterButtons from './LegacyFilterButtons.vue';
import DateRangeInputs from './DateRangeInputs.vue';

let nextKey = 0;

/**
 * Give a filter a key for v-for, since filters can be inserted and removed anywhere in the list.
 */
function withKey(filter) {
  return { ...filter, key: nextKey++ };
}

/**
 * The legacy filters panel, which reloads the page with the chosen filters and dates in its URL.  The
 * filters which can be used depend on the page, as filterdata.php describes.
 *
 * A page which shows the panel also needs to:
 *  - Pass `filterstring: filterString(window.location.href)` to its own legacy API, which then
 *    applies the filters and returns `filterdata`.
 *  - Show the panel when `Number(cdash.filterdata?.showfilters) === 1`.
 *  - Toggle it with a link which also changes the URL, using showFiltersUrl().
 *
 * Remove this once the pages which use it use the GraphQL filters.
 */
export default {
  name: 'LegacyFilters',

  components: {
    LegacyFilterRow,
    LegacyFilterButtons,
    DateRangeInputs,
  },

  props: {
    show: {
      type: Boolean,
      required: true,
    },

    /** The page whose filters filterdata.php describes, if not the page's file name. */
    pageId: {
      type: String,
      default: null,
    },

    /** The page's date, e.g. "2025-01-15". */
    date: {
      type: String,
      default: '',
    },

    /** Whether the page's URL chose a date. */
    dateSet: {
      type: Boolean,
      default: false,
    },

    /** The first and last dates of the page's date range, if it has one. */
    initialBegin: {
      type: String,
      default: '',
    },

    initialEnd: {
      type: String,
      default: '',
    },
  },

  data() {
    // A single date is shown as a range of one day.
    const date = this.dateSet ? this.date : '';
    return {
      filterdata: null,
      begin: this.initialBegin || date,
      end: this.initialEnd || date,
      limit: '',
      hyperlink: '',
    };
  },

  computed: {
    /** The file name of the page, e.g. "index.php". */
    pageName() {
      const path = window.location.pathname;
      return path.substring(path.lastIndexOf('/') + 1);
    },

    /** Filter blocks can't contain filters which search the content of the builds' results. */
    nonContentFilters() {
      return this.filterdata.availablefilters.filter((field) => !FILTER_DEFINITIONS[field]?.content);
    },
  },

  async mounted() {
    const params = queryParams(window.location.search);
    params.page_id = this.pageId ?? this.pageName;
    params.showlimit = 0;
    params.filterstring = filterString(window.location.href);

    const filterdata = await legacyRequest('GET', 'filterdata.php', { params });
    filterdata.filters = filterdata.filters.map((filter) => {
      if ('filters' in filter) {
        return withKey({
          ...filter,
          filters: filter.filters.map((subfilter) => withKey({ ...subfilter, compare: String(subfilter.compare) })),
        });
      }
      return withKey('compare' in filter ? { ...filter, compare: String(filter.compare) } : filter);
    });
    this.limit = filterdata.limit === undefined ? '' : String(filterdata.limit);
    this.filterdata = filterdata;
  },

  methods: {
    /**
     * Insert a copy of a filter after it.  A filter block's last filter is copied.
     */
    addFilter(filters, index) {
      const previous = filters[index];
      const copied = 'filters' in previous ? previous.filters[previous.filters.length - 1] : previous;
      filters.splice(index + 1, 0, withKey({
        field: copied.field,
        compare: copied.compare,
        value: copied.value,
      }));
    },

    /**
     * Insert a filter block after a filter, starting with a copy of the filter.
     */
    addFilterBlock(index) {
      const parent = this.filterdata.filters[index];
      this.filterdata.filters.splice(index + 1, 0, withKey({
        filters: [withKey({
          field: parent.field,
          compare: parent.compare,
          value: parent.value,
        })],
      }));
    },

    updateFilter(filter, changes) {
      Object.assign(filter, changes);
      if ('field' in changes) {
        // Reset the comparison if it can't be used with the new field.
        const comparisons = COMPARISONS[FILTER_DEFINITIONS[filter.field].type];
        if (!comparisons.some((comparison) => String(comparison.value) === String(filter.compare))) {
          filter.compare = '0';
        }
      }
    },

    /**
     * Filter blocks combine their filters the other way.
     */
    updateCombine(filtercombine) {
      this.filterdata.filtercombine = filtercombine;
      this.filterdata.othercombine = filtercombine === 'or' ? 'and' : 'or';
    },

    createHyperlink() {
      return filtersUrl({
        location: window.location,
        filters: this.filterdata.filters,
        filtercombine: this.filterdata.filtercombine,
        limit: this.limit,
        begin: this.begin,
        end: this.end,
      });
    },

    applyFilters() {
      window.location.href = this.createHyperlink();
    },

    clearFilters() {
      this.filterdata.filters = [];
      this.applyFilters();
    },
  },
};
</script>
