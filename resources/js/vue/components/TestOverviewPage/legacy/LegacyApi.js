import { legacyRequest } from '../../shared/legacy/legacyRequest';

/**
 * The legacy JSON API which the test overview page uses.  Every request the page makes goes through here,
 * apart from the shared legacy filters' and timeline's, so it can be replaced with its GraphQL equivalent
 * on its own.
 */

export default {
  /**
   * How often each test failed during the page's days.
   *
   * @param {Object<string,string>} params The page's query parameters, plus `filterstring`.
   */
  testOverview(params) {
    return legacyRequest('GET', 'testOverview.php', { params });
  },
};
