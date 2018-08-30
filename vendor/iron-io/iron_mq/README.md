IronMQ PHP Client Library
-------------

[IronMQ](http://www.iron.io/products/mq) is an elastic message queue for managing data and event flow within cloud applications and between systems.

The [full API documentation is here](http://dev.iron.io/mq/reference/api/) and this client tries to stick to the API as
much as possible so if you see an option in the API docs, you can use it in the methods below.

## Update notes

* 1.3.0 - changed argument list in methods `postMessage` and `postMessages`. Please revise code that uses these methods.
* 1.4.5 - added `getMessagePushStatuses` and `deleteMessagePushStatus` methods.
* 2.0.0 - version 2.0 introduced some backward incompatible changes. IronMQ client finally PSR-4 compatible and using namespaces & other php 5.3 stuff. If you're migrating from previous (1.x) version, please carefully check how iron_mq / iron_core classes loaded.
If you need some 1.x features like `.phar` archives, use latest 1.x stable version: https://github.com/iron-io/iron_mq_php/releases/tag/1.5.3


## Getting Started

### Get credentials

To start using iron_mq_php, you need to sign up and get an oauth token.

1. Go to http://iron.io/ and sign up.
2. Get an Oauth Token at http://hud.iron.io/tokens

--

### Install iron_mq_php

There are two ways to use iron_mq_php:

##### Using composer

Create `composer.json` file in project directory:

```json
{
    "require": {
        "iron-io/iron_mq": "2.*"
    }
}
```

Do `composer install` (install it if needed: https://getcomposer.org/download/)

And use it:

```php
require __DIR__ . '/vendor/autoload.php';

$ironmq = new \IronMQ\IronMQ();
```


##### Using classes directly (strongly not recommended)

1. Copy classes from `src` to target directory
2. Grab IronCore classes [there](https://github.com/iron-io/iron_core_php) and copy to target directory
3. Include them all.

```php
require 'src/HttpException.php';
require 'src/IronCore.php';
require 'src/IronMQ.php';
require 'src/IronMQException.php';
require 'src/IronMQMessage.php';
require 'src/JsonException.php';

$ironmq = new \IronMQ\IronMQ();
```

--

### Configure

Three ways to configure IronMQ:

* Passing array with options:

```php
<?php
$ironmq = new \IronMQ\IronMQ(array(
    "token" => 'XXXXXXXXX',
    "project_id" => 'XXXXXXXXX'
));
```
* Passing json (or ini) file name which stores your configuration options (usually `token` and `project_id`):

```php
<?php
$ironmq = new \IronMQ\IronMQ('iron.json');
```

* Automatic [config](http://dev.iron.io/mq/reference/configuration/) search -
pass zero arguments to constructor and library will try to find config file in following locations:

    * `iron.ini` in current directory
    * `iron.json` in current directory
    * `IRON_MQ_TOKEN`, `IRON_MQ_PROJECT_ID` and other environment variables
    * `IRON_TOKEN`, `IRON_PROJECT_ID` and other environment variables
    * `.iron.ini` in user's home directory
    * `.iron.json` in user's home directory

--


## The Basics

### Post a Message to the Queue

```php
<?php
$ironmq->postMessage($queue_name, "Hello world");
```

More complex example:

```php
<?php
$ironmq->postMessage($queue_name, "Test Message", array(
    "timeout" => 120, # Timeout, in seconds. After timeout, item will be placed back on queue. Defaults to 60.
    "delay" => 5, # The item will not be available on the queue until this many seconds have passed. Defaults to 0.
    "expires_in" => 2*24*3600 # How long, in seconds, to keep the item on the queue before it is deleted.
));
```

Post multiple messages in one API call:

```php
<?php
$ironmq->postMessages($queue_name, array("Message 1", "Message 2"), array(
    "timeout" => 120
));
```

--

### Get a Message off the Queue

```php
<?php
$ironmq->getMessage($queue_name);
```

When you pop/get a message from the queue, it will NOT be deleted.
It will eventually go back onto the queue after a timeout if you don't delete it (default timeout is 60 seconds).

Get multiple messages in one API call:

```php
<?php
$ironmq->getMessage($queue_name, 3);
```

--

### Delete a Message from the Queue

```php
<?php
$ironmq->deleteMessage($queue_name, $message_id);
```
Delete a message from the queue when you're done with it.

Delete multiple messages in one API call:

```php
<?php
$ironmq->deleteMessages($queue_name, array("xxxxxxxxx", "xxxxxxxxx"));
```
Delete multiple messages specified by messages id array.

--


## Troubleshooting

### http error: 0

If you see  `Uncaught exception 'Http_Exception' with message 'http error: 0 | '`
it most likely caused by misconfigured cURL https sertificates.
There are two ways to fix this error:

1. Disable SSL sertificate verification - add this line after IronMQ initialization: `$ironmq->ssl_verifypeer = false;`
2. Switch to http protocol - add this to configuration options: `protocol = http` and `port = 80`
3. Fix the error! Recommended solution: download actual certificates - [cacert.pem](http://curl.haxx.se/docs/caextract.html) and add them to `php.ini`:

```
[PHP]

curl.cainfo = "path\to\cacert.pem"
```

--


## Queues

### IronMQ Client

`IronMQ` is based on `IronCore` and provides easy access to the whole IronMQ API.

```php
<?php
$ironmq = new \IronMQ\IronMQ(array(
    "token" => 'XXXXXXXXX',
    "project_id" => 'XXXXXXXXX'
));
```

--

### List Queues

```php
<?php
$queues = $ironmq->getQueues($page, $per_page);
```

**Optional parameters:**

* `$page`: The 0-based page to view. The default is 0.
* `$per_page`: The number of queues to return per page. The default is 30, the maximum is 100.

```php
<?php
$queues_page_four = $ironmq->getQueues(3, 20); // get 4th page, 20 queues per page
```

--

### Retrieve Queue Information

```php
<?php
$qinfo = $ironmq->getQueue($queue_name);
```

--

### Delete a Message Queue

```php
<?php
$response = $ironmq->deleteQueue($queue_name);
```

--

### Post Messages to a Queue

**Single message:**

```php
<?php
$ironmq->postMessage($queue_name, "Test Message", array(
    'timeout' => 120,
    'delay' => 2,
    'expires_in' => 2*24*3600 # 2 days
));
```

**Multiple messages:**

```php
<?php
$ironmq->postMessages($queue_name, array("Lorem", "Ipsum"), array(
    "timeout" => 120,
    "delay" => 2,
    "expires_in" => 2*24*3600 # 2 days
));
```

**Optional parameters (3rd, `array` of key-value pairs):**

* `timeout`: After timeout (in seconds), item will be placed back onto queue.
You must delete the message from the queue to ensure it does not go back onto the queue.
 Default is 60 seconds. Minimum is 30 seconds. Maximum is 86,400 seconds (24 hours).

* `delay`: The item will not be available on the queue until this many seconds have passed.
Default is 0 seconds. Maximum is 604,800 seconds (7 days).

* `expires_in`: How long in seconds to keep the item on the queue before it is deleted.
Default is 604,800 seconds (7 days). Maximum is 2,592,000 seconds (30 days).

--

### Get Messages from a Queue

**Single message:**

```php
<?php
$message = $ironmq->getMessage($queue_name, $timeout, $wait);
```

**Multiple messages:**

```php
<?php
$message = $ironmq->getMessages($queue_name, $count, $timeout, $wait);
```

**Optional parameters:**

* `$count`: The maximum number of messages to get. Default is 1. Maximum is 100.

* `$timeout`: After timeout (in seconds), item will be placed back onto queue.
You must delete the message from the queue to ensure it does not go back onto the queue.
If not set, value from POST is used. Default is 60 seconds. Minimum is 30 seconds.
Maximum is 86,400 seconds (24 hours).

* `$wait`: Time in seconds to wait for a message to become available.
This enables long polling. Default is 0 (does not wait), maximum is 30.

--

### Touch a Message on a Queue

Touching a reserved message extends its timeout by the duration specified when the message was created, which is 60 seconds by default.

```php
<?php
$ironmq->touchMessage($queue_name, $message_id);
```

--

### Release Message

```php
<?php
$ironmq->releaseMessage($queue_name, $message_id, $delay);
```

**Optional parameters:**

* `$delay`: The item will not be available on the queue until this many seconds have passed.
Default is 0 seconds. Maximum is 604,800 seconds (7 days).

--

### Delete a Message from a Queue

```php
<?php
$ironmq->deleteMessage($queue_name, $message_id);
```

--

### Peek Messages from a Queue

Peeking at a queue returns the next messages on the queue, but it does not reserve them.

**Single message:**

```php
<?php
$message = $ironmq->peekMessage($queue_name);
```

**Multiple messages:**

```php
<?php
$messages = $ironmq->peekMessages($queue_name, $count);
```

--

### Clear a Queue

```php
<?php
$ironmq->clearQueue($queue_name);
```

--

### Add alerts to a queue. This is for Pull Queue only.

```php
<?php
$first_alert = array(
        'type' => 'fixed',
        'direction' => 'desc',
        'trigger' => 1001,
        'snooze' => 10,
        'queue' => 'test_alert_queue');
$second_alert = array(
        'type' => 'fixed',
        'direction' => 'asc',
        'trigger' => 1000,
        'snooze' => 5,
        'queue' => 'test_alert_queue',);

$res = $ironmq->addAlerts("test_alert_queue", array($first_alert, $second_alert));
```

### Replace current queue alerts with a given list of alerts. This is for Pull Queue only.

```php
<?php
$res = $ironmq->updateAlerts("test_alert_queue", array($first_alert, $second_alert));
```

### Remove alerts from a queue. This is for Pull Queue only.

```php
<?php
$ironmq->deleteAlerts("test_alert_queue", $alert_ids);
```

### Remove alert from a queue by its ID. This is for Pull Queue only.

```php
<?php
$ironmq->deleteAlertById("test_alert_queue", $alert_id);
```

--


## Push Queues

IronMQ push queues allow you to setup a queue that will push to an endpoint, rather than having to poll the endpoint.
[Here's the announcement for an overview](http://blog.iron.io/2013/01/ironmq-push-queues-reliable-message.html).

### Update a Message Queue

```php
<?php
$params = array(
    "push_type" => "multicast",
    "retries" => 5,
    "subscribers" => array(
        array("url" => "http://your.first.cool.endpoint.com/push"),
        array("url" => "http://your.second.cool.endpoint.com/push")
    ),
    "error_queue" => "my_error_queue_name")
);

$ironmq->updateQueue($queue_name, $params);
```

**The following parameters are all related to Push Queues:**

* `subscribers`: An array of subscriber hashes containing a “url” field.
This set of subscribers will replace the existing subscribers.
To add or remove subscribers, see the add subscribers endpoint or the remove subscribers endpoint.
See below for example json.
* `push_type`: Either `multicast` to push to all subscribers or `unicast` to push to one and only one subscriber. Default is `multicast`.
* `retries`: How many times to retry on failure. Default is 3. Maximum is 100.
* `retries_delay`: Delay between each retry in seconds. Default is 60.
* `error_queue`: The name of another queue where information about messages that can't be delivered after retrying retries number of times will be placed. Pass in an empty string to disable error queues. Default is disabled. see: [http://dev.iron.io/mq/reference/push_queues/#error_queues](http://dev.iron.io/mq/reference/push_queues/#error_queues)

--

### Add/Remove Subscribers on a Queue

Add subscriber to Push Queue:

```php
<?php
$ironmq->addSubscriber($queue_name, array(
    "url" => "http://cool.remote.endpoint.com/push"
));

$ironmq->removeSubscriber($queue_name, array(
    "url" => "http://cool.remote.endpoint.com/push"
));
```

--

### Get Message Push Status

```php
<?php
$response = $ironmq->postMessage('push me!');

$message_id = $response["ids"][0];
$statuses = $ironmq->getMessagePushStatuses($queue_name, $message_id);
```

Returns an array of subscribers with status.

--

### Acknowledge / Delete Message Push Status

```php
<?php
$statuses = $ironmq->getMessagePushStatuses($queue_name, $message_id);

foreach ($statuses as $status) {
    $ironmq->deleteMessagePushStatus($queue_name, $message_id, $status["id"]);
}
```

--

### Revert Queue Back to Pull Queue

If you want to revert your queue just update `push_type` to `"pull"`.

```php
<?php
$params = array("push_type" => "pull");

$ironmq->updateQueue($queue_name, $params);
```

--


## Further Links

* [IronMQ Overview](http://dev.iron.io/mq/)
* [IronMQ REST/HTTP API](http://dev.iron.io/mq/reference/api/)
* [Push Queues](http://dev.iron.io/mq/reference/push_queues/)
* [Other Client Libraries](http://dev.iron.io/mq/libraries/)
* [Live Chat, Support & Fun](http://get.iron.io/chat)

-------------
© 2011 - 2013 Iron.io Inc. All Rights Reserved.
