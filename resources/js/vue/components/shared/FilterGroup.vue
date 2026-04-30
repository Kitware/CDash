<template>
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
          @change-filters="newEntry => changeRow(newEntry, index)"
        />
        <filter-row
          v-else-if="entry !== 'deleted'"
          :fields="availableFields"
          :initial-field="filterRowFromGraphQLFilter(entry).field"
          :initial-operator="filterRowFromGraphQLFilter(entry).operator"
          :initial-value="filterRowFromGraphQLFilter(entry).value"
          @delete="changeRow('deleted', index)"
          @change-filters="newEntry => changeRow(newEntry, index)"
        />
      </div>
      <div class="tw-flex tw-flex-row tw-w-full tw-gap-1">
        <button
          class="tw-btn tw-btn-xs"
          @click="addFilter"
        >
          <font-awesome-icon :icon="FA.faPlus" /> Add Filter
        </button>
        <button
          class="tw-btn tw-btn-xs"
          @click="addGroup"
        >
          <font-awesome-icon :icon="FA.faBarsStaggered" /> Add Group
        </button>
        <button
          class="tw-btn tw-btn-xs"
          @click="$emit('delete')"
        >
          <font-awesome-icon :icon="FA.faTrash" /> Delete Group
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import FilterRow from './FilterRow.vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {
  faPlus,
  faBarsStaggered,
  faTrash,
} from '@fortawesome/free-solid-svg-icons';
import {BasicFilterField, FilterType, getEnumValues, RelationshipFilterField} from './Filters/FilterUtils';

const AVAILABLE_FILTERS = Object.freeze({
  BuildTestsFiltersMultiFilterInput: (apolloClient) => [
    new BasicFilterField('Name', FilterType.TEXT, null, 'name'),
    new BasicFilterField('Details', FilterType.TEXT, null, 'details'),
    new BasicFilterField('Running Time', FilterType.NUMBER, null, 'runningTime'),
    new BasicFilterField('Start Time', FilterType.DATETIME, null, 'startTime'),
    new BasicFilterField('Status', FilterType.ENUM, () => getEnumValues(apolloClient, 'TestStatus'), 'status'),
    new BasicFilterField('Time Status', FilterType.ENUM, () => getEnumValues(apolloClient, 'TestTimeStatusCategory'), 'timeStatusCategory'),
    new RelationshipFilterField('Label', FilterType.TEXT, null, 'text', 'labels'),
  ],
  BuildCoverageFiltersMultiFilterInput: () => [
    new BasicFilterField('Lines of Code Tested', FilterType.NUMBER, null, 'linesOfCodeTested'),
    new BasicFilterField('Lines of Code Untested', FilterType.NUMBER, null, 'linesOfCodeUntested'),
    new BasicFilterField('Line Percentage', FilterType.NUMBER, null, 'linePercentage'),
    new BasicFilterField('Branches Tested', FilterType.NUMBER, null, 'branchesTested'),
    new BasicFilterField('Branches Untested', FilterType.NUMBER, null, 'branchesUntested'),
    new BasicFilterField('Branch Percentage', FilterType.NUMBER, null, 'branchPercentage'),
    new BasicFilterField('Functions Tested', FilterType.NUMBER, null, 'functionsTested'),
    new BasicFilterField('Functions Untested', FilterType.NUMBER, null, 'functionsUntested'),
    new BasicFilterField('Function Percentage', FilterType.NUMBER, null, 'functionPercentage'),
    new BasicFilterField('File Path', FilterType.TEXT, null, 'filePath'),
    new BasicFilterField('File', FilterType.TEXT, null, 'file'),
  ],
  BuildCommandsFiltersMultiFilterInput: (apolloClient) => [
    new BasicFilterField('Type', FilterType.ENUM, () => getEnumValues(apolloClient, 'BuildCommandType'), 'type'),
    new BasicFilterField('Start Time', FilterType.DATETIME, null, 'startTime'),
    new BasicFilterField('Duration', FilterType.NUMBER, null, 'duration'),
    new BasicFilterField('Command', FilterType.TEXT, null, 'command'),
    new BasicFilterField('Working Directory', FilterType.TEXT, null, 'workingDirectory'),
    new BasicFilterField('Result', FilterType.TEXT, null, 'result'),
    new BasicFilterField('Source', FilterType.TEXT, null, 'source'),
    new BasicFilterField('Language', FilterType.TEXT, null, 'language'),
    new BasicFilterField('Config', FilterType.TEXT, null, 'config'),
  ],
  BuildTargetsFiltersMultiFilterInput: (apolloClient) => [
    new BasicFilterField('Name', FilterType.TEXT, null, 'name'),
    new BasicFilterField('Type', FilterType.ENUM, () => getEnumValues(apolloClient, 'TargetType'), 'type'),
  ],
});

export default {
  components: { FontAwesomeIcon, FilterRow },

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

  emits: [
    'changeFilters',
    'delete',
  ],

  data() {
    return {
      filters: JSON.parse(JSON.stringify(this.initialFilters)),
      availableFields: AVAILABLE_FILTERS[this.type](this.$apollo.provider.defaultClient),
    };
  },

  computed: {
    FA() {
      return {
        faPlus,
        faBarsStaggered,
        faTrash,
      };
    },

    currentCombineType() {
      return 'any' in this.filters ? 'any' : 'all';
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

    filterRowFromGraphQLFilter(filter) {
      const filterField = this.availableFields.find(field => field.isMatch(filter));

      if (filterField) {
        return {
          field: filterField,
          operator: filterField.getOperatorFromFilter(filter),
          value: filterField.getValueFromFilter(filter),
        };
      }
      else {
        const field = this.availableFields[0];

        return {
          field: field,
          operator: 'eq',
          value: '',
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
