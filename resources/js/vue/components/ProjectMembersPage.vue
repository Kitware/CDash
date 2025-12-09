<template>
  <div
    class="tw-flex tw-flex-col tw-gap-4"
    data-test="project-members-page"
  >
    <div class="tw-flex tw-flex-row tw-w-full tw-gap-2 tw-justify-end">
      <button
        v-if="canInviteUsers"
        class="tw-btn"
        data-test="invite-members-button"
        onclick="invite_members_modal.showModal()"
      >
        Invite Members
      </button>
      <button
        v-if="canJoinProject"
        class="tw-btn tw-btn-outline tw-btn-success"
        data-test="join-project-button"
        @click="joinProject"
      >
        Join Project
      </button>
      <button
        v-if="canLeaveProject"
        class="tw-btn tw-btn-outline tw-btn-error"
        data-test="leave-project-button"
        onclick="leave_project_modal.showModal()"
      >
        Leave Project
      </button>
    </div>
    <dialog
      id="leave_project_modal"
      data-test="leave-project-modal"
      class="tw-modal"
    >
      <div class="tw-modal-box tw-flex tw-flex-col tw-gap-4 tw-w-full">
        <h3 class="tw-text-lg tw-font-bold">
          Confirm
        </h3>
        Are you sure you want to leave this project?
        <form
          method="dialog"
          class="tw-flex tw-flex-row tw-w-full tw-gap-2"
        >
          <button
            class="tw-btn tw-ml-auto"
            data-test="leave-project-modal-cancel-button"
          >
            Cancel
          </button>
          <button
            class="tw-btn tw-btn-error"
            data-test="leave-project-modal-button"
            @click="leaveProject"
          >
            Leave
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
    <dialog
      id="invite_members_modal"
      data-test="invite-members-modal"
      class="tw-modal"
    >
      <div class="tw-modal-box tw-flex tw-flex-col tw-gap-4 tw-w-full">
        <h3 class="tw-text-lg tw-font-bold">
          Invite Members
        </h3>
        <div
          v-if="inviteMembersModalError"
          class="tw-text-error tw-font-bold"
          data-test="invite-members-modal-error-text"
        >
          {{ inviteMembersModalError }}
        </div>
        <div>
          <div class="tw-label tw-font-bold">
            Email
          </div>
          <label class="tw-input tw-input-bordered tw-flex tw-items-center tw-w-full tw-gap-2">
            <font-awesome-icon :icon="FA.faEnvelope" />
            <input
              v-model="inviteMembersModalEmail"
              type="email"
              class="tw-grow"
              placeholder="example@example.com"
              required
              data-test="invite-members-modal-email"
            >
          </label>
        </div>
        <div>
          <div class="tw-label tw-font-bold">
            Role
          </div>
          <select
            v-model="inviteMembersModalRole"
            class="tw-select tw-select-bordered tw-w-full"
            data-test="invite-members-modal-role"
          >
            <option
              v-for="type in USER_TYPES"
              :value="type"
            >
              {{ humanReadableRole(type) }}
            </option>
          </select>
        </div>
        <form
          method="dialog"
          class="tw-flex tw-flex-row tw-w-full tw-gap-2"
        >
          <button
            class="tw-btn tw-ml-auto"
            data-test="invite-members-modal-cancel-button"
          >
            Cancel
          </button>
          <button
            class="tw-btn tw-btn-primary"
            :disabled="inviteMembersModalEmail.length === 0"
            data-test="invite-members-modal-invite-button"
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
    <loading-indicator
      v-if="canInviteUsers"
      :is-loading="!projectInvitations"
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
    <loading-indicator :is-loading="!projectAdministrators || !projectUsers">
      <data-table
        :column-groups="[
          {
            displayName: 'Members',
            width: 100,
          }
        ]"
        :columns="[
          {
            name: 'name',
            displayName: 'Name',
          },
          {
            name: 'role',
            displayName: 'Role',
          },
        ].concat(
          canRemoveUsers ? [{
            name: 'actions',
            displayName: 'Actions',
          }] : []
        )"
        :rows="formattedUserRows"
        :full-width="true"
        test-id="members-table"
      >
        <template #role="{ props: { user: user, value: role } }">
          <select
            v-if="canInviteUsers && parseInt(user.id) !== parseInt(userId)"
            class="tw-select tw-select-bordered tw-w-full tw-select-sm"
            data-test="role-select"
            @change="changeUserRole($event, user)"
          >
            <option
              v-for="type in USER_TYPES"
              :value="type"
              :selected="role === type"
            >
              {{ humanReadableRole(type) }}
            </option>
          </select>
          <span
            v-else
            data-test="role-text"
          >
            {{ humanReadableRole(role) }}
          </span>
        </template>
        <template #actions="{ props: { user: user } }">
          <button
            v-if="parseInt(user.id) !== parseInt(userId)"
            class="tw-btn tw-btn-sm tw-btn-outline"
            data-test="remove-user-button"
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
import {
  faEnvelope,
  faTrash,
} from '@fortawesome/free-solid-svg-icons';
import { DateTime } from 'luxon';

export default {
  components: {
    DataTable,
    LoadingIndicator,
    FontAwesomeIcon,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },

    userId: {
      type: [Number, null],
      required: true,
    },

    canInviteUsers: {
      type: Boolean,
      required: true,
    },

    canRemoveUsers: {
      type: Boolean,
      required: true,
    },

    canJoinProject: {
      type: Boolean,
      required: true,
    },

    canLeaveProject: {
      type: Boolean,
      required: true,
    },
  },

  setup(props) {
    // Basic sanity checks...
    console.assert(!(props.canJoinProject && props.canLeaveProject));
  },

  data() {
    const user_types = Object.freeze({
      USER: 'USER',
      ADMINISTRATOR: 'ADMINISTRATOR',
    });

    return {
      USER_TYPES: user_types,
      inviteMembersModalEmail: '',
      inviteMembersModalRole: user_types.USER,
      inviteMembersModalError: null,
    };
  },

  apollo: {
    projectAdministrators: {
      query: gql`
        query projectAdministrators($projectid: ID, $after: String) {
          project(id: $projectid) {
            id
            administrators(after: $after) {
              edges {
                node {
                  id
                  firstname
                  lastname
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
        }
      `,
      update: data => data?.project?.administrators,
      variables() {
        return {
          projectid: this.projectId,
        };
      },
      result({data}) {
        if (data && data.project.administrators.pageInfo.hasNextPage) {
          this.$apollo.queries.projectAdministrators.fetchMore({
            variables: {
              projectid: this.projectId,
              after: data.project.administrators.pageInfo.endCursor,
            },
          });
        }
      },
    },

    projectUsers: {
      query: gql`
        query projectUsers($projectid: ID, $after: String) {
          project(id: $projectid) {
            id
            basicUsers(after: $after) {
              edges {
                node {
                  id
                  firstname
                  lastname
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
        }
      `,
      update: data => data?.project?.basicUsers,
      variables() {
        return {
          projectid: this.projectId,
        };
      },
      result({data}) {
        if (data && data.project.basicUsers.pageInfo.hasNextPage) {
          this.$apollo.queries.projectUsers.fetchMore({
            variables: {
              projectid: this.projectId,
              after: data.project.basicUsers.pageInfo.endCursor,
            },
          });
        }
      },
    },

    projectInvitations: {
      query: gql`
        query projectInvitations($projectid: ID, $after: String) {
          project(id: $projectid) {
            id
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
        }
      `,
      update: data => data?.project?.invitations,
      variables() {
        return {
          projectid: this.projectId,
        };
      },
      result({data}) {
        if (data && data.project.invitations.pageInfo.hasNextPage) {
          this.$apollo.queries.projectInvitations.fetchMore({
            variables: {
              projectid: this.projectId,
              after: data.project.invitations.pageInfo.endCursor,
            },
          });
        }
      },
      skip() {
        return !this.canInviteUsers;
      },
    },
  },

  computed: {
    FA() {
      return {
        faEnvelope,
        faTrash,
      };
    },

    formattedUserRows() {
      return this.projectAdministrators.edges?.map(edge => {
        return {
          name: `${edge.node.firstname} ${edge.node.lastname}`,
          role: {
            value: this.USER_TYPES.ADMINISTRATOR,
            user: edge.node,
          },
          actions: {
            user: edge.node,
          },
        };
      }).concat(this.projectUsers.edges?.map(edge => {
        return {
          name: `${edge.node.firstname} ${edge.node.lastname}`,
          role: {
            value: this.USER_TYPES.USER,
            user: edge.node,
          },
          actions: {
            user: edge.node,
          },
        };
      }));
    },

    formattedInvitationRows() {
      return this.projectInvitations.edges?.map(edge => {
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
        mutation: gql`mutation ($userId: ID!, $projectId: ID!, $role: ProjectRole!) {
          changeProjectRole(input: {
            userId: $userId
            projectId: $projectId
            role: $role
          }) {
            message
          }
        }`,
        variables: {
          userId: user.id,
          projectId: this.projectId,
          role: event.target.value,
        },
        // Strictly speaking, we should update any relevant queries here.  It doesn't mean anything
        // to do so currently though, so we'll leave this as a to do until something actually needs the results.
      }).catch((error) => {
        console.error(error);
      });
    },

    inviteUserByEmail(event) {
      this.inviteMembersModalError = null;
      event.preventDefault();
      this.$apollo.mutate({
        mutation: gql`mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
          inviteToProject(input: {
            email: $email
            projectId: $projectId
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
          email: this.inviteMembersModalEmail,
          projectId: this.projectId,
          role: this.inviteMembersModalRole,
        },
        updateQueries: {
          projectInvitations(prev, { mutationResult }) {
            if (mutationResult.data.inviteToProject.message !== null) {
              return prev;
            }

            const data = JSON.parse(JSON.stringify(prev));
            data.project.invitations.edges.push({
              __typename: 'ProjectInvitationEdge',
              node: {
                ...mutationResult.data.inviteToProject.invitedUser,
              },
            });
            return data;
          },
        },
      }).then((mutationResult) => {
        if (mutationResult.data.inviteToProject.message !== null) {
          this.inviteMembersModalError = mutationResult.data.inviteToProject.message;
        }
        else {
          // eslint-disable-next-line no-undef
          invite_members_modal.close();
          this.inviteMembersModalEmail = '';
          this.inviteMembersModalRole = this.USER_TYPES.USER;
        }
      }).catch((error) => {
        console.error(error);
      });
    },

    revokeInvitation(invitation) {
      this.$apollo.mutate({
        mutation: gql`mutation ($invitationId: ID!) {
          revokeProjectInvitation(input: {
            invitationId: $invitationId
          }) {
            message
          }
        }`,
        variables: {
          invitationId: invitation.id,
        },
        updateQueries: {
          projectInvitations(prev) {
            const data = JSON.parse(JSON.stringify(prev));
            const prevLength = data.project.invitations.edges.length;
            data.project.invitations.edges = data.project.invitations.edges.filter(({ node }) => node.id !== invitation.id);
            console.assert(data.project.invitations.edges.length === prevLength - 1);
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

    removeUser(user) {
      this.$apollo.mutate({
        mutation: gql`mutation ($userId: ID!, $projectId: ID!) {
          removeProjectUser(input: {
            userId: $userId
            projectId: $projectId
          }) {
            message
          }
        }`,
        variables: {
          userId: user.id,
          projectId: this.projectId,
        },
        updateQueries: {
          projectUsers(prev) {
            const data = JSON.parse(JSON.stringify(prev));
            data.project.basicUsers.edges = data.project.basicUsers.edges.filter(({ node }) => node.id !== user.id);
            return data;
          },
          projectAdministrators(prev) {
            const data = JSON.parse(JSON.stringify(prev));
            data.project.administrators.edges = data.project.administrators.edges.filter(({ node }) => node.id !== user.id);
            return data;
          },
        },
      }).catch((error) => {
        console.error(error);
      });
    },

    joinProject() {
      this.$apollo.mutate({
        mutation: gql`mutation ($projectId: ID!) {
          joinProject(input: {
            projectId: $projectId
          }) {
            message
          }
        }`,
        variables: {
          projectId: this.projectId,
        },
      }).catch((error) => {
        console.error(error);
      }).then(() => {
        window.location.reload();
      });
    },

    leaveProject() {
      this.$apollo.mutate({
        mutation: gql`mutation ($userId: ID!, $projectId: ID!) {
          removeProjectUser(input: {
            userId: $userId
            projectId: $projectId
          }) {
            message
          }
        }`,
        variables: {
          userId: this.userId,
          projectId: this.projectId,
        },
      }).catch((error) => {
        console.error(error);
      }).then(() => {
        window.location.reload();
      });
    },
  },
};
</script>
