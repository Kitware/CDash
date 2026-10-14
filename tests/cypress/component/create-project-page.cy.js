import CreateProjectPage from '../../../resources/js/vue/components/CreateProjectPage.vue';

describe('CreateProjectPage', () => {
  it('locks authenticated submissions on when the instance requires them', () => {
    cy.mount(CreateProjectPage, {
      props: {
        maxProjectVisibility: 'PUBLIC',
        authenticatedSubmissionsRequired: true,
      },
    });

    cy.get('[data-test="project-authenticated-submissions-input"]').should('be.checked').and('be.disabled');
  });

  it('leaves authenticated submissions editable when the instance does not require them', () => {
    cy.mount(CreateProjectPage, {
      props: {
        maxProjectVisibility: 'PUBLIC',
        authenticatedSubmissionsRequired: false,
      },
    });

    cy.get('[data-test="project-authenticated-submissions-input"]').should('not.be.checked').and('be.enabled');
  });

  it('shows every validation error returned by the server', () => {
    cy.mount(CreateProjectPage, {
      props: {
        maxProjectVisibility: 'PUBLIC',
        authenticatedSubmissionsRequired: false,
      },
      global: {
        mocks: {
          $apollo: {
            mutate: () => Promise.reject({
              graphQLErrors: [{
                extensions: {
                  validation: {
                    'input.name': ['Name error.'],
                    'input.description': ['Description error.'],
                    'input.visibility': ['Visibility error.'],
                    'input.authenticateSubmissions': ['Authenticated submissions error.'],
                    'input.someFutureField': ['Future field error.'],
                  },
                },
              }],
            }),
          },
        },
      },
    });

    cy.get('[data-test="create-project-button"]').click();

    cy.get('[data-test="create-project-page"]')
      .should('contain', 'Name error.')
      .and('contain', 'Description error.')
      .and('contain', 'Visibility error.')
      .and('contain', 'Authenticated submissions error.')
      .and('contain', 'Future field error.');
  });
});
