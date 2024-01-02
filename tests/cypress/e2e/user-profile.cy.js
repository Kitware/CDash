describe('User profile page', () => {
  it('is protected by login', () => {
    cy.visit('/profile');
    cy.url().should('eq', `${Cypress.config().baseUrl}/login`);
  });

  // TODO: figure out a cleaner way to reset each value...
  it('Can change name and email', () => {
    cy.login();
    cy.visit('/profile');

    cy.get('[name=fname]').should('have.value', 'admin');
    cy.get('[name=lname]').should('have.value', 'admin');
    cy.get('[name=email]').should('have.value', 'admin@example.com');
    cy.get('[name=institution]').should('have.value', 'testing');

    cy.get('[name=fname]').clear().type('first name here');
    cy.get('[name=lname]').clear().type('last name here');
    cy.get('[name=email]').clear().type('emailtest@example.com');
    cy.get('[name=institution]').clear().type('Kitware (Test)');

    cy.get('[name=updateprofile]').click();
    cy.get('#main_content').should('contain.text', 'Your profile has been updated');

    cy.visit('/logout');
    cy.clearCookies();
    cy.login('emailtest');

    cy.visit('/profile');

    cy.get('[name=fname]').should('have.value', 'first name here')
      .clear().type('admin');
    cy.get('[name=lname]').should('have.value', 'last name here')
      .clear().type('admin');
    cy.get('[name=email]').should('have.value', 'emailtest@example.com')
      .clear().type('admin@example.com');
    cy.get('[name=institution]').should('have.value', 'Kitware (Test)')
      .clear().type('testing');

    cy.get('[name=updateprofile]').click();
    cy.get('#main_content').should('contain.text', 'Your profile has been updated');

    cy.visit('/logout');
    cy.clearCookies();
    cy.login();

    cy.visit('/profile');
    cy.get('[name=fname]').should('have.value', 'admin');
    cy.get('[name=lname]').should('have.value', 'admin');
    cy.get('[name=email]').should('have.value', 'admin@example.com');
    cy.get('[name=institution]').should('have.value', 'testing');
  });

  it('Incorrect password prevents password reset', () => {
    cy.login();
    cy.visit('/profile');

    cy.get('[name=oldpasswd]').should('have.value', '');
    cy.get('[name=passwd]').should('have.value', '');
    cy.get('[name=passwd2]').should('have.value', '');

    cy.get('[name=oldpasswd]').type('incorrect password');
    cy.get('[name=passwd]').type('new password');
    cy.get('[name=passwd2]').type('new password');
    cy.get('[name=updatepassword]').click();
    cy.get('#main_content').should('contain.text', 'Your old password is incorrect');

    cy.visit('/logout');
    cy.clearCookies();
    cy.login();
  });

  it('Short password prevents password reset', () => {
    cy.login();
    cy.visit('/profile');

    cy.get('[name=oldpasswd]').should('have.value', '');
    cy.get('[name=passwd]').should('have.value', '');
    cy.get('[name=passwd2]').should('have.value', '');

    cy.get('[name=oldpasswd]').type('12345');
    cy.get('[name=passwd]').type('a');
    cy.get('[name=passwd2]').type('a');
    cy.get('[name=updatepassword]').click();
    cy.get('#main_content').should('contain.text', 'Password must be at least 5 characters');

    cy.visit('/logout');
    cy.clearCookies();
    cy.login();
  });

  it('Can change password', () => {
    cy.login();
    cy.visit('/profile');

    cy.get('[name=oldpasswd]').should('have.value', '');
    cy.get('[name=passwd]').should('have.value', '');
    cy.get('[name=passwd2]').should('have.value', '');

    cy.get('[name=oldpasswd]').type('12345');
    cy.get('[name=passwd]').type('new password');
    cy.get('[name=passwd2]').type('new password');
    cy.get('[name=updatepassword]').click();

    cy.visit('/logout');
    cy.clearCookies();
    cy.login('admin', 'new password');

    cy.visit('/profile');

    cy.get('[name=oldpasswd]').should('have.value', '');
    cy.get('[name=passwd]').should('have.value', '');
    cy.get('[name=passwd2]').should('have.value', '');

    cy.get('[name=oldpasswd]').type('new password');
    cy.get('[name=passwd]').type('12345');
    cy.get('[name=passwd2]').type('12345');
    cy.get('[name=updatepassword]').click();

    cy.visit('/logout');
    cy.clearCookies();
    cy.login();
  });
});
