# CDash Notifications

This document is incomplete and is subject to change.

## Definitions

### Actionable

This is a term applied to CTest submissions that require post persistence processing. The list of currently actionable CTest submissions:
* Build
* Configure
* Done
* DynamicAnalysis
* Test
* Update

### Commit Author

In git, the person who pushes the change may not be the same person who authors the change. The person who pushes the change shall be known in this document as the commit author.

### Message Decorator

A decorator decorates the message with text as described by the topic.

### Notification

The notification is the message containing the information intrinsic to the topic. This is usually an email, but we  abstract the concept to support other messaging systems, SMS and Slack for example.

### Preferences

The topics a subscriber is subscribed to.

### Subscription

A subscription is a collection of topics, the subscriber to those topics (the user), and the resultant notification for the subscriber.

### Topic

A user subscribes to topics. Examples of topics are test failures, label subscriptions, build fixes, builds from a particular build group, etc. Any criteria that triggers a message shall be known as a topic. Topics are decorators, meaning, they may be composed of other topics allowing combinations of topics to determine if the subscription preferences of a user align with the topics intrinsic to the submitted document (the CTest submission). For instance, a user may wish to receive notifications about test failures, however, that users only wishes to receive those test failures for which the user has authored. The consequence of these preferences is a topic composed of both a TestFailureTopic as well as AuthoredTopic that will filter out all test failures which are not authored by the user.  

## High Level Overview

When a CTest document is submitted is submitted the XML handler responsible for creating the CTest submission is passed to a subscription builder. The subscription builder determines the build's project users and the topics that they are subscribed, then proceeds to check the build submission for criteria matching that of each of the subscribers topics. Once the subscriptions have been created and notification builder, in our first case, an email builder is created, it is passed the subscriptions and an email message for each subscriber is constructed. Those messages are then passed to a transport for delivery.

## Code Examples

Steps to build a collection of notifications:

```php
$subscriptions = new SubscriptionsCollection();

// $handler is a AbstractHandler implementing ActionableBuildInterface
// (e.g. BuildHandler, or UpdateHandler, etc.)

// A collection of subscription builders is necessary because there are various
// ways by which to build a subscription. Currently CDash has two implementations
// of SubscriptionBuilderInterface, one that builds subscriptions for users and
// another that builds subscriptions for commit authors. Because each handler
// understands what sorts of subscriptions may be built from the content it 
// handles, each handler contains a SubscriptionBuilderCollection factory method.

$builders = $handler->GetSubscriptionBuilderCollection();
foreach ($builders as $builder) {
  // We pass the SubscriptionsCollection to the builder so that it can add its
  // type of subscriptions to it. See Subscription Creation for a more detailed
  // explanation of what actually happens during this build process
  $builder->build($subscriptions);
}

  
// Create the subscriptions
$subscriptionBuilder = new SubscriptionBuilder($actionableBuild, $project);  
  
/** @var SubscriptionCollection $subscriptions */
$subscriptions = $subscriptionBuilder->build();  
  
// Create the notifications
$director = new NotificationDirector();
$emailBuilder = new EmailBuilder(new EmailNotificationFactory(), new NotificationCollection());
$emailBuilder
  ->setSubscriptions($subscriptions)
  ->setProject($project);
  
/** @var NotificationCollection $emails */
$emails = $director->build($emailBuilder);
```

## Subscription Creation

Each class implementing the SubscriptionBuilderInterface has its own criteria for determining what subscriptions are added to the SubscriptionCollection. However a general overview of that criteria might look something like the following:
* Determine the subscriber base, e.g. users or commit authors.
* Gather subscription info based on project, build group, and or user settings, etc.
* Determine if the submission matches any of the criteria gathered from project, build group, and user settings.
* Add the subscriptions with matching criteria to the SubscriptionCollection.

### SubscriptionBuilder
```php
/**
 * @return SubscriptionCollection
 */
public function build()
{
    ...
    foreach ($subscribers as $subscriber) {
        /** @var SubscriberInterface $subscriber */
        if ($subscriber->hasBuildTopics($this->build)) {
            $subscription = $factory->create();
            $subscription
                ->setSubscriber($subscriber)
                ->setTopicCollection($subscriber->getTopics())
                ->setProject($this->project);

            $subscriptions->add($subscription);
        }
    }
    return $subscriptions;
}
```
### Subscriber

A subscriber is some entity that is able to subscribe a submission via topics. Currently CDash has two implementations of a Subscriber; 1) a user; 2) a commit author. Users' topics are set by evaluating the preferences they've set through the CDash UI. Commit authors have a set of topics with which they are provided. Subscribers' topics are evaluated against an actionable submission to determine if the Subscriber is subscribed to any given submission. Example:

```php
/**
 * @param ActionableBuildInterface $handler
 * @return bool
 */
public function hasBuildTopics(ActionableBuildInterface $handler)
{
    $topics = $this->getTopics();
    
    // First we ask the $handler if this subscriber subscribes to its content;
    // it does this by evaluating the subscribers' preferences.
    $collection = $submission->GetTopicCollectionForSubscriber($this);
    
    if ($collection->hasItems()) {
        $builds = $submission->GetBuildCollection();
        
        // Next we decorate the topics in our collection with the Subscribers'
        // more granular topics
        TopicDecorator::decorate($collection, $this->preferences);
        
        // Then cycle through each topic checking to see if the individual
        // builds in the submission match the topics' refined criteria.
        foreach ($collection as $topic) {
            $topic->setSubscriber($this);
            foreach ($builds as $build) {
                if ($topic->subscribesToBuild($build)) {
                    $topic->addBuild($build);
                    $topics->add($topic);
                }
            }
        }
    }
    
    return $topics->count() > 0;
}
```
### TopicDecorator

The TopicDecorator acts somewhat like a builder. Its `decorate` method accepts the collection of topics intrinsic to the current submission and a `NotificationsPreferences` object which it uses to how or if to further refine the submission topics. For instance, given a user who wishes to see tests that have been fixed for any given submission, and a submission that contains fixed tests the `TopicDecorator::decorate` method will decorate the appropriate Topic with another topic that further refines that topic to include tests that are fixed.

### Topic (for instance, TestFailureTopic)
```php
/**
 * @param Build $build
 * @return bool
 */
public function subscribesToBuild(Build $build)
{
    return $build->GetNumberOfFailedTests() > 0;
}
 ```

## Notification Creation
### EmailBuilder
```php
/**
 * @param SubscriptionInterface $subscription
 * @param string $templateName
 * @return EmailMessage|NotificationInterface|null
 */
public function createNotifications(SubscriptionInterface $subscription, $templateName)
{
    $message = null;
    $blade = new Blade((array)$this->templateDirectory, $this->cacheDirectory);
    $data = ['subscription' => $subscription];
    $subject = $blade->make("{$templateName}.subject", $data);
    $body = $blade->make($templateName, $data);
    $recipient = $subscription->getSubscriber()->getAddress();
    /** @var EmailMessage $message */
    $message = $this->factory->create();
    $message->setSubject($subject)
        ->setBody($body)
        ->setRecipient($recipient);
    // todo: this doesn't really belong here, refactor asap
    $this->setBuildEmailCollection($message, $subscription);
    return $message;
}
```
