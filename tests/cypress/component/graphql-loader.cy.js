import GraphqlLoader from '../../../resources/js/components/shared/GraphqlLoader.vue';
import axios from 'axios';
import { config } from '@vue/test-utils';

describe('GraphQL loader component tests', () => {
  it('Displays loading indicator while loading', () => {
    cy.intercept('**/graphql', {
      delay: 1000,
      statusCode: 200,
      body: {
        data: {
          projects: {},
        },
      },
    }).as('request');

    cy.mount(GraphqlLoader, {
      props: {
        query: `
          query {
            projects {
              name
              builds {
                name
              }
            }
          }
        `,
      },
      slots: {
        default: '<div id="testcontent">test content</div>',
      },
      global: {
        config: {
          globalProperties: {
            $axios: axios,
            $baseURL: 'http://localhost:1234',
          },
        },
      },
    });

    cy.get('#testcontent').should('not.exist');

    cy.wait('@request');

    cy.get('#testcontent').should('contain.text', 'test content');
  });

  it('Displays error message on invalid request', () => {
    cy.intercept('**/graphql', {
      statusCode: 200,
      body: {
        error: [
          {
            message: 'Invalid query',
          },
        ],
      },
    });

    cy.mount(GraphqlLoader, {
      props: {
        query: `
          this is not a valid query! }
        `,
      },
      slots: {
        default: '<div id="testcontent">test content</div>',
      },
      global: {
        config: {
          globalProperties: {
            $axios: axios,
            $baseURL: 'http://localhost:1234',
          },
        },
      },
    });

    cy.get('#testcontent').should('not.exist');

    cy.get('[data-cy="loading-error-message"]')
      .should('be.visible')
      .should('contain', 'An error occurred while querying the API!');
  });

  it('Displays error message on failed request', () => {
    cy.intercept('**/graphql', {
      forceNetworkError: true,
    });

    cy.mount(GraphqlLoader, {
      props: {
        query: `
          query {
            projects {
              name
              builds {
                name
              }
            }
          }
        `,
      },
      slots: {
        default: '<div id="testcontent">test content</div>',
      },
      global: {
        config: {
          globalProperties: {
            $axios: axios,
            $baseURL: 'http://localhost:1234',
          },
        },
      },
    });

    cy.get('#testcontent').should('not.exist');

    cy.get('[data-cy="loading-error-message"]')
      .should('be.visible')
      .should('contain', 'An error occurred while querying the API!');
  });
});
