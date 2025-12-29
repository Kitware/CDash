<template>
  <loading-indicator :is-loading="!result">
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
      >
        <option
          v-for="field in result.typeInformation.inputFields.filter((x) => x.name !== 'id')"
          :value="field.name"
        >
          {{ humanReadableField(field.name) }}
        </option>
      </select>
      <!-- Operator chooser -->
      <select
        v-if="selectedField !== ''"
        v-model="selectedOperator"
        class="tw-select tw-select-xs tw-select-bordered tw-shrink"
      >
        <option
          v-for="operator in operators"
          :value="operator"
        >
          {{ humanReadableOperator(operator) }}
        </option>
      </select>
      <!-- Value field -->
      <template v-if="selectedType.kind === 'SCALAR'">
        <input
          v-if="typeCategory(selectedType.name) === 'NUMBER'"
          v-model="selectedValue"
          type="number"
          class="tw-input tw-input-xs tw-input-bordered tw-shrink"
        >
        <input
          v-else-if="typeCategory(selectedType.name) === 'STRING'"
          v-model="selectedValue"
          type="text"
          class="tw-input tw-input-xs tw-input-bordered tw-w-full"
        >
        <span v-else-if="typeCategory(selectedType.name) === 'BOOLEAN'">
          <!-- TODO: Implement -->
        </span>
        <date-time-selector
          v-else-if="typeCategory(selectedType.name) === 'DATE'"
          v-model="selectedValue"
        />
        <span v-else>ERROR: Unknown type</span>
      </template>
      <select
        v-else-if="selectedType.kind === 'ENUM'"
        v-model="selectedValue"
        class="tw-select tw-select-xs tw-select-bordered tw-shrink"
      >
        <option
          v-for="option in selectedType.enumValues"
          :value="option.name"
        >
          {{ option.name }}
        </option>
      </select>
    </div>
  </loading-indicator>
</template>

<script>
import {useQuery} from '@vue/apollo-composable';
import gql from 'graphql-tag';
import LoadingIndicator from './LoadingIndicator.vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {faTrash} from '@fortawesome/free-solid-svg-icons';
import DateTimeSelector from './DateTimeSelector.vue';
import { DateTime } from 'luxon';

export default {
  components: {FontAwesomeIcon, LoadingIndicator, DateTimeSelector},

  props: {
    type: {
      type: String,
      required: true,
    },

    /**
     * An array of GraphQL operators, as determined by API introspection.
     * This template overrides some of these values for UI reasons.
     *
     * TODO: We currently assume that every field has the same operators.
     * this could be improved in the future by intelligently populating this
     * list based on the operators each field appears under.
     */
    operators: {
      type: Array,
      required: true,
    },

    initialField: {
      type: String,
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

  setup(props) {
    const { result, error } = useQuery(gql`
      query($typeName: String!){
        typeInformation: __type(name: $typeName) {
          inputFields {
            name
            type {
              name
              kind
              enumValues {
                name
              }
            }
          }
        }
      }
    `, {
      typeName: props.type,
    });

    return {
      result,
      error,
    };
  },

  data() {
    return {
      // selectedType is derived from the selected field
      selectedField: null,
      selectedOperator: null,
      selectedValue: null,
    };
  },

  computed: {
    FA() {
      return {
        faTrash,
      };
    },

    selectedType() {
      return this.result?.typeInformation.inputFields.filter(field => field.name === this.selectedField)[0].type;
    },
  },

  watch: {
    result: {
      handler(result) {
        // Do nothing if we haven't loaded data from the server yet
        if (!result) {
          return;
        }

        this.selectedField = this.initialField ?? result.typeInformation.inputFields.filter((x) => x.name !== 'id')[0].name;

        this.selectedOperator = this.initialOperator ?? 'eq';
      },
      immediate: true,
    },

    selectedType: {
      handler(type) {
        if (this.selectedValue === null && this.initialValue !== null) {
          this.selectedValue = this.initialValue;
          if (this.typeCategory(type.name) === 'DATE') {
            this.selectedValue = DateTime.fromISO(this.selectedValue, {setZone: true});
          }
        }
        else {
          if (type.kind === 'ENUM') {
            this.selectedValue = type.enumValues[0].name;
          }
          else if (this.typeCategory(type.name) === 'DATE') {
            this.selectedValue = DateTime.now().toUTC();
          }
          else {
            this.selectedValue = '';
          }
        }
      },
    },

    selectedField() {
      this.emitChange();
    },

    selectedOperator() {
      this.emitChange();
    },

    selectedValue() {
      this.emitChange();
    },
  },

  methods: {
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
     * Converts a GraphQL field to a human-readable equivalent
     */
    humanReadableField(field) {
      const result = field.replace(/([A-Z])/g, ' $1');
      return result.charAt(0).toUpperCase() + result.slice(1);
    },

    /**
     * Emits the current value of this GraphQL filter entry
     */
    emitChange() {
      this.$emit('changeFilters', {
        [this.selectedOperator]: {
          [this.selectedField]: this.selectedValue,
        },
      });
    },

    /**
     * Accepts a GraphQL type and returns the type of field to display
     *
     * Valid return values: STRING, NUMBER, DATE, BOOLEAN
     */
    typeCategory(typename) {
      switch (typename) {
      case 'ID':
        return 'NUMBER';
      case 'Integer':
        return 'NUMBER';
      case 'Float':
        return 'NUMBER';
      case 'NonNegativeSeconds':
        return 'NUMBER';
      case 'NonNegativeIntegerMilliseconds':
        return 'NUMBER';
      case 'String':
        return 'STRING';
      case 'DateTimeTz':
        return 'DATE';
      case 'DateTimeUtc':
        return 'DATE';
      case 'Boolean':
        return 'BOOLEAN';
      default:
        return 'UNKNOWN';
      }
    },
  },
};
</script>
