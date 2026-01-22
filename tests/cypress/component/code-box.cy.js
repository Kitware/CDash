import CodeBox from '../../../resources/js/vue/components/shared/CodeBox.vue';

describe('CodeBox', () => {
  it('renders the text', () => {
    const code = 'const message = "Hello, World!";\nconsole.log(message);';

    cy.mount(CodeBox, {
      props: {
        text: code,
      },
    });

    cy.contains('Hello, World!').should('exist');
  });

  it('updates the content when the text prop changes', () => {
    const initialCode = 'const a = 1;';
    const updatedCode = 'const b = 2;';

    cy.mount(CodeBox, {
      props: {
        text: initialCode,
      },
    }).then(({ wrapper }) => {
      cy.contains('const a = 1;').should('exist');
      wrapper.setProps({ text: updatedCode });
      // Wait for the DOM to update before asserting the new content.
      cy.contains('const a = 1;').should('not.exist').then(() => {
        cy.contains('const b = 2;').should('exist');
      });
    });
  });
});
