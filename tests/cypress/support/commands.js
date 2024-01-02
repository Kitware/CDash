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

  // If we get redirected back to the login page, the test users don't exist yet and should be created.
  cy.url().then((url) => {
    if (url === `${Cypress.config().baseUrl}/login`) {
      cy.clearCookies();
      cy.visit('/register');

      cy.get('[name=fname]').type('user');
      cy.get('[name=lname]').type('user');
      cy.get('[name=email]').type('user@example.com');
      cy.get('[name=password]').type('12345');
      cy.get('[name=password_confirmation]').type('12345');
      cy.get('[name=institution]').type('testing');
      cy.get('[type=submit]').click();

      cy.visit('/logout');
      cy.clearCookies();
      cy.visit('/register');

      cy.get('[name=fname]').type('admin');
      cy.get('[name=lname]').type('admin');
      cy.get('[name=email]').type('admin@example.com');
      cy.get('[name=password]').type('12345');
      cy.get('[name=password_confirmation]').type('12345');
      cy.get('[name=institution]').type('testing');
      cy.get('[type=submit]').click();

      cy.visit('/logout');
      cy.clearCookies();
      cy.visit('/login');
      cy.get('[name=email]').type('simpletest@localhost');
      cy.get('[name=password]').type('simpletest');
      cy.get('[type=submit]').click();

      cy.visit('/manageUsers.php');
      cy.get('[name=search]').type('admin');
      cy.wait(500); // Wait for the content to load dynamically
      cy.get('#newuser').contains('admin@example.com').parent().parent().find('[name=makeadmin]').click();

      cy.visit('/logout');
      cy.clearCookies();
      cy.login(user);
    }
  });
});
