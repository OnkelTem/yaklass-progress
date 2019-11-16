#!/usr/bin/env bash

SELENIUM_URL="http://selenium-release.storage.googleapis.com/3.141/selenium-server-standalone-3.141.59.jar"
CHROMEDRIVER_URL="https://chromedriver.storage.googleapis.com/77.0.3865.40/chromedriver_linux64.zip"

err() {
  echo "Error: $1"
  exit 1
}

dir=drivers
mkdir -p "$dir" || err "Cannot create directory: $dir/"
cd "$dir" || err "Directory not found: $dir"
wget -O selenium.jar "$SELENIUM_URL"
wget -O chromedriver.zip "$CHROMEDRIVER_URL"
unzip chromedriver.zip && rm chromedriver.zip
