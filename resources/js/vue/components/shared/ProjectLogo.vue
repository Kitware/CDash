<template>
  <div class="tw-aspect-square tw-rounded tw-overflow-hidden">
    <img
      v-if="imageUrl"
      :alt="projectName + ' logo'"
      :src="imageUrl"
      class="tw-w-full tw-h-full tw-object-cover"
    >
    <div
      v-else
      class="tw-w-full tw-h-full tw-flex tw-items-center tw-justify-center"
      :class="placeholderColorClass"
    >
      <span>{{ projectName[0].toUpperCase() }}</span>
    </div>
  </div>
</template>

<script>

export default {
  props: {
    projectName: {
      type: String,
      required: true,
    },

    imageUrl: {
      type: [String, null],
      required: true,
    },
  },

  computed: {
    placeholderColorClass() {
      const colors = [
        { bg: 'tw-bg-blue-100', text: 'tw-text-blue-800' },
        { bg: 'tw-bg-green-100', text: 'tw-text-green-800' },
        { bg: 'tw-bg-red-100', text: 'tw-text-red-800' },
        { bg: 'tw-bg-purple-100', text: 'tw-text-purple-800' },
        { bg: 'tw-bg-pink-100', text: 'tw-text-pink-800' },
      ];

      // We always want project names to map to the same color.
      let hash = 0;
      for (let i = 0; i < this.projectName.length; i++) {
        hash += this.projectName.charCodeAt(i);
      }
      const color = colors[hash % colors.length];
      return [color.bg, color.text];
    },
  },
};
</script>
