/**
 * Combine URL components, ensuring that trailing slashes are stripped before concatenation.
 *
 * @param {...String} components
 */
function makeUrlFromComponents(...components) {
  return components.map(part => part.replace(/\/+$/, '')).join('/');
}

export class Repository {
  constructor(repositoryUrl) {
    this.repositoryUrl = repositoryUrl;
  }

  /**
   * @param {String} commit
   * @return String
   */
  getCommitUrl(commit) { // eslint-disable-line no-unused-vars
    throw new Error('Method not implemented for abstract Repository class.');
  }

  /**
   * @param {String} commit1
   * @param {String} commit2
   * @return String
   */
  getComparisonUrl(commit1, commit2) { // eslint-disable-line no-unused-vars
    throw new Error('Method not implemented for abstract Repository class.');
  }

  /**
   * @param {String} commit
   * @param {String} path
   * @return String
   */
  getFileUrl(commit, path) { // eslint-disable-line no-unused-vars
    throw new Error('Method not implemented for abstract Repository class.');
  }
}

export class GitHub extends Repository {
  getCommitUrl(commit) {
    return makeUrlFromComponents(this.repositoryUrl, 'commit', commit);
  }

  getComparisonUrl(commit1, commit2) {
    return makeUrlFromComponents(this.repositoryUrl, 'compare', `${commit1}...${commit2}`);
  }

  getFileUrl(commit, path) {
    return makeUrlFromComponents(this.repositoryUrl, 'blob', commit, path);
  }
}

export class GitLab extends Repository {
  getCommitUrl(commit) {
    return makeUrlFromComponents(this.repositoryUrl, '-', 'commit', commit);
  }

  getComparisonUrl(commit1, commit2) {
    return makeUrlFromComponents(this.repositoryUrl, '-', 'compare', `${commit1}...${commit2}`);
  }
  getFileUrl(commit, path) {
    return makeUrlFromComponents(this.repositoryUrl, '-', 'blob', commit, path);
  }
}

/**
 * @param {String} repositoryType
 * @param {String} repositoryUrl
 * @return ?Repository
 */
export function getRepository(repositoryType, repositoryUrl) {
  switch (repositoryType.toLowerCase()) {
  case 'github':
    return new GitHub(repositoryUrl);
  case 'gitlab':
    return new GitLab(repositoryUrl);
  default:
    return null;
  }
}
