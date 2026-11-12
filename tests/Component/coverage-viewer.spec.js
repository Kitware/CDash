import { beforeEach, describe, expect, it } from 'vitest';
import { render } from 'vitest-browser-vue';
import CoverageViewer from '../../resources/js/vue/components/shared/CoverageViewer.vue';

describe('CoverageViewer', () => {
  const fileContent = [
    '1', '2', '3', '4', '5', '6', '7', '8', '9',
  ].join('\n');

  const coverageLinesData = [
    { lineNumber: 0, timesHit: 5, totalBranches: null, branchesHit: null },
    { lineNumber: 1, timesHit: 0, totalBranches: null, branchesHit: null },
    { lineNumber: 2, timesHit: null, totalBranches: 2, branchesHit: 1 },
    { lineNumber: 3, timesHit: null, totalBranches: 1, branchesHit: 0 },
    // Line 5 (lineNumber 4) has no coverage data
    { lineNumber: 5, timesHit: null, totalBranches: 1, branchesHit: 1 },
    { lineNumber: 7, timesHit: 1, totalBranches: null, branchesHit: null },
  ];

  let container;

  // CodeMirror's lines and gutters have no roles or test IDs, so look them up by class.
  const line = (index) => container.querySelectorAll('.cm-line')[index];
  const gutterMarker = (index) => container.querySelectorAll('.cm-coverage-gutter .cm-gutterElement')[index]?.querySelector('span');

  beforeEach(async () => {
    ({ container } = await render(CoverageViewer, {
      props: {
        file: fileContent,
        coverageLines: coverageLinesData,
      },
    }));
  });

  it('should highlight a simple hit line in green', async () => {
    await expect.poll(() => line(0)).toHaveClass('cm-line-hit');
    await expect.poll(() => gutterMarker(0)).toHaveTextContent('5');
    await expect.poll(() => gutterMarker(0)).toHaveClass('cm-coverage-gutter-hit');
  });

  it('should highlight a missed line in red', async () => {
    await expect.poll(() => line(1)).toHaveClass('cm-line-miss');
    await expect.poll(() => gutterMarker(1)).toHaveTextContent('0');
    await expect.poll(() => gutterMarker(1)).toHaveClass('cm-coverage-gutter-miss');
  });

  it('should highlight a partially covered branch line in yellow', async () => {
    await expect.poll(() => line(2)).toHaveClass('cm-line-partial');
    await expect.poll(() => gutterMarker(2)).toHaveTextContent('1/2');
    await expect.poll(() => gutterMarker(2)).toHaveClass('cm-coverage-gutter-partial');
  });

  it('should highlight a branch line with no hits in red', async () => {
    await expect.poll(() => line(3)).toHaveClass('cm-line-miss');
    await expect.poll(() => gutterMarker(3)).toHaveTextContent('0/1');
    await expect.poll(() => gutterMarker(3)).toHaveClass('cm-coverage-gutter-miss');
  });

  it('should highlight a fully covered branch line in green', async () => {
    await expect.poll(() => line(5)).toHaveClass('cm-line-hit');
    await expect.poll(() => gutterMarker(4)).toHaveTextContent('1/1');
    await expect.poll(() => gutterMarker(4)).toHaveClass('cm-coverage-gutter-hit');
  });
});
