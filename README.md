# fias_address_suggestions

Service of address suggestions on the base FIAS. PHP, PostgreSQL. Based on a trigram index.

## Demo

http://prettyaddress.ru

## Installation guide

```shell
sudo apt install language-pack-ru
```
Reconfigure locales.
```shell
sudo dpkg-reconfigure locales
```
Check to generate `ru_RU.UTF8`.
```shell
sudo apt install postgresql
sudo su postgres
psql
```
```
> CREATE USER fias WITH password 'password';
> CREATE DATABASE fias
	WITH OWNER fias
	ENCODING 'UTF8'
	LC_COLLATE = 'ru_RU.UTF-8'
	LC_CTYPE = 'ru_RU.UTF-8'
	TEMPLATE = template0;
> \c fias
> CREATE EXTENSION pg_trgm;
> CREATE EXTENSION btree_gist;
```
Install php and unrar.
```shell
sudo apt install php-fpm php-pgsql php-mbstring php-xml php-curl unrar
```
Running the installation script.
```shell
nohup php install.php &
```
When the installation is complete, create postgres buffer.
```shell
php buffer.php
```
