# Yaklass TOP - SQL fetcher

* Switch to the russian version: [README.RU.md](README.RU.md)

## Description

Simple PHP script for fetching students TOP data from [Yaklass](https://yaklass.ru).

The result is saved into [SQLite](https://sqlite.org/index.html) database. 
Subsequent invocations will update the database with incremental updates.

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
$ composer init --no-interaction -s dev --repository '{"type": "git", "url": "git@github.com:OnkelTem/yaklass_top_sql.git"}'
$ composer require onkeltem/yaklass_top_sql
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

Run Yaklass TOP page SQL fetcher:  

```
$ vendor/bin/yaklass_top_sql sync
```

When running for the first time this will create a new SQLite 
database in the project root - `stats.sqlite` and will populate
it with the fetched data.

To see the data currently stored in the DB use `show` command:
 
```
$ vendor/bin/yaklass_top_sql show
```

It will prints the data in JSON format, allowing for further processing (e.g. with 
[jq](https://stedolan.github.io/jq/)).

Since this is a regular SQLite database, you can also use 
[SQLite Database Browser](https://sqlitebrowser.org/) or any other appropriate tool
which supports SQLite 3 databases.

With the subsequent runs `sync` will update the database with the new information
about students activities, if there were any.

## Database

The database has contains tables: `student` and `activity`.

The `student` table stores brief student information - Yaklass student's UUID 
and the name.

The `activity` table stores student's activities measured since 
the last invocation of the `sync` command.  

## Workflow

Add `yaklass_top sync` invocation to your **crontab** to get 
the database updated regularly and automatically.

## TODO

* Enable `headless` mode of the PHP Webdriver to allow for cron-processing :)
* Add reporting capabilities (e.g. save to CSV)   
