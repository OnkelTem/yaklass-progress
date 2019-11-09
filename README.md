# Yaklass TOP fetcher

* Switch to the russian version: [README.RU.md](README.RU.md)

## Description

Simple PHP script for fetching students TOP data from [Yaklass](https://yaklass.ru).

The result is saved into a CSV file (currently: `stats.csv`). 
Subsequent invocations will update the file with incremental updates.

## Why?

Well. You might be interested in collecting rating dynamics about 
the group where your kid is studying.

I personally would like to run a sort of sprints with some reward at the end 
to motivate students to constantly repeat and nail down when they learn. 
Because as the saying goes: *"repetitio est mater studiorum."*

Wanna discuss? Contact me: aneganov@gmail.com 

## Prerequisites

In order to use this package you need to install [Composer](https://getcomposer.org/).

## Setup

Install this package and its dependencies:

```
$ composer init --no-interaction -s dev --repository '{"type": "git", "url": "git@github.com:OnkelTem/yaklass_top.git"}'
$ composer require onkeltem/yaklass_top
```

Install [Selenium](http://selenium-release.storage.googleapis.com/index.html) server 
and [Chromedriver](https://sites.google.com/a/chromium.org/chromedriver/downloads) either manually or using
the provided install script:

```
$ vendor/bin/install.sh
```

Create `credentials.json` file with your Yaklass username and password:

```json
{
  "username": "parent@example.com",
  "password": "parentPassword"
}
```

You need to have at least one kid configured in Yaklass to access TOP page information.

## Usage

Run Selenium server:

```
$ vendor/bin/start.sh
```

Run Yaklass TOP page fetcher:  

```
$ vendor/bin/yaklass_top
```

It creates a CSV file named `stats.csv` with the following structure:

| id | first_name | last_name | 2019-11-08 01:40:12 |
|----|------------|-----------|--------------------:|
| 749c2b76-1a... | Vladimir | Putin | 125 | 
| 213b1a32-b5... | George | Bush | 76 | 
| ... | 

The last column contains user's points and its name is
the current date and time.
 
Every time when you run the script again, it adds a new column with the number
of points gained by the users since the previous invocation:

| id | first_name | last_name | 2019-11-08 01:40:12 | 2019-11-08 01:40:12 |
|----|------------|-----------|--------------------:|--------------------:|
| 749c2b76-1a... | Vladimir | Putin | 125 | 12 |
| 213b1a32-b5... | George | Bush | 76 | 18 |
| ... | 

Thus, to get the total number of points just sum up values in a row.   
