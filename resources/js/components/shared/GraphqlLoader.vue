<template>
  <loading-indicator :is-loading="isLoading">
    <span
      v-if="error"
      data-cy="loading-error-message"
    >
      An error occurred while querying the API!
    </span>
    <slot
      v-else
      :data="data"
    />
  </loading-indicator>
</template>

<script>
import LoadingIndicator from './LoadingIndicator.vue';

export default {
  name: 'GraphqlLoader',

  components: {LoadingIndicator},

  props: {
    query: {
      type: String,
      default: '',
    },
    params: {
      type: Object,
      default: undefined,
    },
  },

  data() {
    return {
      isLoading: true,
      data: undefined,
      error: false,
    };
  },

  async mounted() {
    this.isLoading = true;

    await this.$axios.post(`${this.$baseURL}/graphql`, {
      query: this.query,
      variables: this.params,
    })
      .then((response) => {
        if (!response || response.data.errors || !response.data.data) {
          try {
            // This was probably a GraphQL issue.  Log just the message for convenience.
            // Network errors will fall into the axios catch() callback.
            console.error(`GraphQL error: ${response.data.errors[0].message}`);
          }
          catch (e) {
            // If it wasn't an obvious GraphQL issue we can parse, just log the entire response object.
            console.error(response);
          }
          this.error = true;
        }
        else {
          this.data = response.data.data;
        }
      })
      .catch(() => {
        this.error = true;
      });

    this.isLoading = false;
  },
};
</script>

