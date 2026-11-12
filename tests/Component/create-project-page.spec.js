import { describe, expect, it } from 'vitest';
import { render } from 'vitest-browser-vue';
import CreateProjectPage from '../../resources/js/vue/components/CreateProjectPage.vue';

describe('CreateProjectPage', () => {
  it('locks authenticated submissions on when the instance requires them', async () => {
    const screen = await render(CreateProjectPage, {
      props: {
        maxProjectVisibility: 'PUBLIC',
        authenticatedSubmissionsRequired: true,
      },
    });

    const input = screen.getByTestId('project-authenticated-submissions-input');
    await expect.element(input).toBeChecked();
    await expect.element(input).toBeDisabled();
  });

  it('leaves authenticated submissions editable when the instance does not require them', async () => {
    const screen = await render(CreateProjectPage, {
      props: {
        maxProjectVisibility: 'PUBLIC',
        authenticatedSubmissionsRequired: false,
      },
    });

    const input = screen.getByTestId('project-authenticated-submissions-input');
    await expect.element(input).not.toBeChecked();
    await expect.element(input).toBeEnabled();
  });

  it('shows every validation error returned by the server', async () => {
    const screen = await render(CreateProjectPage, {
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

    await screen.getByTestId('create-project-button').click();

    const page = screen.getByTestId('create-project-page');
    await expect.element(page).toMatchTextContent('Name error.');
    await expect.element(page).toMatchTextContent('Description error.');
    await expect.element(page).toMatchTextContent('Visibility error.');
    await expect.element(page).toMatchTextContent('Authenticated submissions error.');
    await expect.element(page).toMatchTextContent('Future field error.');
  });
});
