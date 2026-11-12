<template>
  <Teleport to="body">
    <div
      class="modal fade in"
      style="display: block;"
      tabindex="-1"
      role="dialog"
      @click.self="$emit('cancel')"
    >
      <div class="modal-dialog modal-sm">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">
              Remove Build
            </h4>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to remove this build?</p>
          </div>
          <div class="modal-footer">
            <button
              class="btn"
              data-test="cancel-remove-build"
              @click="$emit('cancel')"
            >
              cancel
            </button>
            <button
              id="modal-delete-build-button"
              class="btn btn-danger"
              data-test="confirm-remove-build"
              @click="$emit('confirm')"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-backdrop fade in" />
  </Teleport>
</template>

<script>
/**
 * Asks whether to remove a build.  Clicking outside the dialog or pressing escape cancels.
 */
export default {
  name: 'RemoveBuildDialog',

  emits: {
    cancel: () => true,
    confirm: () => true,
  },

  mounted() {
    document.addEventListener('keydown', this.onKeydown);
  },

  beforeUnmount() {
    document.removeEventListener('keydown', this.onKeydown);
  },

  methods: {
    onKeydown(event) {
      if (event.key === 'Escape') {
        this.$emit('cancel');
      }
    },
  },
};
</script>
