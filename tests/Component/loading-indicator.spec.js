import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render } from 'vitest-browser-vue';
import LoadingIndicator from '../../resources/js/vue/components/shared/LoadingIndicator.vue';

describe('LoadingIndicator', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('shows the loading image instead of the content while loading', async () => {
    const screen = await render(LoadingIndicator, {
      props: {
        isLoading: true,
      },
      slots: {
        default: '<div>content</div>',
      },
    });

    // The loading indicator has a delay to prevent a flicker on pages which render immediately.
    await vi.advanceTimersByTimeAsync(1000);

    await expect.element(screen.getByAltText('The page is loading.')).toBeVisible();
    await expect.element(screen.getByText('content')).not.toBeInTheDocument();
  });

  it('shows the content when not loading', async () => {
    const screen = await render(LoadingIndicator, {
      props: {
        isLoading: false,
      },
      slots: {
        default: '<div>content</div>',
      },
    });

    await vi.advanceTimersByTimeAsync(1000);

    await expect.element(screen.getByAltText('The page is loading.')).not.toBeInTheDocument();
    await expect.element(screen.getByText('content')).toBeVisible();
  });
});
