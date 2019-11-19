# Yaklass TOP - SQL fetcher

* Switch to the russian version: [README.ru.md](README.ru.md)

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

## Prerequisites

In order to use this package you need to install [Composer](https://getcomposer.org/).

## Setup

Install this package and its dependencies:

```
$ composer init --no-interaction -s dev --repository '{"type": "git", "url": "git@github.com:OnkelTem/yaklass-top-sql.git"}'
$ composer require onkeltem/yaklass-top-sql
```

Install [Selenium](http://selenium-release.storage.googleapis.com/index.html) server 
and [Chromedriver](https://sites.google.com/a/chromium.org/chromedriver/downloads) either manually or using
the provided install script:

```
$ vendor/bin/yaklass-ts-install.sh
```

## Usage

### List of tasks

There are currently three tasks you can run with the script:

* `sync` - fetching data from Yaklass
* `list` - printing data currently stored in the database in JSON
* `publish` - publishing data in a Google Spreadsheet

### Task `sync`

In the project's root create the `config.json` file with the following content:

```json
{
  "sync": {
    "username": "/* Your Yaklass email */",
    "password": "/* Your Yaklass password */"
  }
}
```

You need to have at least one kid configured in Yaklass to access TOP page information.

Run the Selenium server either manually or using the provided script:

```
$ vendor/bin/yaklass-ts-start.sh
```

The script adds a delay of 10 seconds so please wait until it returns.

Now launch the `sync` command:

```
$ vendor/bin/yaklass-ts sync
```

When running for the first time, it creates a new SQLite database in the 
project root - `stats.sqlite` and populates it with the fetched data.

With the subsequent runs `sync` will be updating the database 
with the new information about students activities, if there were any.

### Task `list`
 
To list the data currently stored in the DB use `list` command:

```
$ vendor/bin/yaklass-ts list
```

It will prints the data in JSON format, allowing for further processing (e.g. with 
[jq](https://stedolan.github.io/jq/)).

Since this is a regular SQLite database, you can also use 
[SQLite Database Browser](https://sqlitebrowser.org/) or any other appropriate tool
which supports SQLite 3 databases.

### Task `publish`

You can export the data in a Google Spreadsheet.

To do this, you first need to acquire a **Google Sheets API Service Account** credentials
and share a specific spreadsheet with that account.

To get the credentials:

* Go to your [Google Developer Console](https://console.developers.google.com/)
* Click `+ ENABLE APIS AND SERVICES` button and search for "Google Sheets API"
* On the API's page enable proceed to craete Credentials of the type "Service Account".

When you're done, you should get an email-like address and a JSON file with the credentials.
Download that file and place it in the project root. It's name is random and may look 
like `some-word-253822-b4260ce85d5c.json`.

To share a spreadsheet just use the regular **Share** function and set the received 
service account's email address as the target.

Now update your `config.json` by adding new section to it:

```json
{
  "publish": {
    "maxVisibleBodyCols": "/* The count of visible data columns, default: 50 */",
    "sheetTitle": "/* The title of the target sheet where to publish the data */",
    "spreadsheetId": "/* That long ID of the target spreadsheet, copy it from the address bar */",
    "credentials": "/* Google Service Account credentials JSON filename */",
    "locale": "/* Your locale, e.g.: ru_RU */"
  }
}
```

and edit it accordingly to suit your conditions.

Now you should be ready to publish the data:

```
$ vendor/bin/yaklass-ts publish
```

Here is an image of how it may look:

![Spreadsheet](https://i.gyazo.com/7981dc63897ed0a68291d315b5dd1f82.png)

## Database structure

The database has contains tables: `student` and `activity`.

The `student` table stores a brief student information - Yaklass student's UUID 
and the name.

The `activity` table stores the student's activities measured since 
the last invocation of the `sync` command.  

## Workflow

Since the purpose of the script is to fetch and publish incremental updates, you'll
probably want to run it regularly. One way to achieve that is via cron. 

For example, run `crontab -e` and add these lines:

```
0/10 * * * * cd /path/to/project && ./vendor/bin/yaklass-ts-start.sh && ./vendor/bin/yaklass-ts --headless sync >> cron.log 2>&1; ./vendor/bin/yaklass-ts-stop.sh
0/20 * * * * cd /path/to/project && ./vendor/bin/yaklass-ts publish >> cron.log 2>&1
```

The first line instructs cron to run hourly:
 
* switching to your project dir,
* starting Selenium server,
* running the `sync` command with the `--headless` option, 
* stopping the server,
* writing the output to the `cron.log` file in the project dir.

The second line publishes the data.

## TODO

* ~~Enable `headless` mode of the PHP Webdriver to allow for cron-processing :)~~
* ~~Add reporting capabilities (e.g. save/export to CSV)~~   

## Contact

If you have some ideas or questions - feel free to create tickets in the **Issues** 
or write me directly: aneganov@gmail.com.
