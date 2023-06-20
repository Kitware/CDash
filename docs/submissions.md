# Submission parsing

CDash stores submitted files in the following directories:

| Directory  | Description |
| ---------- | ----------- |
| `storage/app/inbox` | submissions waiting to be parsed |
| `storage/app/parsed` | submissions that have been successfully parsed |
| `storage/app/failed` | submissions that CDash failed to parse |
| `storage/app/inprogress` | submissions that CDash is actively parsing |

CDash has two primary methods of parsing submissions: synchronously and asychronously.

## Synchronous submission parsing

This is the easiest method to setup, but it can be error-prone under heavy load.

Synchronous submission parsing is unsuitable for CDash installations that are expecting to receive many submissions over a short period of time.

To configure your CDash installation to parse submissions synchronously, set `QUEUE_CONNECTION=sync` in your .env file. This is the default value.

## Asynchronous submission parsing

Asychronous submission parsing is the correct choice for CDash installations that receive many submissions, especially if large bursts of submissions are expected to occur during a short time period.

To configure your CDash installation to perform asychronous submission parsing, you will need to do the following:

1. Set `QUEUE_CONNECTION=database` in your .env file
2. Make sure the queue worker is running. This command can be started by running `php artisan queue:work` but it is better to use a tool like systemd to make sure this process is running automaticaly in the background.

To do so, create a file named `/etc/systemd/system/cdash-worker@.service` with the following contents:

```
[Unit]
Description=CDash queue worker #%i

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/CDash
Restart=on-failure
RestartSec=5s
ExecStart=/usr/bin/php artisan queue:work

[Install]
WantedBy=multi-user.target
```

Notice that this example assumes that CDash's source code is located at `/var/www/CDash`,
and that the web server user is `www-data`. Modify these details as necesssary.

Once you've created this file, use `systemctl` to enable and start as many copies
of this service as you'd like.

```
systemctl enable cdash-worker@1
systemctl start cdash-worker@1
systemctl enable cdash-worker@2
systemctl start cdash-worker@2
(etc.)
```

### Parallel workers

As we showed in the example above, it is okay to have multiple queue workers
running simultaneously. This is particularly useful for CDash instances that
receive heavy submission traffic, particularly if some of those submissions
contain lots of data that can take a long time to parse.

### Remote workers

CDash support queue workers that run on separate system from the main CDash
web service. To use this feature, set `REMOTE_WORKERS=true` in the .env file
for both the CDash web service and the remote worker(s).`

## Deferred submissions

CDash automatically detects when its database is unavailable and stores submissions received during this outage. When database access is restored, CDash will attempt to parse the submissions that were received during the outage. This behavior is controlled by the presence of the file named `DB_WAS_DOWN` was in the storage directory.
