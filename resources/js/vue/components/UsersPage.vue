<template>
  <div
    class="tw-flex tw-flex-col tw-gap-4"
    data-test="users-page"
  >
    <div
      v-if="canInviteUsers"
      class="tw-flex tw-flex-row tw-w-full"
    >
      <button
        class="tw-ml-auto tw-btn"
        data-test="invite-users-button"
        onclick="invite_users_modal.showModal()"
      >
        Invite Users
      </button>
      <dialog
        id="invite_users_modal"
        data-test="invite-users-modal"
        class="tw-modal"
      >
        <div class="tw-modal-box tw-flex tw-flex-col tw-gap-4 tw-w-full">
          <h3 class="tw-text-lg tw-font-bold">
            Invite Users
          </h3>
          <div
            v-if="inviteUsersModalError"
            class="tw-text-error tw-font-bold"
            data-test="invite-users-modal-error-text"
          >
            {{ inviteUsersModalError }}
          </div>
          <div>
            <div class="tw-label tw-font-bold">
              Email
            </div>
            <label class="tw-input tw-input-bordered tw-flex tw-items-center tw-w-full tw-gap-2">
              <font-awesome-icon :icon="FA.faEnvelope" />
              <input
                v-model="inviteUsersModalEmail"
                type="email"
                class="tw-grow"
                placeholder="example@example.com"
                required
                data-test="invite-users-modal-email"
              >
            </label>
          </div>
          <div>
            <div class="tw-label tw-font-bold">
              Role
            </div>
            <select
              v-model="inviteUsersModalRole"
              class="tw-select tw-select-bordered tw-w-full"
              data-test="invite-users-modal-role"
            >
              <option
                value="ADMINISTRATOR"
              >
                Administrator
              </option>
              <option
                value="USER"
              >
                User
              </option>
            </select>
          </div>
          <form
            method="dialog"
            class="tw-flex tw-flex-row tw-w-full tw-gap-2"
          >
            <button
              class="tw-btn tw-ml-auto"
              data-test="invite-users-modal-cancel-button"
            >
              Cancel
            </button>
            <button
              class="tw-btn tw-btn-primary"
              :disabled="inviteUsersModalEmail.length === 0"
              data-test="invite-users-modal-invite-button"
              @click="inviteUserByEmail"
            >
              Invite
            </button>
          </form>
        </div>
        <form
          method="dialog"
          class="tw-modal-backdrop"
        >
          <button>Cancel</button>
        </form>
      </dialog>
    </div>
    <loading-indicator
      v-if="canInviteUsers"
      :is-loading="!invitations"
    >
      <data-table
        :column-groups="[
          {
            displayName: 'Invitations',
            width: 100,
          }
        ]"
        :columns="[
          {
            name: 'email',
            displayName: 'Email',
          },
          {
            name: 'role',
            displayName: 'Role',
          },
          {
            name: 'invitedBy',
            displayName: 'Invited By',
          },
          {
            name: 'invitationTimestamp',
            displayName: 'Invitation Timestamp',
          },
          {
            name: 'actions',
            displayName: 'Actions'
          }
        ]"
        :rows="formattedInvitationRows"
        :full-width="true"
        initial-sort-column="invitationTimestamp"
        :initial-sort-asc="false"
        test-id="invitations-table"
      >
        <template #actions="{ props: { invite: invite } }">
          <button
            class="tw-btn tw-btn-sm tw-btn-outline"
            data-test="revoke-invitation-button"
            @click="revokeInvitation(invite)"
          >
            Revoke Invitation <font-awesome-icon :icon="FA.faTrash" />
          </button>
        </template>
      </data-table>
    </loading-indicator>
    <loading-indicator :is-loading="!users">
      <data-table
        :column-groups="[
          {
            displayName: 'Users',
            width: 100,
          }
        ]"
        :columns="[
          {
            name: 'name',
            displayName: 'Name',
          },
          {
            name: 'email',
            displayName: 'Email',
          },
          {
            name: 'institution',
            displayName: 'Institution',
          },
          {
            name: 'role',
            displayName: 'Role',
          },
        ].concat(
          me && me.admin ? [{
            name: 'actions',
            displayName: 'Actions',
          }] : []
        )"
        :rows="formattedUserRows"
        :full-width="true"
        test-id="users-table"
      >
        <template #role="{ props: { user: user } }">
          <select
            v-if="me && parseInt(user.id) !== parseInt(me.id) && me.admin"
            class="tw-select tw-select-bordered tw-w-full tw-select-sm"
            :data-test="'role-select-' + user.id"
            @change="changeUserRole($event, user)"
          >
            <option
              value="ADMINISTRATOR"
              :selected="user.admin"
            >
              Administrator
            </option>
            <option
              value="USER"
              :selected="!user.admin"
            >
              User
            </option>
          </select>
          <span
            v-else
            :data-test="'role-text-' + user.id"
          >
            {{ user.admin ? 'Administrator' : 'User' }}
          </span>
        </template>
        <template #actions="{ props: { user: user } }">
          <button
            v-if="me && parseInt(user.id) !== parseInt(me.id)"
            class="tw-btn tw-btn-sm tw-btn-outline"
            :data-test="'remove-user-button-' + user.id"
            @click="removeUser(user)"
          >
            Remove User <font-awesome-icon :icon="FA.faTrash" />
          </button>
          <span v-else />
        </template>
      </data-table>
    </loading-indicator>
  </div>
</template>

<script>

import gql from 'graphql-tag';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import DataTable from './shared/DataTable.vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { DateTime } from 'luxon';
import {
  faTrash,
  faEnvelope,
} from '@fortawesome/free-solid-svg-icons';

export default {
  components: {
    DataTable,
    LoadingIndicator,
    FontAwesomeIcon,
  },

  props: {
    canInviteUsers: {
      type: Boolean,
      required: true,
    },
  },

  data() {
    return {
      inviteUsersModalEmail: '',
      inviteUsersModalRole: 'USER',
      inviteUsersModalError: null,
    };
  },

  apollo: {
    users: {
      query: gql`
        query users($after: String) {
          users(after: $after) {
            edges {
              node {
                id
                email
                firstname
                lastname
                institution
                admin
              }
            }
            pageInfo {
              hasNextPage
              hasPreviousPage
              startCursor
              endCursor
            }
          }
        }
      `,
      result({data}) {
        if (data && data.users.pageInfo.hasNextPage) {
          this.$apollo.queries.users.fetchMore({
            variables: {
              after: data.users.pageInfo.endCursor,
            },
          });
        }
      },
    },

    me: {
      query: gql`
        query {
          me {
            id
            email
            firstname
            lastname
            institution
            admin
          }
        }
      `,
    },

    invitations: {
      query: gql`
        query invitations($after: String) {
          invitations(after: $after) {
            edges {
              node {
                id
                email
                role
                invitedBy {
                  id
                  firstname
                  lastname
                }
                invitationTimestamp
              }
            }
            pageInfo {
              hasNextPage
              hasPreviousPage
              startCursor
              endCursor
            }
          }
        }
      `,
      result({data}) {
        if (data && data.invitations.pageInfo.hasNextPage) {
          this.$apollo.queries.invitations.fetchMore({
            variables: {
              after: data.invitations.pageInfo.endCursor,
            },
          });
        }
      },
    },
  },

  computed: {
    FA() {
      return {
        faTrash,
        faEnvelope,
      };
    },

    formattedUserRows() {
      return this.users.edges?.map(edge => {
        return {
          id: edge.node.id,
          name: `${edge.node.firstname} ${edge.node.lastname}`,
          email: edge.node.email ?? '',
          institution: edge.node.institution,
          role: {
            user: edge.node,
          },
          actions: {
            user: edge.node,
          },
        };
      });
    },

    formattedInvitationRows() {
      return this.invitations.edges?.map(edge => {
        return {
          email: edge.node.email,
          role: this.humanReadableRole(edge.node.role),
          invitedBy: `${edge.node.invitedBy.firstname} ${edge.node.invitedBy.lastname}`,
          invitationTimestamp: DateTime.fromISO(edge.node.invitationTimestamp).toLocaleString(DateTime.DATETIME_MED),
          actions: {
            invite: edge.node,
          },
        };
      });
    },
  },

  methods: {
    changeUserRole(event, user) {
      this.$apollo.mutate({
        mutation: gql`mutation ($userId: ID!, $role: GlobalRole!) {
          changeGlobalRole(input: {
            userId: $userId
            role: $role
          }) {
            message
          }
        }`,
        variables: {
          userId: user.id,
          role: event.target.value,
        },
        // Strictly speaking, we should update any relevant queries here.  It doesn't mean anything
        // to do so currently though, so we'll leave this as a to do until something actually needs the results.
      }).catch((error) => {
        console.error(error);
      });
    },

    inviteUserByEmail(event) {
      this.inviteUsersModalError = null;
      event.preventDefault();
      this.$apollo.mutate({
        mutation: gql`mutation ($email: String!, $role: GlobalRole!) {
          createGlobalInvitation(input: {
            email: $email
            role: $role
          }) {
            message
            invitedUser {
              id
              email
              role
              invitedBy {
                id
                firstname
                lastname
              }
              invitationTimestamp
            }
          }
        }`,
        variables: {
          email: this.inviteUsersModalEmail,
          role: this.inviteUsersModalRole,
        },
        updateQueries: {
          invitations(prev, { mutationResult }) {
            if (mutationResult.data.createGlobalInvitation.message !== null) {
              return prev;
            }

            const data = JSON.parse(JSON.stringify(prev));
            data.invitations.edges.push({
              __typename: 'GlobalInvitationEdge',
              node: {
                ...mutationResult.data.createGlobalInvitation.invitedUser,
              },
            });
            return data;
          },
        },
      }).then((mutationResult) => {
        if (mutationResult.data.createGlobalInvitation.message !== null) {
          this.inviteUsersModalError = mutationResult.data.createGlobalInvitation.message;
        }
        else {
          invite_users_modal.close();
          this.inviteUsersModalEmail = '';
          this.inviteUsersModalRole = 'USER';
        }
      }).catch((error) => {
        console.error(error);
      });
    },

    revokeInvitation(invitation) {
      this.$apollo.mutate({
        mutation: gql`mutation ($invitationId: ID!) {
          revokeGlobalInvitation(input: {
            invitationId: $invitationId
          }) {
            message
          }
        }`,
        variables: {
          invitationId: invitation.id,
        },
        updateQueries: {
          invitations(prev) {
            const data = JSON.parse(JSON.stringify(prev));
            const prevLength = data.invitations.edges.length;
            data.invitations.edges = data.invitations.edges.filter(({ node }) => node.id !== invitation.id);
            console.assert(data.invitations.edges.length === prevLength - 1);
            return data;
          },
        },
      }).catch((error) => {
        console.error(error);
      });
    },

    removeUser(user) {
      this.$apollo.mutate({
        mutation: gql`mutation ($userId: ID!) {
          removeUser(input: {
           userId: $userId
          }) {
            message
          }
        }`,
        variables: {
          userId: user.id,
        },
        updateQueries: {
          users(prev) {
            const data = JSON.parse(JSON.stringify(prev));
            const prevLength = data.users.edges.length;
            data.users.edges = data.users.edges.filter(({ node }) => node.id !== user.id);
            console.assert(data.users.edges.length === prevLength - 1);
            return data;
          },
        },
      }).catch((error) => {
        console.error(error);
      });
    },

    humanReadableRole(role) {
      return String(role).at(0) + String(role).slice(1).toLowerCase();
    },
  },
};
</script>
