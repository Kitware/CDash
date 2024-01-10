import LoadingIndicator from '../../../resources/js/components/shared/LoadingIndicator.vue';

it('displays according to isLoading prop', () => {
  cy.mount(LoadingIndicator, {
    props: {
      isLoading: true,
    },
    slots: {
      default: '<div id="testcontent">content</div>',
    },
  });

  // The loading indicator has a delay to prevent a flicker on pages which render immediately.
  cy.wait(1000);

  cy.get('img[alt="The page is loading."]').should('be.visible');

  cy.get('#testcontent').should('not.exist');

  cy.mount(LoadingIndicator, {
    props: {
      isLoading: false,
    },
    slots: {
      default: '<div id="testcontent">content</div>',
    },
  });

  cy.wait(1000);

  cy.get('img[alt="The page is loading."]').should('not.exist');

  cy.get('#testcontent').should('be.visible');
});
