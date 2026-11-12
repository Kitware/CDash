import { toRaw } from 'vue';

const keys = new WeakMap();
let nextKey = 0;

/**
 * A v-for key which identifies a row by its object.  The legacy index API can list a build more than
 * once, e.g. in its build group and in a dynamic group, so builds' IDs aren't unique keys.
 *
 * @param {Object} row
 * @returns {number}
 */
export function rowKey(row) {
  const raw = toRaw(row);
  if (!keys.has(raw)) {
    keys.set(raw, nextKey++);
  }
  return keys.get(raw);
}
