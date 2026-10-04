// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

/**
 * Log in a given user.  Current options: "admin", "user".
 */
Cypress.Commands.add('login', (user = 'admin', password = null) => {
  cy.visit('/login');
  cy.get('[name=email]').type(`${user}@example.com`);
  cy.get('[name=password]').type(password ?? '12345');
  cy.get('[type=submit]').click();
  cy.url().should('not.eq', `${Cypress.config().baseUrl}/login`);
});
