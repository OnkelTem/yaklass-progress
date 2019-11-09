#!/usr/bin/env bash

err() {
  echo "Error: $1"
  exit 1
}

dir=drivers
[[ -d "$dir" ]] || err "Directory not found: $dir, have you run install.sh?"
cd "$dir" || exit 1
java -jar selenium.jar
