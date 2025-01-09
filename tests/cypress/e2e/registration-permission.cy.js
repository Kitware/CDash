describe('registration-permission', () => {

  function loadEnvFile(cy, envName)  {
    cy.exec('cp .env .env.backup');
    cy.exec(`cp tests/Environments/.env.${envName} .env`);
  }

  function resetEnvFile(cy)  {
    cy.exec('cp .env.backup .env');
  }

  describe('Test the login page to verify that the Register link respects the permission level', () => {
    function visitPublicRegistration(permissionRequirement, containString) {
      loadEnvFile(cy, permissionRequirement);
      cy.visit('/login');

      // assert expected text in the page headers
      cy.get('#topmenu').should(containString, 'Register');

      resetEnvFile(cy);
    };

    it('does not show the registration button when registration is restricted to project and site admins', () => {
      visitPublicRegistration('PROJECT_ADMIN', 'not.contain');
    });

    it('shows the registration button when  registration is unrestricted', () => {
      visitPublicRegistration('PUBLIC', 'contain');
    });

    it('does not show the registration button when registration is restricted to site admins', () => {
      visitPublicRegistration('ADMIN', 'not.contain');
    });

    it('does not show the registration button when registration is disabled', () => {
      visitPublicRegistration('DISABLED', 'not.contain');
    });

  });

  describe('Test the Administrator ManageUsers page to verify that the registration form respects the permission level', () => {
    function visitManageUsers(permissionRequirement, containString) {
      loadEnvFile(cy, permissionRequirement);
      cy.login();
      cy.visit('/manageUsers.php');
      // assert expected text in the page headers
      cy.get('tbody').should(containString, 'Add new user');

      resetEnvFile(cy);
    };

    it('does show the registration form when registration is unrestricted', () => {
      visitManageUsers('PUBLIC', 'contain');
    });
    it('does show the registration form when registration is restricted to project and site admins', () => {
      visitManageUsers('PROJECT_ADMIN', 'contain');
    });

    it('does show the registration form when registration is restricted to site admins', () => {
      visitManageUsers('ADMIN', 'contain');
    });

    it('does not show the registration button when registration is disabled', () => {
      visitManageUsers('DISABLED', 'not.contain');
    });
  });


  describe('Test the manageProjectRoles page to verify that the registration form respects the permission level', () => {
    function visitManageProjectRoles(permissionRequirement, existString) {
      loadEnvFile(cy, permissionRequirement);
      cy.login();
      cy.visit('/manageProjectRoles.php?projectid=15');
      // assert expected text in the page headers
      cy.get('#fragment-3').should(existString);

      resetEnvFile(cy);
    };
    it('does show the registration form when registration is unrestricted', () => {
      visitManageProjectRoles('PUBLIC', 'exist');
    });
    it('does show the registration form when registration is restricted to project and site admins', () => {
      visitManageProjectRoles('PROJECT_ADMIN', 'exist');
    });

    it('does show the registration form when registration is restricted to site admins', () => {
      visitManageProjectRoles('ADMIN', 'not.exist');
    });

    it('does not show the registration button when registration is disabled', () => {
      visitManageProjectRoles('DISABLED', 'not.exist');
    });
  });
});
