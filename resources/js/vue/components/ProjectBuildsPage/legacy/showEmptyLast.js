/**
 * angularOrderBy.js puts missing values first when sorting in descending order.  These keep rows
 * without results at the bottom of the project builds page's tables regardless of the sort order.
 */

function primarySortField(sortFields) {
  if (sortFields.length === 0) {
    return null;
  }
  const field = sortFields[0];
  return field.startsWith('-') ? field.substring(1) : field;
}

/**
 * Move expected builds which haven't submitted to the bottom, and above them the builds without
 * results for the section ("update", "configure", "compilation" or "test") being sorted by.
 *
 * @param {Object[]} builds Builds sorted by sortFields.
 * @param {string[]} sortFields
 * @returns {Object[]}
 */
export function showEmptyBuildsLast(builds, sortFields) {
  const expected = builds.filter((build) => 'expectedandmissing' in build);
  const submitted = builds.filter((build) => !('expectedandmissing' in build));

  const field = primarySortField(sortFields);
  // Only the sections' fields, e.g. "compilation.error", can be missing.
  if (field === null || !field.includes('.')) {
    return [...submitted, ...expected];
  }

  const section = field.substring(0, field.indexOf('.'));
  return [
    ...submitted.filter((build) => section in build),
    ...submitted.filter((build) => !(section in build)),
    ...expected,
  ];
}

const SUBPROJECT_SECTIONS = {
  configure: ['nconfigureerror', 'nconfigurewarning', 'nconfigurepass'],
  build: ['nbuilderror', 'nbuildwarning', 'nbuildpass'],
  test: ['ntestfail', 'ntestnotrun', 'ntestpass'],
};

function hasResults(subproject, fields) {
  return fields.some((field) => subproject[field] !== 0);
}

/**
 * @param {Object} subproject
 * @param {'configure'|'build'|'test'} section
 * @returns {boolean} Whether the SubProject has any results for a section.
 */
export function subProjectHasResults(subproject, section) {
  return hasResults(subproject, SUBPROJECT_SECTIONS[section]);
}

/**
 * Move SubProjects without any results to the bottom, and above them those without results for the
 * section being sorted by.  Sorting by name or start time doesn't move any SubProjects.
 *
 * @param {Object[]} subprojects SubProjects sorted by sortFields.
 * @param {string[]} sortFields
 * @returns {Object[]}
 */
export function showEmptySubProjectsLast(subprojects, sortFields) {
  const field = primarySortField(sortFields);
  const section = Object.values(SUBPROJECT_SECTIONS).find((fields) => fields.includes(field));
  if (section === undefined) {
    return subprojects;
  }

  const allFields = Object.values(SUBPROJECT_SECTIONS).flat();
  const empty = subprojects.filter((subproject) => !hasResults(subproject, allFields));
  const nonempty = subprojects.filter((subproject) => hasResults(subproject, allFields));
  return [
    ...nonempty.filter((subproject) => hasResults(subproject, section)),
    ...nonempty.filter((subproject) => !hasResults(subproject, section)),
    ...empty,
  ];
}
