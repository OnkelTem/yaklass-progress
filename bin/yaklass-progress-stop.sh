#!/usr/bin/env bash

DRIVERS_DIR=drivers

msg() { echo -e "\033[0;32m$1\033[0m"; }
warn() { echo -e "\033[1;33m$1\033[0m"; }
err() {  echo -e "\033[1;31m$1\033[0m"; }
abort() { err "$1"; exit 1; }

[[ -d "$DRIVERS_DIR" ]] || abort "Directory not found: $DRIVERS_DIR, have you run 'yaklass-progress-install.sh?'"
cd "$DRIVERS_DIR" || abort "Directory not found: $DRIVERS_DIR"

if [[ -f selenium.lock ]]; then
  pid=$(<selenium.lock)
  kill -9 "$pid"
  rm selenium.lock
  msg "Selenium server is stopped."
fi
