/**
 * CTest sets Details="Disabled" for tests marked DISABLED.
 */
export const DISABLED_DETAILS = 'Disabled';

/**
 * @param {string|null|undefined} details
 * @returns {boolean}
 */
export function isAcceptableNotRun(details) {
  return details === DISABLED_DETAILS;
}

/**
 * @param {string} status GraphQL TestStatus enum value.
 * @param {string} details
 */
export function testStatusToColorClass(status, details = '') {
  if (status === 'NOT_RUN' && isAcceptableNotRun(details)) {
    return 'normal';
  }

  switch (status) {
  case 'PASSED':
    return 'normal';
  case 'FAILED':
    return 'error';
  case 'NOT_RUN':
    return 'warning';
  default:
    return '';
  }
}

/**
 * @param {string} status
 * @param {string} details
 */
export function testStatusToTextColorClass(status, details = '') {
  switch (testStatusToColorClass(status, details)) {
  case 'normal':
    return 'normal-text';
  case 'warning':
    return 'warning-text';
  case 'error':
    return 'error-text';
  default:
    return '';
  }
}
