# Yaklass TOP - SQL fetcher

## Description

Скрипт для скачивания рейтинга учеников со страницы ТОП в ЯКласс.

Выгрузка сохраняется в БД SQLite. Последующие запуски будут обновлять базу инкрементами.

## Why

Ну, возможно, вы зачем-то захотите собирать статистику по прогрессу всего класса, 
где учится ваш ребёнок.

Лично мне это нужно, чтобы устраивать марафоны с вознаграждением. Хочу чтобы 
у детей была дополнительная мотивация на закрепление пройденного материала. 
Ведь повторение - мать учения, как изввестно. 

Хотите об этом поговорить? Пишите, обсудим: aneganov@gmail.com. 
 

## Prerequisites

Чтобы использовать этот софт, установите [Composer](https://getcomposer.org/).

## Setup

Установить пакет и его зависимости:

```
$ composer init --no-interaction -s dev --repository '{"type": "git", "url": "git@github.com:OnkelTem/yaklass_top_sql.git"}'
$ composer require onkeltem/yaklass_top_sql
```

Установите сервер [Selenium](http://selenium-release.storage.googleapis.com/index.html) 
и [Chromedriver](https://sites.google.com/a/chromium.org/chromedriver/downloads) либо вручную, 
либо запустив прилагающийся скрипт: 

```
$ vendor/bin/install.sh
```

В корне создайте файл `credentials.json` с вашими логином и паролем:

```json
{
  "username": "parent@example.com",
  "password": "parentPassword"
}
```

На ЯКласс вам нужно настроить хотя бы одного ребенка, чтобы получить доступ к странице с рейтингом класса. 

## Usage

Запустите сервер Selenium:

```
$ vendor/bin/start.sh
```

Запустите скрипт:

```
$ vendor/bin/yaklass_top_sql sync 
```

При первом запуске будет создана БД в корне проекта - `stats.sqlite` и заполнена 
выгруженными данными. 

Чтобы посмотреть данные в базе, выполните команду `show`:
 
```
$ vendor/bin/yaklass_top_sql show
```

Будет выведен список в формате JSON, который можно уже дальше расковыривать с помощью 
гениальной [jq](https://stedolan.github.io/jq/), например.

Поскольку имеем обыкновенную базу SQLite, её можно смотреть через 
[SQLite Database Browser](https://sqlitebrowser.org/) или любой другой аналогичной
тулзы для SQLite 3. 

Последующие запуски `sync` будут обновлять базу новой информацией об активности 
детишек, если таковая имела место быть (за период с последнего запуска `sync`). 

## Database

В базе 2 таблицы: `student` and `activity`.

В таблице `student` хранится краткая информация о детях - UUID с якласса и имена.

В таблице `activity` хранится информация об активности обучающихся.

## Workflow

Предполагается, что `yaklass_top sync` будет добавлен в **crontab** с каким-то разумным 
периодом перезапуска (например раз в час) чтобы база обновлялась регулярно и сама.
Но сейчас это работать не будет, так как я ещё не добавил `headless` режим для PHP WebDriver.

## TODO

* Добавить `headless` режим для PHP WebDriver.
* Добавить возможность получать отчеты (например в CSV)   
