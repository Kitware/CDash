#!/bin/bash

set -e

# Ping both the http and https routes, and fail if neither of them is successful
curl -s -o /dev/null -w "%{http_code}" http://cdash:8080/ping | grep 200 > /dev/null || \
curl -s -o /dev/null -w "%{http_code}" https://cdash:8080/ping | grep 200 > /dev/null || exit 1
