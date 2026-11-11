/**
 * Read and write cookies in the jquery.cookie plugin's format, so the settings users have already saved
 * are read correctly.  Names and values are URI-encoded, arrays are saved as comma-separated lists, and
 * no path is set, so the browser uses the page's directory.
 *
 * Remove this once the pages' settings are stored somewhere else.
 */

/**
 * @param {string} name
 * @returns {string|undefined}
 */
export function getCookie(name) {
  const cookies = document.cookie ? document.cookie.split('; ') : [];
  for (const cookie of cookies) {
    const parts = cookie.split('=');
    if (decodeURIComponent(parts.shift()) !== name) {
      continue;
    }
    let value = parts.join('=');
    if (value.startsWith('"')) {
      value = value.slice(1, -1).replace(/\\"/g, '"').replace(/\\\\/g, '\\');
    }
    try {
      return decodeURIComponent(value.replace(/\+/g, ' '));
    } catch {
      return undefined;
    }
  }
  return undefined;
}

/**
 * @param {string} name
 * @param {*} value Converted to a string.
 * @param {{expires?: number}} options The number of days to keep the cookie for.  By default, it lasts
 *   until the browser is closed.
 */
export function setCookie(name, value, { expires } = {}) {
  let cookie = `${encodeURIComponent(name)}=${encodeURIComponent(String(value))}`;
  if (expires !== undefined) {
    cookie += `; expires=${new Date(Date.now() + (expires * 864e5)).toUTCString()}`;
  }
  document.cookie = cookie;
}

/**
 * @param {string} name
 */
export function removeCookie(name) {
  setCookie(name, '', { expires: -1 });
}
