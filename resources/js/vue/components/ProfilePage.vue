<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-max-w-4xl tw-mx-auto tw-justify-center tw-gap-12 tw-p-4">
    <div
      v-if="error"
      class="tw-alert tw-alert-error tw-mb-4"
    >
      {{ error }}
    </div>
    <div
      v-if="message"
      class="tw-alert tw-alert-success tw-mb-4"
    >
      {{ message }}
    </div>

    <FormSection title="Profile">
      <form
        id="profile_form"
        method="post"
        action=""
        class="tw-flex tw-flex-col tw-gap-4"
      >
        <input
          type="hidden"
          name="_token"
          :value="csrfToken"
        >

        <InputField
          v-model="profileForm.fname"
          name="fname"
          label="First Name"
          test-id="fname-input"
        />
        <InputField
          v-model="profileForm.lname"
          name="lname"
          label="Last Name"
          test-id="lname-input"
        />
        <InputField
          v-model="profileForm.email"
          name="email"
          label="Email"
          test-id="email-input"
        />
        <InputField
          v-model="profileForm.institution"
          name="institution"
          label="Institution"
          test-id="institution-input"
        />

        <div class="tw-flex tw-justify-end tw-mt-4">
          <input
            type="submit"
            value="Update Profile"
            name="updateprofile"
            class="tw-btn tw-btn-sm tw-btn-primary"
            data-test="update-profile-button"
          >
        </div>
      </form>
    </FormSection>

    <FormSection title="Change Password">
      <form
        id="password_form"
        method="post"
        action=""
        class="tw-flex tw-flex-col tw-gap-4"
      >
        <input
          type="hidden"
          name="_token"
          :value="csrfToken"
        >

        <InputField
          v-model="passwordForm.oldpasswd"
          name="oldpasswd"
          type="password"
          label="Current Password"
          test-id="oldpasswd-input"
        />
        <InputField
          v-model="passwordForm.passwd"
          name="passwd"
          type="password"
          label="New Password"
          test-id="passwd-input"
        />
        <InputField
          v-model="passwordForm.passwd2"
          name="passwd2"
          type="password"
          label="Confirm Password"
          test-id="passwd2-input"
        />

        <div class="tw-flex tw-justify-end tw-mt-4">
          <input
            type="submit"
            value="Update Password"
            name="updatepassword"
            class="tw-btn tw-btn-sm tw-btn-primary"
            data-test="update-password-button"
          >
        </div>
      </form>
    </FormSection>

    <FormSection title="Authentication Tokens">
      <div class="tw-flex tw-flex-col tw-gap-4 tw-mb-8">
        <div class="tw-text-lg tw-font-bold">
          Create New Token
        </div>
        <div class="tw-flex tw-flex-col tw-gap-4">
          <InputField
            v-model="newTokenForm.description"
            label="Description"
            placeholder="Token description"
            :validation-error="newTokenValidationErrors?.description?.[0]"
            test-id="token-description-input"
          />
          <div class="tw-flex tw-flex-row tw-items-start tw-gap-4">
            <InputField
              v-model="newTokenForm.expiration"
              type="date"
              label="Expiration Date"
              :min="todayDate"
              :max="maxTokenExpiration"
              :validation-error="newTokenValidationErrors?.expiration?.[0]"
              test-id="token-expiration-input"
            />
            <SelectField
              v-model="newTokenForm.scope"
              label="Scope"
              :options="[
                { value: 'SUBMIT_ONLY', text: 'Submit Only' },
                { value: 'FULL_ACCESS', text: 'Full Access' }
              ]"
              :validation-error="newTokenValidationErrors?.scope?.[0]"
              test-id="token-scope-input"
            />
            <SelectField
              v-model="newTokenForm.projectId"
              label="Project (Optional)"
              :disabled="newTokenForm.scope === 'FULL_ACCESS'"
              :options="projectOptions"
              :validation-error="newTokenValidationErrors?.projectId?.[0]"
              test-id="token-project-input"
            />
          </div>
          <div class="tw-flex tw-items-center tw-justify-end tw-mt-2">
            <button
              class="tw-btn tw-btn-primary tw-btn-sm"
              :disabled="!isNewTokenFormValid"
              data-test="create-token-button"
              @click="createAuthenticationToken"
            >
              <font-awesome-icon :icon="FA.faPlus" /> Create Token
            </button>
          </div>

          <div
            v-if="newTokenError"
            class="tw-text-error"
            data-test="new-token-error"
          >
            {{ newTokenError }}
          </div>
          <div
            v-if="newTokenRaw"
            class="tw-bg-gray-100 tw-p-4 tw-rounded-lg tw-flex tw-flex-col tw-gap-2"
            data-test="new-token-container"
          >
            <div class="tw-font-bold">
              Token created successfully. This token cannot be retrieved after leaving the page.
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
              <div
                class="tw-font-mono tw-bg-gray-200 tw-p-2 tw-rounded tw-flex-grow tw-overflow-x-auto"
                data-test="new-token-raw"
              >
                {{ newTokenRaw }}
              </div>
              <button
                class="tw-btn tw-btn-sm"
                data-test="copy-token-button"
                @click="copyToken"
              >
                <font-awesome-icon
                  v-if="!copied"
                  :icon="FA.faCopy"
                />
                <span v-if="copied">Copied!</span>
                <span v-else>Copy</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="tw-divider" />

      <div class="tw-flex tw-flex-col tw-gap-4">
        <table
          class="tw-table tw-table-zebra tw-w-full"
          data-test="auth-tokens-table"
        >
          <thead>
            <tr>
              <th class="tw-text-center">
                Description
              </th>
              <th class="tw-text-center">
                Expires
              </th>
              <th class="tw-text-center">
                Created
              </th>
              <th class="tw-text-center">
                Scope
              </th>
              <th class="tw-text-center">
                Project
              </th>
              <th class="tw-text-center" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="{ node: token } in authenticationTokens"
              :key="token.id"
              data-test="auth-token-row"
            >
              <td
                class="tw-w-full"
                data-test="token-description"
              >
                {{ token.description }}
              </td>
              <td
                class="tw-text-nowrap"
                data-test="token-expires"
              >
                {{ stringToDate(token.expires) }}
              </td>
              <td
                class="tw-text-nowrap"
                data-test="token-created"
              >
                {{ stringToDate(token.created) }}
              </td>
              <td
                class="tw-text-nowrap"
                data-test="token-scope"
              >
                {{ formatScope(token.scope) }}
              </td>
              <td data-test="token-project">
                {{ token.project?.name ?? '' }}
              </td>
              <td>
                <button
                  class="tw-btn tw-btn-ghost tw-btn-sm"
                  title="Delete Token"
                  data-test="delete-token-button"
                  @click="deleteAuthenticationToken(token.id)"
                >
                  <font-awesome-icon :icon="FA.faTrashCan" />
                </button>
              </td>
            </tr>
            <tr v-if="!authenticationTokens?.length">
              <td
                colspan="6"
                class="tw-text-center"
                data-test="no-auth-tokens-message"
              >
                No authentication tokens to display.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </FormSection>
  </div>
</template>

<script>
import InputField from './shared/FormInputs/InputField.vue';
import SelectField from './shared/FormInputs/SelectField.vue';
import FormSection from './shared/FormSection.vue';
import gql from 'graphql-tag';
import { DateTime } from 'luxon';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faTrashCan, faPlus, faCopy } from '@fortawesome/free-solid-svg-icons';

export default {
  name: 'ProfilePage',
  components: {
    InputField,
    SelectField,
    FormSection,
    FontAwesomeIcon,
  },
  props: {
    user: {
      type: Object,
      required: true,
    },
    error: {
      type: String,
      default: '',
    },
    message: {
      type: String,
      default: '',
    },
    maxTokenExpiration: {
      type: String,
      required: true,
    },
  },

  data() {
    return {
      profileForm: {
        fname: this.user.firstname || '',
        lname: this.user.lastname || '',
        email: this.user.email || '',
        institution: this.user.institution || '',
      },
      passwordForm: {
        oldpasswd: '',
        passwd: '',
        passwd2: '',
      },
      newTokenForm: {
        description: '',
        expiration: this.maxTokenExpiration || '',
        scope: 'SUBMIT_ONLY',
        projectId: null,
      },
      newTokenValidationErrors: {},
      newTokenError: '',
      newTokenRaw: '',
      copied: false,
    };
  },

  computed: {
    csrfToken() {
      return document.head.querySelector('meta[name="csrf-token"]')?.content;
    },
    FA() {
      return {
        faTrashCan,
        faPlus,
        faCopy,
      };
    },
    todayDate() {
      return DateTime.local().toISODate();
    },
    projectOptions() {
      const options = [{ value: null, text: 'None' }];
      if (this.projects) {
        this.projects.forEach(({ node }) => {
          options.push({ value: node.id, text: node.name });
        });
      }
      return options;
    },
    isNewTokenFormValid() {
      return this.newTokenForm.description && this.newTokenForm.expiration && this.newTokenForm.scope;
    },
  },

  watch: {
    'newTokenForm.scope'(newScope) {
      if (newScope === 'FULL_ACCESS') {
        this.newTokenForm.projectId = null;
      }
    },
  },

  apollo: {
    authenticationTokens: {
      query: gql`
        query {
          me {
            authenticationTokens {
              edges {
                node {
                  id
                  description
                  scope
                  expires
                  created
                  project {
                    id
                    name
                  }
                }
              }
            }
          }
        }
      `,
      update: data => data?.me?.authenticationTokens?.edges,
    },
    projects: {
      query: gql`
        query {
          me {
            projects(first: 1000) {
              edges {
                node {
                  id
                  name
                }
              }
            }
          }
        }
      `,
      update: data => data?.me?.projects?.edges,
    },
  },

  methods: {
    stringToDate(isoString) {
      if (!isoString) {
        return '';
      }
      return DateTime.fromISO(isoString).toISODate();
    },

    formatScope(scope) {
      if (scope === 'FULL_ACCESS' || scope === 'full_access') {
        return 'Full Access';
      }
      if (scope === 'SUBMIT_ONLY' || scope === 'submit_only') {
        return 'Submit Only';
      }
      return scope;
    },

    async createAuthenticationToken() {
      this.newTokenError = '';
      this.newTokenRaw = '';
      this.newTokenValidationErrors = {};

      try {
        const response = await this.$apollo.mutate({
          mutation: gql`
            mutation createAuthenticationToken($input: CreateAuthenticationTokenInput!) {
              createAuthenticationToken(input: $input) {
                message
                rawToken
              }
            }
          `,
          variables: {
            input: {
              description: this.newTokenForm.description,
              expiration: DateTime.fromISO(this.newTokenForm.expiration, {zone: 'utc'}).plus({ days: 1 }).startOf('day').set({ millisecond: 0 }).toISO({ suppressMilliseconds: true }),
              scope: this.newTokenForm.scope,
              projectId: this.newTokenForm.projectId,
            },
          },
        });

        if (response.data.createAuthenticationToken.message) {
          this.newTokenError = response.data.createAuthenticationToken.message;
        }
        else {
          this.newTokenRaw = response.data.createAuthenticationToken.rawToken;
          this.newTokenForm = {
            description: '',
            expiration: this.maxTokenExpiration || '',
            scope: 'SUBMIT_ONLY',
            projectId: null,
          };
          await this.$apollo.queries.authenticationTokens.refetch();
        }
      }
      catch (error) {
        if (error.graphQLErrors) {
          error.graphQLErrors.forEach(e => {
            if (e.extensions && e.extensions.validation) {
              this.newTokenValidationErrors = Object.keys(e.extensions.validation).reduce((acc, key) => {
                acc[key.replace('input.', '')] = e.extensions.validation[key];
                return acc;
              }, {});
            }
            else {
              this.newTokenError = e.message;
            }
          });
        }
        else {
          this.newTokenError = error.message;
        }
      }
    },

    async deleteAuthenticationToken(tokenId) {
      try {
        await this.$apollo.mutate({
          mutation: gql`
            mutation deleteAuthenticationToken($input: DeleteAuthenticationTokenInput!) {
              deleteAuthenticationToken(input: $input) {
                message
              }
            }
          `,
          variables: {
            input: {
              tokenId: tokenId,
            },
          },
        });
        await this.$apollo.queries.authenticationTokens.refetch();
      }
      catch (error) {
        console.error(error);
      }
    },

    async copyToken() {
      try {
        await navigator.clipboard.writeText(this.newTokenRaw);
        this.copied = true;
        setTimeout(() => {
          this.copied = false;
        }, 2000);
      }
      catch (err) {
        console.error('Failed to copy: ', err);
      }
    },
  },
};
</script>
