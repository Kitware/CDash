import { legacyRequest } from '../../shared/legacy/legacyRequest';

/**
 * The legacy JSON APIs which the project builds page uses.  Every request the page makes goes through
 * here, apart from the shared legacy filters' and timeline's, so each one can be replaced with its
 * GraphQL equivalent on its own.
 */

export default {
  /**
   * The builds, coverage and dynamic analysis to show.
   *
   * @param {Object<string,string>} params The page's query parameters, plus `filterstring`.
   */
  index(params) {
    return legacyRequest('GET', 'index.php', { params });
  },

  /**
   * When a build's errors and failing tests started.
   *
   * @param {number} buildid
   */
  buildProblems(buildid) {
    return legacyRequest('GET', 'build.php', { params: { buildid, getproblems: 1 } });
  },

  /**
   * When an expected build last submitted.
   *
   * @param {{siteid: number, groupid: number, name: string, type: string, currenttime: number}} params
   */
  expectedBuildInfo(params) {
    return legacyRequest('GET', 'expectedbuild.php', { params });
  },

  /**
   * @param {number} buildid
   * @returns {Promise<number>} 1 if the build is expected to submit every day, 0 otherwise.
   */
  async isBuildExpected(buildid) {
    const response = await legacyRequest('GET', 'is_build_expected.php', { params: { buildid } });
    return Number(response.expected);
  },

  setBuildDone(buildid, done) {
    return legacyRequest('POST', 'build.php', { body: { buildid, done } });
  },

  setBuildExpected(buildid, groupid, expected) {
    return legacyRequest('POST', 'build.php', { body: { buildid, groupid, expected } });
  },

  moveBuild(buildid, newgroupid, expected) {
    return legacyRequest('POST', 'build.php', { body: { buildid, newgroupid, expected } });
  },

  deleteBuild(buildid) {
    return legacyRequest('DELETE', 'build.php', { params: { buildid } });
  },

  /**
   * Stop expecting a build which hasn't submitted.
   *
   * @param {{siteid: number, groupid: number, name: string, type: string}} params
   */
  deleteExpectedBuild(params) {
    return legacyRequest('DELETE', 'expectedbuild.php', { params });
  },

  /**
   * Expect a build which hasn't submitted in another group instead.
   *
   * @param {{siteid: number, groupid: number, newgroupid: number, name: string, type: string}} body
   */
  moveExpectedBuild(body) {
    return legacyRequest('POST', 'expectedbuild.php', { body });
  },
};
