/**
 * Sorts the legacy pages' tables.  Users' saved sort orders rely on its behavior, which differs from a
 * plain sort:
 *  - Strings are compared case-insensitively, but otherwise with `<` rather than localeCompare().
 *  - Values of different types are ordered by the name of their type ('boolean' < 'number' < 'string'),
 *    followed by null and then undefined.  A descending sort reverses that order too, so missing values
 *    come first.  ProjectBuildsPage/legacy/showEmptyLast.js moves them back to the end.
 *  - Ties keep the order of the input.
 *
 * Remove this once the pages' data comes from GraphQL and can be sorted by the server.
 */

function isPrimitive(value) {
  return ['number', 'boolean', 'string'].includes(typeof value);
}

function objectValue(value) {
  if (typeof value.valueOf === 'function') {
    value = value.valueOf();
    if (isPrimitive(value)) {
      return value;
    }
  }
  if (typeof value.toString === 'function' && value.toString !== Object.prototype.toString) {
    value = value.toString();
    if (isPrimitive(value)) {
      return value;
    }
  }
  return value;
}

function predicateValue(value, index) {
  let type = typeof value;
  if (value === null) {
    type = 'null';
  } else if (type === 'object') {
    value = objectValue(value);
  }
  return { value, type, index };
}

function compare(v1, v2) {
  if (v1.type !== v2.type) {
    if (v1.type === 'undefined') {
      return 1;
    }
    if (v2.type === 'undefined') {
      return -1;
    }
    if (v1.type === 'null') {
      return 1;
    }
    if (v2.type === 'null') {
      return -1;
    }
    return v1.type < v2.type ? -1 : 1;
  }

  let value1 = v1.value;
  let value2 = v2.value;
  if (v1.type === 'string') {
    value1 = value1.toLowerCase();
    value2 = value2.toLowerCase();
  } else if (v1.type === 'object') {
    // Plain objects are ordered by their position in the input.
    if (value1 !== null && typeof value1 === 'object') {
      value1 = v1.index;
    }
    if (value2 !== null && typeof value2 === 'object') {
      value2 = v2.index;
    }
  }

  if (value1 === value2) {
    return 0;
  }
  return value1 < value2 ? -1 : 1;
}

/**
 * The value of a property path such as "compilation.error" or "measurements[2]", or undefined if part of
 * it is missing.
 */
function propertyAt(object, path) {
  return path.replace(/\[(\d+)\]/g, '.$1').split('.')
    .reduce((value, key) => (value === null || value === undefined ? undefined : value[key]), object);
}

/**
 * Sort an array by one or more properties.
 *
 * @param {Array} array
 * @param {string|string[]} predicates Property paths, e.g. "compilation.error" or "measurements[2]", each
 *   optionally prefixed with '-' to sort in descending order or '+' to sort in ascending order.
 * @returns {Array} A sorted copy of the array.
 */
export default function orderBy(array, predicates) {
  if (!Array.isArray(predicates)) {
    predicates = [predicates];
  }
  if (predicates.length === 0) {
    predicates = ['+'];
  }

  const parsed = predicates.map((predicate) => {
    let descending = 1;
    if (predicate.startsWith('+') || predicate.startsWith('-')) {
      descending = predicate.startsWith('-') ? -1 : 1;
      predicate = predicate.substring(1);
    }
    const get = predicate === '' ? (value) => value : (value) => propertyAt(value, predicate);
    return { get, descending };
  });

  return array
    .map((value, index) => ({
      value,
      index,
      predicateValues: parsed.map(({ get }) => predicateValue(get(value), index)),
    }))
    .sort((a, b) => {
      for (let i = 0; i < parsed.length; i++) {
        const result = compare(a.predicateValues[i], b.predicateValues[i]);
        if (result !== 0) {
          return result * parsed[i].descending;
        }
      }
      return a.index - b.index;
    })
    .map(({ value }) => value);
}
