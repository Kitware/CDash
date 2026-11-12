import { describe, expect, it } from 'vitest';
import { render } from 'vitest-browser-vue';
import CodeBox from '../../resources/js/vue/components/shared/CodeBox.vue';

describe('CodeBox', () => {
  it('renders the text', async () => {
    const code = 'const message = "Hello, World!";\nconsole.log(message);';

    const screen = await render(CodeBox, {
      props: {
        text: code,
      },
    });

    await expect.element(screen.getByText('Hello, World!', { exact: false })).toBeInTheDocument();
  });

  it('updates the content when the text prop changes', async () => {
    const initialCode = 'const a = 1;';
    const updatedCode = 'const b = 2;';

    const screen = await render(CodeBox, {
      props: {
        text: initialCode,
      },
    });

    await expect.element(screen.getByText('const a = 1;')).toBeInTheDocument();
    await screen.rerender({ text: updatedCode });
    await expect.element(screen.getByText('const a = 1;')).not.toBeInTheDocument();
    await expect.element(screen.getByText('const b = 2;')).toBeInTheDocument();
  });

  it('renders matching strings as links when links prop is provided', async () => {
    const code = 'See issue example-com and issue example-org for details.';
    const links = new Map([
      ['example-com', 'https://example.com'],
      ['example-org', 'https://example.org'],
    ]);

    const screen = await render(CodeBox, {
      props: {
        text: code,
        links: links,
      },
    });

    await expect.element(screen.getByRole('link')).toHaveLength(2);

    const comLink = screen.getByRole('link', { name: 'example-com' });
    await expect.element(comLink).toHaveAttribute('href', 'https://example.com');
    await expect.element(comLink).toHaveClass('tw-link', 'tw-link-hover', 'tw-link-info');

    const orgLink = screen.getByRole('link', { name: 'example-org' });
    await expect.element(orgLink).toHaveAttribute('href', 'https://example.org');
    await expect.element(orgLink).toHaveClass('tw-link', 'tw-link-hover', 'tw-link-info');

    await expect.element(screen.getByText('See issue')).toBeInTheDocument();
    await expect.element(screen.getByText('and issue')).toBeInTheDocument();
    await expect.element(screen.getByText('for details.')).toBeInTheDocument();
  });

  it('handles multiple instances of the same link correctly', async () => {
    const code = 'Duplicate example and example';
    const links = new Map([
      ['example', 'https://example.com'],
    ]);

    const screen = await render(CodeBox, {
      props: {
        text: code,
        links: links,
      },
    });

    const renderedLinks = screen.getByRole('link');
    await expect.element(renderedLinks).toHaveLength(2);
    await expect.element(renderedLinks.nth(0)).toHaveAttribute('href', 'https://example.com');
    await expect.element(renderedLinks.nth(1)).toHaveAttribute('href', 'https://example.com');
  });
});
