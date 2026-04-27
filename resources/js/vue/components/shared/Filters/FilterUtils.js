
import gql from 'graphql-tag';

import {reactive} from 'vue';

export const FilterType = Object.freeze({
  TEXT: 'text',
  NUMBER: 'number',
  DATETIME: 'datetime',
  ENUM: 'enum',
});

export const getEnumValues = (apolloClient, enumName) => {
  const query = gql`
    query GetEnumValues($name: String!) {
      __type(name: $name) {
        enumValues {
          name
        }
      }
    }
  `;
  return apolloClient.query({
    query,
    variables: { name: enumName },
  }).then(result => {
    return result.data.__type.enumValues.map(enumValue => enumValue.name);
  });
};

export class FilterField {
  /**
   * @param {String} name
   * @param {FilterType} type
   * @param {function|null} values A function which lazily loads the set of possible values for this field.
   */
  constructor(name, type, values) {
    this.name = name;
    this.type = type;
    this.values = values;
    this.loadedValues = reactive([]);
    if (this.type === FilterType.ENUM && typeof this.values === 'function') {
      const result = this.values();
      if (result instanceof Promise) {
        result.then(v => {
          this.loadedValues.splice(0, this.loadedValues.length, ...v);
        });
      }
      else {
        this.loadedValues.splice(0, this.loadedValues.length, ...result);
      }
    }
  }

  getOperators() {
    switch (this.type) {
    case FilterType.TEXT:
      return [
        'eq',
        'ne',
        'contains',
      ];
    case FilterType.NUMBER:
      return [
        'eq',
        'ne',
        'gt',
        'lt',
      ];
    case FilterType.DATETIME:
      return [
        'eq',
        'ne',
        'gt',
        'lt',
      ];
    case FilterType.ENUM:
      return [
        'eq',
        'ne',
      ];
    default:
      return [];
    }
  }

  /**
   * @return {Array}
   */
  getPossibleValues() {
    return this.loadedValues;
  }

  /**
   * @param {*} data
   * @param {String} operator
   * @return Object
   */
  getFilter(data, operator) { // eslint-disable-line no-unused-vars
    throw 'Method getFilter not implemented for abstract FilterField class.';
  }

  /**
   * @param {Object} filter
   * @return boolean
   */
  isMatch(filter) { // eslint-disable-line no-unused-vars
    throw 'Method isMatch not implemented for abstract FilterField class.';
  }

  /**
   * This method assumes that the input filter has already been validated by isMatch().
   *
   * @param {Object} filter
   * @return {*}
   */
  getValueFromFilter(filter) { // eslint-disable-line no-unused-vars
    throw 'Method getValueFromFilter not implemented for abstract FilterField class.';
  }

  /**
   * This method assumes that the input filter has already been validated by isMatch().
   *
   * @param {Object} filter
   * @return {*}
   */
  getOperatorFromFilter(filter) { // eslint-disable-line no-unused-vars
    throw 'Method getOperatorFromFilter not implemented for abstract FilterField class.';
  }
}

export class BasicFilterField extends FilterField {
  /**
   * @param {String} name
   * @param {FilterType} type
   * @param {function} values
   * @param {String} field
   */
  constructor(name, type, values, field) {
    super(name, type, values);
    this.field = field;
  }

  /**
   * @param {*} data
   * @param {String} operator
   * @return Object
   */
  getFilter(data, operator) {
    return {
      [operator]: {
        [this.field]: data,
      },
    };
  }

  /**
   * @param {Object} filter
   * @return boolean
   */
  isMatch(filter) {
    const operator = Object.keys(filter)[0];
    return operator && this.field in filter[operator];
  }

  /**
   * @param {Object} filter
   * @return {*}
   */
  getValueFromFilter(filter) {
    const operator = Object.keys(filter)[0];
    return filter[operator][this.field];
  }

  /**
   * @param {Object} filter
   * @return {String}
   */
  getOperatorFromFilter(filter) {
    return Object.keys(filter)[0];
  }
}

