<template>
  <sub v-if="diff > 0">
    +{{ diff }}{{ suffix }}
  </sub>
  <sub v-else-if="diff < 0">
    -{{ -diff }}{{ suffix }}
  </sub>
</template>

<script>
/**
 * The change in a coverage measurement since the previous build, if any.  The signs are written
 * out, since Vue would remove the space before the change if it started with an interpolation.
 */
export default {
  name: 'CoverageDiff',

  props: {
    diff: {
      type: Number,
      default: undefined,
    },

    suffix: {
      type: String,
      default: '',
    },
  },
};
</script>
