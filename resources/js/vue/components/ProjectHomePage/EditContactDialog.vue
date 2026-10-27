<template>
  <dialog
    ref="dialog"
    class="tw-modal"
    data-test="edit-contact-dialog"
  >
    <div class="tw-modal-box tw-flex tw-flex-col tw-gap-4">
      <h3 class="tw-text-lg tw-font-bold tw-m-0">
        Edit contact information
      </h3>
      <div
        v-if="generalError"
        role="alert"
        class="tw-alert tw-alert-error"
        data-test="edit-contact-error"
      >
        <FontAwesomeIcon :icon="FA.faCircleExclamation" />
        <span>{{ generalError }}</span>
      </div>
      <form
        class="tw-flex tw-flex-col tw-gap-2"
        novalidate
        @submit.prevent="save"
      >
        <InputField
          v-model="form.contactInfo"
          label="Contact Information"
          description="How to reach the project's maintainers, such as an email address or mailing list."
          placeholder="maintainers@example.com"
          maxlength="100"
          :validation-error="validationError"
          test-id="contact-info-input"
          @update:model-value="validationError = null"
        />
        <div class="tw-modal-action">
          <button
            type="button"
            class="tw-btn"
            data-test="edit-contact-cancel-button"
            @click="close"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="tw-btn tw-btn-primary"
            :disabled="saving"
            data-test="edit-contact-save-button"
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

export default {
  name: 'EditContactDialog',

  components: {
    FontAwesomeIcon,
    InputField,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },

    contactInfo: {
      type: [String, null],
      default: null,
    },
  },

  data() {
    return {
      form: {},
      validationError: null,
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
  },

  methods: {
    open() {
      this.form = {
        contactInfo: this.contactInfo ?? '',
      };
      this.validationError = null;
      this.generalError = null;
      this.$refs.dialog.showModal();
    },

    close() {
      this.$refs.dialog.close();
    },

    async save() {
      this.generalError = null;

      // An empty field clears the existing contact information.
      const contactInfo = this.form.contactInfo.trim() || null;
      if (contactInfo === this.contactInfo) {
        this.close();
        return;
      }

      this.saving = true;
      try {
        const result = await this.$apollo.mutate({
          mutation: gql`
            mutation updateProjectContactInfo($input: UpdateProjectInput!) {
              updateProject(input: $input) {
                message
                project {
                  id
                  contactInfo
                }
              }
            }
          `,
          variables: {
            input: {
              id: this.projectId,
              contactInfo,
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
      } finally {
        this.saving = false;
      }
    },

    handleError(error) {
      const graphQLErrors = error?.graphQLErrors ?? [];

      if (graphQLErrors.length === 0) {
        this.generalError = 'Your changes could not be saved. Check your connection and try again.';
        return;
      }

      const messages = [];
      for (const graphQLError of graphQLErrors) {
        const validation = graphQLError.extensions?.validation;
        if (validation?.['input.contactInfo']) {
          this.validationError = validation['input.contactInfo'][0];
        } else if (validation) {
          messages.push(...Object.values(validation).flat());
        } else if (graphQLError.message === 'This action is unauthorized.') {
          // An expired session also ends up here, since the request is then made as a guest.
          messages.push('You do not have permission to edit this project. If you were signed out, reload the page and sign in again.');
        } else {
          messages.push(graphQLError.message);
        }
      }

      this.generalError = messages.length > 0 ? [...new Set(messages)].join(' ') : null;
    },
  },
};
</script>
