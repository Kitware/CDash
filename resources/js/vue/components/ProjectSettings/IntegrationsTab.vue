<template>
  <TabContent
    title="Integrations"
    data-test="integrations-tab"
  >
    <div>
      <LoadingIndicator :is-loading="!project">
        <div
          v-if="project && project.repositories.edges.length > 0"
          class="tw-flex tw-flex-col tw-gap-2 tw-mb-6"
        >
          <div
            v-for="{ node: repository } in project.repositories.edges"
            :key="repository.id"
            class="tw-flex tw-flex-row tw-items-center tw-justify-between tw-p-3 tw-border tw-border-base-300 tw-rounded-lg tw-bg-base-100"
          >
            <div class="tw-flex tw-flex-col sm:tw-flex-row sm:tw-items-center tw-gap-1 sm:tw-gap-6 tw-flex-grow tw-overflow-hidden">
              <div
                class="tw-flex-1 tw-font-bold"
                :title="repository.url"
              >
                {{ repository.url }}
              </div>
              <div
                class="sm:tw-w-1/4 tw-truncate tw-text-neutral-500"
                :title="repository.username"
              >
                {{ repository.username }}
              </div>
              <div
                class="sm:tw-w-1/4 tw-truncate tw-text-neutral-500"
                :title="repository.branch"
              >
                {{ repository.branch }}
              </div>
            </div>
            <button
              class="tw-btn tw-btn-ghost tw-btn-sm tw-text-error tw-ml-4"
              title="Delete Repository"
              data-test="delete-repository-button"
              @click="deleteRepository(repository.id)"
            >
              <font-awesome-icon :icon="FA.faTrashCan" />
            </button>
          </div>
        </div>
        <div
          v-else
          data-test="no-integrations-message"
        >
          No integrations configured yet.
        </div>
      </LoadingIndicator>

      <div class="tw-divider" />

      <div>
        <InputField
          v-model="form.url"
          :validation-error="validationErrors?.url?.[0]"
          label="Repository URL"
          placeholder="https://github.com/organization/repository"
          type="url"
          test-id="repository-url-input"
        />

        <InputField
          v-model="form.username"
          :validation-error="validationErrors?.username?.[0]"
          label="Username"
          description="GitHub users should put the installation ID in this field."
          test-id="repository-username-input"
        />

        <InputField
          v-model="form.password"
          :validation-error="validationErrors?.password?.[0]"
          label="Password"
          type="password"
          test-id="repository-password-input"
        />

        <InputField
          v-model="form.branch"
          :validation-error="validationErrors?.branch?.[0]"
          label="Branch"
          test-id="repository-branch-input"
        />

        <div class="tw-flex tw-items-center tw-justify-start tw-mt-4 tw-gap-4">
          <button
            class="tw-btn"
            :disabled="!formIsValid"
            data-test="create-repository-button"
            @click="createRepository"
          >
            <FontAwesomeIcon :icon="FA.faPlus" />
            Add
          </button>

          <span
            v-if="fatalError"
            class="tw-text-error"
          >
            {{ fatalError }}
          </span>
        </div>
      </div>
    </div>
  </TabContent>
</template>

<script>
import TabContent from './TabContent.vue';
import gql from 'graphql-tag';
import InputField from './InputField.vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {
  faPlus,
  faTrashCan,
} from '@fortawesome/free-solid-svg-icons';
import LoadingIndicator from '../shared/LoadingIndicator.vue';

export default {
  components: {
    LoadingIndicator,
    FontAwesomeIcon,
    InputField,
    TabContent,
  },
  props: {
    projectId: {
      type: Number,
      required: true,
    },

  },

  data() {
    return {
      validationErrors: {},
      fatalError: null,
      form: {
        url: '',
        username: '',
        password: '',
        branch: '',
      },
    };
  },

  computed: {
    FA() {
      return {
        faPlus,
        faTrashCan,
      };
    },

    formIsValid() {
      return this.form.url && this.form.username && this.form.password && this.form.branch;
    },
  },

  apollo: {
    project: {
      query: gql`
        query project($id: ID!) {
          project(id: $id) {
            id
            repositories {
              edges {
                node {
                  id
                  url
                  username
                  branch
                }
              }
            }
          }
        }
      `,
      variables() {
        return {
          id: this.projectId,
        };
      },
    },
  },

  methods: {
    async createRepository() {
      this.validationErrors = {};
      this.fatalError = null;

      try {
        const cleanForm = { ...this.form };
        delete cleanForm.__typename;
        await this.$apollo.mutate({
          mutation: gql`
            mutation createRepository($input: CreateRepositoryInput!) {
              createRepository(input: $input) {
                message
              }
            }
          `,
          variables: {
            input: {
              projectId: this.projectId,
              ...cleanForm,
            },
          },
        });
      }
      catch (error) {
        if (error.graphQLErrors) {
          error.graphQLErrors.forEach(e => {
            if (e.extensions && e.extensions.validation) {
              this.validationErrors = Object.keys(e.extensions.validation).reduce((acc, key) => {
                acc[key.replace('input.', '')] = e.extensions.validation[key];
                return acc;
              }, {});
            }
            else if (e.extensions && e.extensions.debugMessage && e.extensions.debugMessage.includes('got invalid value')) {
              const fieldMatch = e.extensions.debugMessage.match(/at "input\.([^"]+)"/);
              if (fieldMatch && fieldMatch[1]) {
                this.validationErrors[fieldMatch[1]] = ['Invalid format.'];
              }
              else {
                this.fatalError = e.extensions.debugMessage;
              }
            }
            else {
              this.fatalError = e.message;
            }
          });
        }
        else {
          this.fatalError = error.message;
        }
        return;
      }

      await this.$apollo.queries.project.refetch();

      this.form = {
        url: '',
        username: '',
        password: '',
        branch: '',
      };
    },

    async deleteRepository(repositoryId) {
      this.fatalError = null;

      try {
        await this.$apollo.mutate({
          mutation: gql`
            mutation deleteRepository($input: DeleteRepositoryInput!) {
              deleteRepository(input: $input) {
                message
              }
            }
          `,
          variables: {
            input: {
              repositoryId: repositoryId,
            },
          },
        });
      }
      catch (error) {
        if (error.graphQLErrors) {
          error.graphQLErrors.forEach(e => {
            this.fatalError = e.message;
          });
        }
        else {
          this.fatalError = error.message;
        }
        return;
      }

      await this.$apollo.queries.project.refetch();
    },
  },
};
</script>
