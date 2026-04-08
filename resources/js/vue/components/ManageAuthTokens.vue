<template>
  <loading-indicator :is-loading="!authenticationTokens">
    <data-table
      :column-groups="[
        {
          displayName: 'Authentication Tokens',
          width: 100,
        }
      ]"
      :columns="[
        {
          name: 'owner',
          displayName: 'Owner',
        },
        {
          name: 'description',
          displayName: 'Description',
          expand: true,
        },
        {
          name: 'scope',
          displayName: 'Scope',
        },
        {
          name: 'created',
          displayName: 'Created',
        },
        {
          name: 'expires',
          displayName: 'Expires',
        },
        {
          name: 'actions',
          displayName: 'Actions',
        },
      ]"
      :rows="formattedAuthTokenRows"
      :full-width="true"
      empty-table-text="No authentication tokens have been created yet."
    >
      <template #scope="{ props: { scope: scope, projectname: projectname } }">
        <template v-if="scope === 'SUBMIT_ONLY' && projectname">
          Submit Only (<a
            class="cdash-link"
            :href="$baseURL + '/index.php?project=' + projectname"
          >{{ projectname }}</a>)
        </template>
        <template v-else-if="scope === 'SUBMIT_ONLY'">
          Submit Only
        </template>
        <template v-else>
          Full Access
        </template>
      </template>
      <template #actions="{ props: { token: token } }">
        <button
          class="tw-btn tw-btn-sm tw-btn-outline tw-flex-nowrap tw-items-center tw-gap-1"
          @click="revokeToken(token)"
        >
          Revoke Token
          <font-awesome-icon :icon="FA.faTrash" />
        </button>
      </template>
    </data-table>
  </loading-indicator>
</template>
<script>

import DataTable from './shared/DataTable.vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {faTrash} from '@fortawesome/free-solid-svg-icons';
import gql from 'graphql-tag';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import {DateTime} from 'luxon';

export default {
  name: 'ManageAuthTokens',
  components: {LoadingIndicator, FontAwesomeIcon, DataTable},

  apollo: {
    authenticationTokens: {
      query: gql`
        query {
          authenticationTokens(first: 100000) {
            edges {
              node {
                id
                created
                expires
                description
                scope
                project {
                  id
                  name
                }
                user {
                  id
                  firstname
                  lastname
                }
              }
            }
          }
        }
      `,
    },
  },

  computed: {
    FA() {
      return {
        faTrash,
      };
    },

    formattedAuthTokenRows() {
      return (this.authenticationTokens?.edges ?? []).map(({node: token}) => {
        return {
          owner: `${token.user?.firstname} ${token.user?.lastname}`,
          description: token.description,
          scope: {
            scope: token.scope,
            projectname: token.project?.name,
          },
          created: this.stringToDate(token.created),
          expires: this.stringToDate(token.expires),
          actions: {
            token: token,
          },
        };
      });
    },
  },

  methods: {
    async revokeToken(token) {
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
              tokenId: token.id,
            },
          },
        });
        await this.$apollo.queries.authenticationTokens.refetch();
      }
      catch (error) {
        console.error(error);
      }
    },

    stringToDate(isoString) {
      if (!isoString) {
        return '';
      }
      return DateTime.fromISO(isoString).toISODate();
    },
  },
};
</script>
