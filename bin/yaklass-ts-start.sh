#!/usr/bin/env bash

dir=drivers
[[ -d "$dir" ]] || { echo "Directory not found: $dir, have you run install.sh?"; exit 1; }
cd "$dir" || exit 1

[[ -f selenium.lock ]] || {
  java -jar selenium.jar > /dev/null 2>&1 &
  pid=$!
  echo $pid > selenium.lock
  sleep 10s
  echo "Selenium server is running."
}
