import { fetchWithCsrfToken } from '../CsrfFetch';

/**
 * Requests to the legacy JSON APIs in api/v1.  The URLs are relative, since the pages which use these
 * APIs are at the root of CDash.
 *
 * Remove this once the pages which use it get their data from GraphQL.
 */

export class LegacyApiError extends Error {
  /**
   * @param {number} status
   * @param {Object} data The response's JSON, if any.
   */
  constructor(status, data) {
    super(data?.error ?? `Request failed with status ${status}`);
    this.status = status;
    this.data = data;
  }
}

/**
 * @param {string} method
 * @param {string} endpoint e.g. "filterdata.php"
 * @param {Object} options
 * @param {Object<string,*>} [options.params] The query parameters.
 * @param {Object} [options.body] Sent as JSON.
 * @returns {Promise<Object>} The response's JSON, or an empty object if it doesn't have any.
 */
export async function legacyRequest(method, endpoint, { params, body } = {}) {
  const query = params === undefined ? '' : `?${new URLSearchParams(params)}`;
  const response = await fetchWithCsrfToken(`api/v1/${endpoint}${query}`, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  let data = {};
  try {
    data = await response.json();
  } catch {
    // Some endpoints respond without a body.
  }
  if (!response.ok) {
    throw new LegacyApiError(response.status, data);
  }
  return data;
}

/**
 * The page's query string as an object.  Later parameters replace earlier ones with the same name.
 *
 * @param {string} search e.g. "?project=CDash&date=2025-01-15"
 * @returns {Object<string,string>}
 */
export function queryParams(search) {
  return Object.fromEntries(new URLSearchParams(search));
}
