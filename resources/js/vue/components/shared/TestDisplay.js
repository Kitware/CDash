/**
 * @param {string} pattern
 * @returns {RegExp|null}
 */
export function patternToRegExp(pattern) {
  const trimmed = pattern.trim();
  if (trimmed === '') {
    return null;
  }

  if (trimmed.startsWith('/')) {
    const lastSlash = trimmed.lastIndexOf('/');
    if (lastSlash > 0) {
      const body = trimmed.slice(1, lastSlash);
      const flags = trimmed.slice(lastSlash + 1);
      try {
        return new RegExp(body, flags);
      } catch {
        return null;
      }
    }
  }

  let regexBody = '';
  for (const part of trimmed.split(/([*?])/)) {
    if (part === '*') {
      regexBody += '.*';
    } else if (part === '?') {
      regexBody += '.';
    } else {
      regexBody += part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
  }

  try {
    return new RegExp(regexBody, 'i');
  } catch {
    return null;
  }
}

/**
 * @param {string} details
 * @param {string} patternsText
 */
export function detailsMatchesSkippedPattern(details, patternsText) {
  if (!details || !patternsText?.trim()) {
    return false;
  }

  return patternsText
    .split(/\r\n|\n|\r/)
    .map((line) => line.trim())
    .filter((line) => line.length > 0)
    .some((pattern) => {
      const regexp = patternToRegExp(pattern);
      return regexp !== null && regexp.test(details);
    });
}

/**
 * @param {string} status GraphQL TestStatus enum value.
 * @param {string} details
 * @param {string} patternsText
 */
export function testStatusToColorClass(status, details = '', patternsText = '') {
  if (status === 'NOT_RUN' && detailsMatchesSkippedPattern(details, patternsText)) {
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
 * @param {string} patternsText
 */
export function testStatusToTextColorClass(status, details = '', patternsText = '') {
  switch (testStatusToColorClass(status, details, patternsText)) {
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
