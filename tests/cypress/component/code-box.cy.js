import CodeBox from '../../../resources/js/vue/components/shared/CodeBox.vue';

describe('CodeBox', () => {
  it('renders the text', () => {
    const code = 'const message = "Hello, World!";\nconsole.log(message);';

    cy.mount(CodeBox, {
      props: {
        text: code,
      },
    });

    cy.get('.cm-editor').should('be.visible');
    cy.get('.cm-content').should('contain.text', 'Hello, World!');
  });

  it('updates the content when the text prop changes', () => {
    const initialCode = 'const a = 1;';
    const updatedCode = 'const b = 2;';

    cy.mount(CodeBox, {
      props: {
        text: initialCode,
      },
    }).then(({ wrapper }) => {
      cy.get('.cm-content').should('contain.text', 'const a = 1;');
      wrapper.setProps({ text: updatedCode });
      // Wait for the DOM to update before asserting the new content.
      cy.get('.cm-content').should('not.contain.text', 'const a = 1;').then(() => {
        cy.get('.cm-content').should('contain.text', 'const b = 2;');
      });
    });
  });
});
