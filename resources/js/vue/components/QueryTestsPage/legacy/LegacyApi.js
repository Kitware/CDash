import { legacyRequest } from '../../shared/legacy/legacyRequest';

/**
 * The legacy JSON API which the query tests page uses.  Every request the page makes goes through here,
 * apart from the shared legacy filters', so it can be replaced with its GraphQL equivalent on its own.
 */

export default {
  /**
   * The tests to show.
   *
   * @param {Object<string,string>} params The page's query parameters, plus `filterstring`.
   */
  queryTests(params) {
    return legacyRequest('GET', 'queryTests.php', { params });
  },
};
