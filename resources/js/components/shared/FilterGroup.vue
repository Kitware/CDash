<template>
  <loading-indicator :is-loading="!result">
    <div class="tw-flex tw-w-full">
      <div class="tw-divider tw-divider-horizontal" />
      <div class="tw-flex tw-flex-col tw-w-full tw-gap-1">
        <div>
          <template v-if="primaryRecordName === ''">
            and
          </template>
          <template v-else>
            Show all {{ primaryRecordName }} where
          </template>
          <select
            class="tw-select tw-select-xs tw-select-bordered tw-shrink"
            @change="event => changeCombineType(event.target.value)"
          >
            <option
              value="all"
              :selected="currentCombineType === 'all'"
            >
              all
            </option>
            <option
              value="any"
              :selected="currentCombineType === 'any'"
            >
              any
            </option>
          </select> of the following are true
        </div>
        <div v-for="(entry, index) in filters[currentCombineType]">
          <filter-group
            v-if="entry !== 'deleted' && (entry.hasOwnProperty('any') || entry.hasOwnProperty('all'))"
            :initial-filters="entry"
            :type="type"
            @delete="changeRow('deleted', index)"
            @changeFilters="newEntry => changeRow(newEntry, index)"
          />
          <filter-row
            v-else-if="entry !== 'deleted'"
            :operators="result.typeInformation.inputFields.filter(f => f.type.kind !== 'LIST').map(o => o.name)"
            :type="result.typeInformation.inputFields.filter(f => f.type.kind !== 'LIST')[0].type.name"
            :initial-field="filterToFilterRow(entry).field"
            :initial-operator="filterToFilterRow(entry).operator"
            :initial-value="filterToFilterRow(entry).value"
            @delete="changeRow('deleted', index)"
            @changeFilters="newEntry => changeRow(newEntry, index)"
          />
        </div>
        <div class="tw-flex tw-flex-row tw-w-full tw-gap-1">
          <button
            class="tw-btn tw-btn-xs"
            @click="addFilter"
          >
            <font-awesome-icon icon="fa-plus" /> Add Filter
          </button>
          <button
            class="tw-btn tw-btn-xs"
            @click="addGroup"
          >
            <font-awesome-icon icon="fa-bars-staggered" /> Add Group
          </button>
          <button
            class="tw-btn tw-btn-xs"
            @click="$emit('delete')"
          >
            <font-awesome-icon icon="fa-trash" /> Delete
          </button>
        </div>
      </div>
    </div>
  </loading-indicator>
</template>

<script>
import LoadingIndicator from './LoadingIndicator.vue';
import {useQuery} from '@vue/apollo-composable';
import gql from 'graphql-tag';
import FilterRow from './FilterRow.vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';

export default {
  components: { FontAwesomeIcon, FilterRow, LoadingIndicator },

  props: {
    type: {
      type: String,
      default: '',
    },

    /**
     * A human-readable string to provide context for the plural type of record being filtered.
     * This is expected to only be set for the top level of the recursive hierarchy.
     *
     * For example: "test measurements", "builds", "tests", etc...
     */
    primaryRecordName: {
      type: String,
      required: false,
      default: '',
    },

    initialFilters: {
      type: Object,
      required: true,
    },
  },

  setup(props) {
    const { result, error } = useQuery(gql`
      query {
        typeInformation: __type(name: "${props.type}") {
          inputFields {
            name
            type {
              name
              kind
              ofType {
                name
              }
            }
          }
        }
      }
    `);

    return {
      result,
      error,
    };
  },

  data() {
    return {
      filters: JSON.parse(JSON.stringify(this.initialFilters)),
    };
  },

  computed: {
    currentCombineType() {
      return this.filters.hasOwnProperty('any') ? 'any' : 'all';
    },
  },

  methods: {
    addGroup() {
      this.filters[this.currentCombineType].push({
        all: [
          {},
        ],
      });
    },

    addFilter() {
      this.filters[this.currentCombineType].push({});
    },

    filterToFilterRow(filter) {
      if (Object.keys(filter).length > 0 && Object.keys(Object.values(filter)[0]).length > 0) {
        return {
          field: Object.keys(Object.values(filter)[0])[0],
          operator: Object.keys(filter)[0],
          value: Object.values(Object.values(filter)[0])[0],
        };
      }
      else {
        return {
          field: null,
          operator: null,
          value: null,
        };
      }
    },

    changeCombineType(newCombineType) {
      const originalCombinetype = this.currentCombineType;
      this.filters[newCombineType] = this.filters[originalCombinetype];
      delete this.filters[originalCombinetype];
      this.emitChange();
    },

    changeRow(newRow, index) {
      this.filters[this.currentCombineType][index] = newRow;
      this.emitChange();
    },

    emitChange() {
      this.$emit('changeFilters', JSON.parse(JSON.stringify({
        [this.currentCombineType]: this.filters[this.currentCombineType].filter(r => r !== 'deleted'),
      })));
    },
  },
};
</script>
