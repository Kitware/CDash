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

  it('renders matching strings as links when links prop is provided', () => {
    const code = 'See issue example-com and issue example-org for details.';
    const links = new Map([
      ['example-com', 'https://example.com'],
      ['example-org', 'https://example.org'],
    ]);

    cy.mount(CodeBox, {
      props: {
        text: code,
        links: links,
      },
    });

    cy.get('a').should('have.length', 2);

    cy.contains('a', 'example-com')
      .should('have.attr', 'href', 'https://example.com')
      .should('have.class', 'tw-link tw-link-hover tw-link-info');

    cy.contains('a', 'example-org')
      .should('have.attr', 'href', 'https://example.org')
      .should('have.class', 'tw-link tw-link-hover tw-link-info');

    cy.contains('See issue').should('exist');
    cy.contains('and issue').should('exist');
    cy.contains('for details.').should('exist');
  });

  it('handles multiple instances of the same link correctly', () => {
    const code = 'Duplicate example and example';
    const links = new Map([
      ['example', 'https://example.com'],
    ]);

    cy.mount(CodeBox, {
      props: {
        text: code,
        links: links,
      },
    });

    cy.get('a').should('have.length', 2);
    cy.get('a').eq(0).should('have.attr', 'href', 'https://example.com');
    cy.get('a').eq(1).should('have.attr', 'href', 'https://example.com');
  });

  it('shows the copy button by default when text is present', () => {
    cy.mount(CodeBox, {
      props: {
        text: 'some text',
      },
    });

    cy.get('[data-test="copy-button"]').should('exist');
  });

  it('hides the copy button when showCopyButton is false', () => {
    cy.mount(CodeBox, {
      props: {
        text: 'some text',
        showCopyButton: false,
      },
    });

    cy.get('[data-test="copy-button"]').should('not.exist');
  });

  it('hides the copy button when the text is empty', () => {
    cy.mount(CodeBox, {
      props: {
        text: '',
      },
    });

    cy.get('[data-test="copy-button"]').should('not.exist');
  });

  it('hides the copy button when the text becomes empty', () => {
    cy.mount(CodeBox, {
      props: {
        text: 'some text',
      },
    }).then(({ wrapper }) => {
      cy.get('[data-test="copy-button"]').should('exist').then(() => {
        wrapper.setProps({ text: '' });
        cy.get('[data-test="copy-button"]').should('not.exist');
      });
    });
  });

  it('copies the text to the clipboard when the copy button is clicked', () => {
    const code = 'const message = "Hello, World!";';

    cy.mount(CodeBox, {
      props: {
        text: code,
      },
    }).then(() => {
      cy.window().then((win) => {
        cy.stub(win.navigator.clipboard, 'writeText').resolves().as('writeText');
      });

      cy.get('[data-test="copy-button"]').click();
      cy.get('@writeText').should('have.been.calledWith', code);
    });
  });
});
