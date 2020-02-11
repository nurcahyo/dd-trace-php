#!/usr/bin/env bash

set -e

for i in {1..5}; do
    curl localhost:8886
    curl localhost:8887
    curl localhost:8888
    curl localhost:8889
done
