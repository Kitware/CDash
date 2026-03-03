import {
  getRepository,
  GitHub,
  GitLab,
  Repository,
} from '../../resources/js/vue/components/shared/RepositoryIntegrations';

describe('RepositoryIntegrations', () => {
  describe('getRepository', () => {
    it('returns a GitHub instance for "github" type', () => {
      const repo = getRepository('github', 'https://github.com/foo/bar');
      expect(repo).toBeInstanceOf(GitHub);
      expect(repo.repositoryUrl).toBe('https://github.com/foo/bar');
    });

    it('returns a GitLab instance for "gitlab" type', () => {
      const repo = getRepository('gitlab', 'https://gitlab.com/foo/bar');
      expect(repo).toBeInstanceOf(GitLab);
      expect(repo.repositoryUrl).toBe('https://gitlab.com/foo/bar');
    });

    it('is case insensitive for repository type', () => {
      const repo = getRepository('GitHub', 'https://github.com/foo/bar');
      expect(repo).toBeInstanceOf(GitHub);
    });

    it('returns null for unknown repository types', () => {
      const repo = getRepository('bitbucket', 'https://bitbucket.org/foo/bar');
      expect(repo).toBeNull();
    });
  });

  describe('GitHub', () => {
    const repoUrl = 'https://github.com/foo/bar';
    const repo = new GitHub(repoUrl);

    it('generates correct commit URL', () => {
      const commit = 'abcdef123456';
      expect(repo.getCommitUrl(commit)).toBe(`${repoUrl}/commit/${commit}`);
    });

    it('generates correct comparison URL', () => {
      const commit1 = 'abc';
      const commit2 = 'def';
      expect(repo.getComparisonUrl(commit1, commit2)).toBe(`${repoUrl}/compare/${commit1}...${commit2}`);
    });

    it('generates correct file URL', () => {
      const commit = 'abcdef123456';
      const path = 'src/main.cpp';
      expect(repo.getFileUrl(commit, path)).toBe(`${repoUrl}/blob/${commit}/${path}`);
    });

    it('handles trailing slashes in repository URL', () => {
      const repoWithSlash = new GitHub('https://github.com/foo/bar/');
      const commit = '123';
      expect(repoWithSlash.getCommitUrl(commit)).toBe('https://github.com/foo/bar/commit/123');
    });
  });

  describe('GitLab', () => {
    const repoUrl = 'https://gitlab.com/foo/bar';
    const repo = new GitLab(repoUrl);

    it('generates correct commit URL', () => {
      const commit = 'abcdef123456';
      expect(repo.getCommitUrl(commit)).toBe(`${repoUrl}/-/commit/${commit}`);
    });

    it('generates correct comparison URL', () => {
      const commit1 = 'abc';
      const commit2 = 'def';
      expect(repo.getComparisonUrl(commit1, commit2)).toBe(`${repoUrl}/-/compare/${commit1}...${commit2}`);
    });

    it('generates correct file URL', () => {
      const commit = 'abcdef123456';
      const path = 'src/main.cpp';
      expect(repo.getFileUrl(commit, path)).toBe(`${repoUrl}/-/blob/${commit}/${path}`);
    });

    it('handles trailing slashes in repository URL', () => {
      const repoWithSlash = new GitLab('https://gitlab.com/foo/bar/');
      const commit = '123';
      expect(repoWithSlash.getCommitUrl(commit)).toBe('https://gitlab.com/foo/bar/-/commit/123');
    });
  });

  describe('Repository (Abstract)', () => {
    const repo = new Repository('https://example.com');

    it('throws error for unimplemented getCommitUrl', () => {
      expect(() => repo.getCommitUrl('123')).toThrow('Method not implemented for abstract Repository class.');
    });

    it('throws error for unimplemented getComparisonUrl', () => {
      expect(() => repo.getComparisonUrl('123', '456')).toThrow('Method not implemented for abstract Repository class.');
    });

    it('throws error for unimplemented getFileUrl', () => {
      expect(() => repo.getFileUrl('123', 'file.txt')).toThrow('Method not implemented for abstract Repository class.');
    });
  });
});
