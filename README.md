# TokenDistrib

Token Distrib is a HIVE blockchain tool to automatize token distribution for community. This repo is the source code for the official install of [TokenDistrib](https://distrib.hivelive.me/).

## Installation

To install your instance of **Token Distrib**, you must have a Web server with PHP 8. This project work with [HiveSQL](https://hivesql.io/) and need the installation of [MS SQL drivers](https://learn.microsoft.com/en-us/sql/connect/odbc/download-odbc-driver-for-sql-server?view=sql-server-ver16).

---

#### Example: Install for Debian 13 Trixie

Install your web server and add PHP and dependencies :
```
apt install git unixodbc-dev php8.2 php8.2-dev php8.2-xml php8.2-intl composer
pecl install sqlsrv pdo_sqlsrv
```

After that just add the `extension=pdo_sqlsrv.so` line in your php.ini file. Restart your web server and it's OK :)

---

### Configure your web server

TokenDistrib is based on Slim 4 Framework. Depending on the webserver you use, just check the [Slim 4 Web servers documentation](https://www.slimframework.com/docs/v4/start/web-servers.html) to find the config you need.

### Clone this repo

Just go to the folder you want to install **TokenDistrib** and clone this repo :

```bash
git clone https://github.com/fkosmala/tokendistrib
```

### Install TokenDistrib deps

TokenDistrib use [Composer](https://getcomposer.org/) to manage dependencies. Just go to the project folder and made a composer install :

```bash
cd tokendistrib
composer install
```

## Fill Config file

go to the `config/` folder and copy the `db.sample.json` to `db.json`. After that, edit the file to add your HiveSQL credentials.

That's all folks ! Open your browser and start to use your **TokenDistrib** Instance !