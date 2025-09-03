import CoverageViewer from '../../../resources/js/vue/components/shared/CoverageViewer.vue';

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

  beforeEach(() => {
    cy.mount(CoverageViewer, {
      props: {
        file: fileContent,
        coverageLines: coverageLinesData,
      },
    });
  });

  it('should highlight a simple hit line in green', () => {
    cy.get('.cm-line').eq(0).should('have.class', 'cm-line-hit');
    cy.get('.cm-coverage-gutter .cm-gutterElement').eq(0).find('span')
      .should('have.text', '5')
      .and('have.class', 'cm-coverage-gutter-hit');
  });

  it('should highlight a missed line in red', () => {
    cy.get('.cm-line').eq(1).should('have.class', 'cm-line-miss');
    cy.get('.cm-coverage-gutter .cm-gutterElement').eq(1).find('span')
      .should('have.text', '0')
      .and('have.class', 'cm-coverage-gutter-miss');
  });

  it('should highlight a partially covered branch line in yellow', () => {
    cy.get('.cm-line').eq(2).should('have.class', 'cm-line-partial');
    cy.get('.cm-coverage-gutter .cm-gutterElement').eq(2).find('span')
      .should('have.text', '1/2')
      .and('have.class', 'cm-coverage-gutter-partial');
  });

  it('should highlight a branch line with no hits in red', () => {
    cy.get('.cm-line').eq(3).should('have.class', 'cm-line-miss');
    cy.get('.cm-coverage-gutter .cm-gutterElement').eq(3).find('span')
      .should('have.text', '0/1')
      .and('have.class', 'cm-coverage-gutter-miss');
  });

  it('should highlight a fully covered branch line in green', () => {
    cy.get('.cm-line').eq(5).should('have.class', 'cm-line-hit');
    cy.get('.cm-coverage-gutter .cm-gutterElement').eq(4).find('span')
      .should('have.text', '1/1')
      .and('have.class', 'cm-coverage-gutter-hit');
  });
});
