#!/usr/bin/env bash

dir=drivers
[[ -d "$dir" ]] || { echo "Directory not found: $dir, have you run install.sh?"; exit 1; }
cd "$dir" || exit 1

[[ -f selenium.lock ]] && {
  pid=$(<selenium.lock)
  kill -9 "$pid"
  rm selenium.lock
}
