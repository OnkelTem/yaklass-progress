#!/usr/bin/env bash

DRIVERS_DIR=drivers

msg() { echo -e "\033[0;32m$1\033[0m"; }
warn() { echo -e "\033[1;33m$1\033[0m"; }
err() {  echo -e "\033[1;31m$1\033[0m"; }
abort() { err "$1"; exit 1; }

[[ -d "$DRIVERS_DIR" ]] || err "Directory not found: $DRIVERS_DIR, have you run 'yaklass-progress-install.sh?'"
cd "$DRIVERS_DIR" || err "Directory not found: $DRIVERS_DIR"

if [[ ! -f selenium.lock ]]; then
  java -jar selenium.jar > selenium.log 2>&1 &
  pid=$!
  sleep 5s
  if ! kill -0 $pid > /dev/null 2>&1; then
    err "Selenium server couldn't start:"
    echo "$(<selenium.log)"
    abort ""
  else
    echo $pid > selenium.lock
    msg "Selenium server is running."
    echo "Log file: $DRIVERS_DIR/selenium.log"
  fi
else
  warn "Lockfile $DRIVERS_DIR/selenium.lock exists, exiting."
fi
