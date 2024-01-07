describe('date range selector', () => {
  it('displays empty input boxes by default and does not set date params', () => {
    cy.visit('index.php?project=InsightExample&showfilters=1');
    cy.get('#begin').should('have.value', '');
    cy.get('#end').should('have.value', '');
    cy.get('input[name=apply]').click();
    cy.url().should('contain', 'index.php?project=InsightExample&filtercount=1&showfilters=1&field1=site&compare1=63&value1=');
  });

  it('sets "begin" and "end" fields according to date params', () => {
    cy.visit('index.php?project=InsightExample&date=2009-02-23&showfilters=1');
    cy.get('#begin').should('have.value', '2009-02-23');
    cy.get('#end').should('have.value', '2009-02-23');
  });

  it('sets only the "begin" or "end" fields in the URL', () => {
    // begin
    cy.visit('index.php?project=InsightExample&begin=2009-02-23&showfilters=1');
    cy.get('#begin').should('have.value', '2009-02-23');
    cy.get('#end').should('have.value', '2009-02-23');
    cy.get('#end').clear();
    cy.get('input[name=apply]').click();
    cy.url().should('contain', 'index.php?project=InsightExample&date=2009-02-23&filtercount=1&showfilters=1&field1=site&compare1=63&value1=');

    // end
    cy.visit('index.php?project=InsightExample&end=2009-02-23&showfilters=1');
    cy.get('#begin').should('have.value', '2009-02-23');
    cy.get('#end').should('have.value', '2009-02-23');
    cy.get('#begin').clear();
    cy.get('input[name=apply]').click();
    cy.url().should('contain', 'index.php?project=InsightExample&date=2009-02-23&filtercount=1&showfilters=1&field1=site&compare1=63&value1=');
  });

  it('supports selecting a range of dates', () => {
    cy.visit('index.php?project=InsightExample&begin=2009-02-22&end=2009-02-24&showfilters=1');
    cy.get('#begin').should('have.value', '2009-02-22');
    cy.get('#end').should('have.value', '2009-02-24');
    cy.get('input[name=apply]').click();
    cy.url().should('contain', 'index.php?project=InsightExample&begin=2009-02-22&end=2009-02-24&filtercount=1&showfilters=1&field1=site&compare1=63&value1=');
  });

  it('removes date params when "begin" and "end" fields are cleared', () => {
    cy.visit('index.php?project=InsightExample&end=2009-02-23&showfilters=1');
    cy.get('#begin').clear();
    cy.get('#end').clear();
    cy.get('input[name=apply]').click();
    cy.url().should('contain', 'index.php?project=InsightExample&filtercount=1&showfilters=1&field1=site&compare1=63&value1=');
  });
});
