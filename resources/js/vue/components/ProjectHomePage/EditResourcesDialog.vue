<template>
  <dialog
    ref="dialog"
    class="tw-modal"
    data-test="edit-resources-dialog"
  >
    <div class="tw-modal-box tw-flex tw-flex-col tw-gap-4">
      <h3 class="tw-text-lg tw-font-bold tw-m-0">
        Edit resources
      </h3>
      <div
        v-if="generalError"
        ref="errorAlert"
        role="alert"
        class="tw-alert tw-alert-error"
        data-test="edit-resources-error"
      >
        <FontAwesomeIcon :icon="FA.faCircleExclamation" />
        <span>{{ generalError }}</span>
      </div>
      <form
        class="tw-flex tw-flex-col tw-gap-2"
        novalidate
        @submit.prevent="save"
      >
        <div class="tw-flex tw-flex-col sm:tw-flex-row tw-gap-x-3">
          <InputField
            v-model="form.vcsUrl"
            type="url"
            label="Repository"
            placeholder="https://github.com/example/project"
            :validation-error="errors.vcsUrl"
            test-id="vcs-url-input"
            @update:model-value="errors.vcsUrl = null"
          />
          <SelectField
            v-model="form.vcsViewer"
            class="sm:tw-w-40 tw-shrink-0"
            label="Repository Type"
            :options="[
              { text: 'None', value: null },
              { text: 'GitHub', value: 'GITHUB' },
              { text: 'GitLab', value: 'GITLAB' },
            ]"
            test-id="vcs-viewer-input"
          />
        </div>
        <InputField
          v-for="field in OTHER_URL_FIELDS"
          :key="field.name"
          v-model="form[field.name]"
          type="url"
          :label="field.label"
          :description="field.description"
          placeholder="https://example.com"
          :validation-error="errors[field.name]"
          :test-id="field.testId"
          @update:model-value="errors[field.name] = null"
        />
        <div class="tw-modal-action">
          <button
            type="button"
            class="tw-btn"
            data-test="edit-resources-cancel-button"
            @click="close"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="tw-btn tw-btn-primary"
            :disabled="saving"
            data-test="edit-resources-save-button"
          >
            <span
              v-if="saving"
              class="tw-loading tw-loading-spinner tw-loading-xs"
            />
            Save
          </button>
        </div>
      </form>
    </div>
    <form
      method="dialog"
      class="tw-modal-backdrop"
    >
      <button>Close</button>
    </form>
  </dialog>
</template>

<script>
import gql from 'graphql-tag';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faCircleExclamation } from '@fortawesome/free-solid-svg-icons';
import InputField from '../shared/FormInputs/InputField.vue';
import SelectField from '../shared/FormInputs/SelectField.vue';

const URL_FIELDS = [
  'vcsUrl',
  'homeUrl',
  'documentationUrl',
  'bugTrackerUrl',
  'bugTrackerNewIssueUrl',
  'testDataUrl',
];

const INVALID_URL_MESSAGE = 'Enter a full URL, such as https://example.com.';

/**
 * The server only accepts complete URLs with a scheme and a host.  Check this before saving, because
 * the server rejects malformed URLs with an error which doesn't say which field was wrong.
 */
function isValidUrl(value) {
  if (!(/^[a-z][a-z0-9+.-]*:\/\//i).test(value) || (/\s/).test(value)) {
    return false;
  }
  try {
    return new URL(value).hostname !== '';
  } catch {
    return false;
  }
}

export default {
  name: 'EditResourcesDialog',

  components: {
    FontAwesomeIcon,
    InputField,
    SelectField,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },

    vcsUrl: {
      type: [String, null],
      default: null,
    },

    vcsViewer: {
      type: [String, null],
      default: null,
    },

    homeUrl: {
      type: [String, null],
      default: null,
    },

    documentationUrl: {
      type: [String, null],
      default: null,
    },

    bugTrackerUrl: {
      type: [String, null],
      default: null,
    },

    bugTrackerNewIssueUrl: {
      type: [String, null],
      default: null,
    },

    testDataUrl: {
      type: [String, null],
      default: null,
    },
  },

  data() {
    return {
      form: {},
      errors: {},
      generalError: null,
      saving: false,
    };
  },

  computed: {
    FA() {
      return {
        faCircleExclamation,
      };
    },

    OTHER_URL_FIELDS() {
      return [
        { name: 'homeUrl', label: 'Website', testId: 'home-url-input' },
        { name: 'documentationUrl', label: 'Documentation', testId: 'documentation-url-input' },
        { name: 'bugTrackerUrl', label: 'Issue Tracker', testId: 'bug-tracker-url-input' },
        {
          name: 'bugTrackerNewIssueUrl',
          label: 'Report an Issue',
          description: 'The page where users can file a new issue.',
          testId: 'bug-tracker-new-issue-url-input',
        },
        {
          name: 'testDataUrl',
          label: 'Test Data',
          description: 'Where the data used by the project\'s tests can be downloaded.',
          testId: 'test-data-url-input',
        },
      ];
    },
  },

  methods: {
    open() {
      this.form = {
        vcsViewer: this.vcsViewer,
      };
      for (const field of URL_FIELDS) {
        this.form[field] = this[field] ?? '';
      }
      this.errors = {};
      this.generalError = null;
      this.$refs.dialog.showModal();
    },

    close() {
      this.$refs.dialog.close();
    },

    async save() {
      this.generalError = null;

      const errors = {};
      const changes = {};
      for (const field of URL_FIELDS) {
        const value = this.form[field].trim();
        if (value !== '' && !isValidUrl(value)) {
          errors[field] = INVALID_URL_MESSAGE;
        }
        // Only send fields which changed.  An empty field clears the existing value.
        const newValue = value === '' ? null : value;
        if (newValue !== (this[field] ?? null)) {
          changes[field] = newValue;
        }
      }
      if (this.form.vcsViewer !== this.vcsViewer) {
        changes.vcsViewer = this.form.vcsViewer;
      }

      this.errors = errors;
      if (Object.keys(errors).length > 0) {
        this.showErrors();
        return;
      }
      if (Object.keys(changes).length === 0) {
        this.close();
        return;
      }

      this.saving = true;
      try {
        const result = await this.$apollo.mutate({
          mutation: gql`
            mutation updateProjectResources($input: UpdateProjectInput!) {
              updateProject(input: $input) {
                message
                project {
                  id
                  vcsUrl
                  vcsViewer
                  homeUrl
                  documentationUrl
                  bugTrackerUrl
                  bugTrackerNewIssueUrl
                  testDataUrl
                }
              }
            }
          `,
          variables: {
            input: {
              id: this.projectId,
              ...changes,
            },
          },
        });

        const message = result?.data?.updateProject?.message;
        if (message) {
          this.generalError = message;
        } else {
          this.close();
        }
      } catch (error) {
        this.handleError(error);
        this.showErrors();
      } finally {
        this.saving = false;
      }
    },

    /**
     * The form can be taller than the screen, so bring the first error into view.
     */
    async showErrors() {
      await this.$nextTick();
      if (this.generalError) {
        this.$refs.errorAlert?.scrollIntoView({ block: 'nearest' });
      } else {
        this.$refs.dialog.querySelector('.tw-input-error')?.focus();
      }
    },

    handleError(error) {
      const graphQLErrors = error?.graphQLErrors ?? [];

      if (graphQLErrors.length === 0) {
        this.generalError = 'Your changes could not be saved. Check your connection and try again.';
        return;
      }

      const fieldErrors = {};
      const messages = [];
      for (const graphQLError of graphQLErrors) {
        const validation = graphQLError.extensions?.validation;
        if (validation) {
          for (const [key, fieldMessages] of Object.entries(validation)) {
            const field = key.replace(/^input\./, '');
            if (field in this.form) {
              fieldErrors[field] = fieldMessages[0];
            } else {
              messages.push(...fieldMessages);
            }
          }
        } else if (graphQLError.message === 'This action is unauthorized.') {
          // An expired session also ends up here, since the request is then made as a guest.
          messages.push('You do not have permission to edit this project. If you were signed out, reload the page and sign in again.');
        } else if (graphQLError.message === 'Internal server error') {
          messages.push('The server could not save your changes. Check that each URL is complete, such as https://example.com, and try again.');
        } else {
          messages.push(graphQLError.message);
        }
      }

      this.errors = { ...this.errors, ...fieldErrors };
      this.generalError = messages.length > 0 ? [...new Set(messages)].join(' ') : null;
    },
  },
};
</script>
