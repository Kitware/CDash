# Release Process

## Overview

This document serves as a guide for developers to follow when creating new CDash
releases.  It is critical for developers to follow this checklist exactly and
keep it up-to-date to ensure a consistent release process each time.

## Release Checklist

### Pre-release

- Verify that the latest CI pipeline passed.
- Verify that the version listed in `config/cdash.php`, `package.json`,
  and `package-lock.json` is the next version to be released.
- Check the [milestone](https://github.com/Kitware/CDash/milestones) for the
  release to ensure everything has either been completed or deferred to future
  milestones.

### Making a release

- Create a new release branch (if this is the initial release candidate for a 
  given branch)
  - Note: Set configs to match [existing branches](https://github.com/Kitware/CDash/settings/branches).
- Create an annotated tag and push it to GitHub
  - Note: Simply creating a release in GitHub is not sufficient since it
    only creates a lightweight tag.
- Verify that the new tag for the `cdash` Docker image was successfully
  pushed to [Docker Hub](https://hub.docker.com/r/kitware/cdash/tags).
- Pull the new image locally and verify that the correct version is reported at
  the bottom of the page.
  - Note: The wrong version could be reported if a lightweight tag was created
    instead of an annotated tag.
- Use GitHub to draft a [new release](https://github.com/Kitware/CDash/releases/new)
  - Select the appropriate tags and click "Generate release notes" to automatically
    generate release notes.
  - Verify that all PRs have appropriate release notes labels and none show up
    in the "Other Changes" section.
  - Select the pre-release checkbox if this is a release candidate.
  - Select the "set as latest" checkbox if this is not a release candidate.
- Publish the new release.

### Post-release

- Create a release announcement on the [Kitware blog](https://www.kitware.com/tag/cdash/).
- Update [cdash.org](https://www.cdash.org) to advertise the new version.
- Bump the version listed in `config/cdash.php`, `package.json`, and
  `package-lock.json` on master to the next expected version.
  - Note: Doing this helps differentiate between a released version and the
    version on master when handling bug reports.
- Lock the oldest release branch to prevent inadvertent changes.
- Close the oldest [milestone](https://github.com/Kitware/CDash/milestones).
- (Optional) Triage issues to determine whether any should be added to new
  milestones or closed.
