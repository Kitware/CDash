<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-max-w-3xl tw-mx-auto tw-justify-center tw-gap-12 tw-p-4 tw-mt-8">
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
        />
        <InputField
          v-model="profileForm.lname"
          name="lname"
          label="Last Name"
        />
        <InputField
          v-model="profileForm.email"
          name="email"
          label="Email"
        />
        <InputField
          v-model="profileForm.institution"
          name="institution"
          label="Institution"
        />

        <div class="tw-flex tw-justify-end tw-mt-4">
          <input
            type="submit"
            value="Update Profile"
            name="updateprofile"
            class="tw-btn tw-btn-primary"
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
        />
        <InputField
          v-model="passwordForm.passwd"
          name="passwd"
          type="password"
          label="New Password"
        />
        <InputField
          v-model="passwordForm.passwd2"
          name="passwd2"
          type="password"
          label="Confirm Password"
        />

        <div class="tw-flex tw-justify-end tw-mt-4">
          <input
            type="submit"
            value="Update Password"
            name="updatepassword"
            class="tw-btn tw-btn-primary"
          >
        </div>
      </form>
    </FormSection>

    <FormSection title="Other Information">
      <div class="tw-flex tw-flex-col tw-gap-4">
        <div class="tw-flex tw-flex-row tw-justify-between tw-items-center">
          <span class="tw-font-bold">Internal Id</span>
          <span>{{ user.id }}</span>
        </div>
      </div>
    </FormSection>
  </div>
</template>

<script>
import InputField from './shared/FormInputs/InputField.vue';
import FormSection from './shared/FormSection.vue';

export default {
  name: 'ProfilePage',
  components: {
    InputField,
    FormSection,
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
    };
  },
  computed: {
    csrfToken() {
      return document.head.querySelector('meta[name="csrf-token"]')?.content;
    },
  },
};
</script>
