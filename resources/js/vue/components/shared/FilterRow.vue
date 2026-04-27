<template>
  <div class="tw-flex tw-flex-row tw-w-full tw-gap-1">
    <button
      class="tw-btn tw-btn-xs"
      @click="$emit('delete')"
    >
      <font-awesome-icon :icon="FA.faTrash" /> Delete
    </button>
    <!-- Field chooser -->
    <select
      v-model="selectedField"
      class="tw-select tw-select-xs tw-select-bordered tw-shrink"
      @change="onFieldChange"
    >
      <option
        v-for="field in fields"
        :key="field.name"
        :value="field"
      >
        {{ field.name }}
      </option>
    </select>
    <!-- Operator chooser -->
    <select
      v-model="selectedOperator"
      class="tw-select tw-select-xs tw-select-bordered tw-shrink"
    >
      <option
        v-for="operator in selectedField.getOperators()"
        :value="operator"
      >
        {{ humanReadableOperator(operator) }}
      </option>
    </select>
    <!-- Value field -->
    <input
      v-if="selectedField.type === FilterType.NUMBER"
      v-model="selectedValue"
      type="number"
      class="tw-input tw-input-xs tw-input-bordered tw-shrink"
    >
    <input
      v-else-if="selectedField.type === FilterType.TEXT"
      v-model="selectedValue"
      type="text"
      class="tw-input tw-input-xs tw-input-bordered tw-w-full"
    >
    <date-time-selector
      v-else-if="selectedField.type === FilterType.DATETIME"
      v-model="selectedValue"
    />
    <select
      v-else-if="selectedField.type === FilterType.ENUM"
      v-model="selectedValue"
      class="tw-select tw-select-xs tw-select-bordered tw-shrink"
    >
      <option
        v-for="option in selectedField.getPossibleValues()"
        :key="option"
        :value="option"
      >
        {{ option }}
      </option>
    </select>
    <span v-else>ERROR: Unknown type</span>
  </div>
</template>

<script>
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {faTrash} from '@fortawesome/free-solid-svg-icons';
import DateTimeSelector from './DateTimeSelector.vue';
import {FilterField, FilterType} from './Filters/FilterUtils';

export default {
  components: {FontAwesomeIcon, DateTimeSelector},

  props: {
    fields: {
      /** @type Array<FilterField> */
      type: Array,
      required: true,
    },

    initialField: {
      type: FilterField,
      default: null,
    },

    initialOperator: {
      type: String,
      default: null,
    },

    initialValue: {
      type: null,
      default: null,
    },
  },

  emits: [
    'changeFilters',
    'delete',
  ],

  data() {
    return {
      selectedField: this.initialField,
      selectedOperator: this.initialOperator,
      selectedValue: this.initialValue ?? '',
    };
  },

  computed: {
    FilterType() {
      return FilterType;
    },

    FA() {
      return {
        faTrash,
      };
    },
  },

  watch: {
    'selectedField.loadedValues': {
      handler() {
        if (this.selectedField.type === FilterType.ENUM && (this.selectedValue === '' || this.selectedValue === null)) {
          this.selectedValue = this.selectedField.getPossibleValues()[0] || '';
        }
      },
      deep: true,
      immediate: true,
    },

    'selectedField': {
      handler(newField) {
        if (newField.type === FilterType.ENUM && (this.selectedValue === '' || this.selectedValue === null)) {
          this.selectedValue = newField.getPossibleValues()[0] || '';
        }
      },
      immediate: false,
    },

    selectedOperator() {
      this.emitChange();
    },

    selectedValue() {
      this.emitChange();
    },
  },

  methods: {
    onFieldChange() {
      if (this.selectedField.type === FilterType.ENUM) {
        this.selectedValue = this.selectedField.getPossibleValues()[0] || '';
      }
      else {
        this.selectedValue = '';
      }
      this.selectedOperator = this.selectedField.getOperators()[0];
      this.emitChange();
    },

    /**
     * Converts a GraphQL filter field operator to a more human-readable text string
     */
    humanReadableOperator(operator) {
      switch (operator) {
      case 'eq':
        return 'equal to';
      case 'ne':
        return 'not equal to';
      case 'lt':
        return 'less than';
      case 'gt':
        return 'greater than';
      default:
        return operator;
      }
    },

    /**
     * Emits the current value of this GraphQL filter entry
     */
    emitChange() {
      if (this.selectedField.type === FilterType.DATETIME && (this.selectedValue === '' || this.selectedValue === null)) {
        return;
      }
      this.$emit('changeFilters', this.selectedField.getFilter(this.selectedValue, this.selectedOperator));
    },
  },
};
</script>
