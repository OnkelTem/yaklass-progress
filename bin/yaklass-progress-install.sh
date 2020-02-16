#!/usr/bin/env bash

DRIVERS_DIR=drivers
SELENIUM_JAR_URL="http://selenium-release.storage.googleapis.com/3.141/selenium-server-standalone-3.141.59.jar"
CHROMEDRIVER_PAGE_URL="https://sites.google.com/a/chromium.org/chromedriver/downloads"

msg() { echo -e "\033[0;32m$1\033[0m"; }
warn() { echo -e "\033[1;33m$1\033[0m"; }
err() {  echo -e "\033[1;31m$1\033[0m"; }
abort() { err "$1"; exit 1; }

mkdir -p "$DRIVERS_DIR" || abort "Cannot create directory: $DRIVERS_DIR"
cd "$DRIVERS_DIR" || abort "Directory not found: $DRIVERS_DIR"

echo "Installing: Selenium server"
if [[ ! -f selenium.jar ]]; then
  wget -O selenium.jar "$SELENIUM_JAR_URL"
  msg "Selenium server is downloaded."
else
  msg "Selenium server is installed already."
fi

echo "Installing: ChromeDriver"
if [[ ! -f chromedriver ]]; then
  warn "Sorry, this operation is not automated yet. Please download ChromeDriver manually."
  warn "The major version should match your local Google Chrome installation."
  msg "Checking your local Google Chrome installation..."
  if hash google-chrome > /dev/null 2>&1; then
    msg "Found: \033[1;35m$(google-chrome --version)\033[0m"
  else
    abort "Not found! Please install Google Chrome first and then rerun this script."
  fi
  echo -n "Do you wish to open ChromeDriver download page? [Y]es/[n]o: "
  read -r answer
  if [[ "$answer" != "${answer#[Yy]}" || "${answer:-yes}" == "yes" ]]; then
    sensible-browser "$CHROMEDRIVER_PAGE_URL" > /dev/null 2>&1 &
    warn "Once you have the package download, please unzip it into the '$DRIVERS_DIR/' directory"
  fi
else
  msg "ChromeDriver is installed already."
fi
