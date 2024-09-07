<template>
  <loading-indicator :is-loading="!result">
    <div class="flex flex-row w-full gap-1">
      <button
        class="btn btn-xs"
        @click="$emit('delete')"
      >
        <font-awesome-icon icon="fa-trash" />
      </button>
      <!-- Field chooser -->
      <select
        v-model="selectedField"
        class="select select-xs select-bordered shrink"
      >
        <option
          v-for="field in result.typeInformation.inputFields"
          :value="field.name"
        >
          {{ humanReadableField(field.name) }}
        </option>
      </select>
      <!-- Operator chooser -->
      <select
        v-if="selectedField !== ''"
        v-model="selectedOperator"
        class="select select-xs select-bordered shrink"
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
          v-if="selectedType.name === 'ID' || selectedType.name === 'Int' || selectedType.name === 'Float'"
          v-model="selectedValue"
          type="number"
          class="input input-xs input-bordered shrink"
        >
        <input
          v-else-if="selectedType.name === 'String'"
          v-model="selectedValue"
          type="text"
          class="input input-xs input-bordered w-full"
        >
        <span v-else-if="selectedType.name === 'Boolean'">
          <!-- TODO: Implement -->
        </span>
        <span v-else-if="selectedType.name === 'DateTimeTz'">
          <!-- TODO: Implement -->
        </span>
        <span v-else>ERROR: Unknown type</span>
      </template>
      <select
        v-else-if="selectedType.kind === 'ENUM'"
        v-model="selectedValue"
        class="select select-xs select-bordered shrink"
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

export default {
  components: {FontAwesomeIcon, LoadingIndicator},

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

  setup(props) {
    const { result, error } = useQuery(gql`
      query {
        typeInformation: __type(name: "${props.type}") {
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
    `);

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

        this.selectedField = this.initialField ?? result.typeInformation.inputFields[0].name;

        this.selectedOperator = this.initialOperator ?? 'eq';
      },
      immediate: true,
    },

    selectedType: {
      handler(type) {
        if (this.selectedValue === null && this.initialValue !== null) {
          this.selectedValue = this.initialValue;
        }
        else {
          if (type.kind === 'ENUM') {
            this.selectedValue = type.enumValues[0].name;
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
      return field;
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
  },
};
</script>
