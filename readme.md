Moved to https://github.com/NRGI/resourcecontracts-api 

# NRGI- Client API

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/younginnovations/resourcecontracts-api/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/younginnovations/resourcecontracts-api/?branch=master)

## Install

NRGI- Client API can be cloned from gitlab repository and installed. Following the procedure given below:

* git clone https://github.com/younginnovations/resourcecontracts-api.git


* cd resourcecontracts-api

## Run

The app can be run with the command below:

* install the application dependencies using command: `composer install`
* copy .env.example to .env and update your configuration .
* run php server ie. `php -S localhost:8000`
* make sure elasticsearch is running .

## Setup Elasticsearch

### For Linux

* Download Elasticsearch- `wget https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-5.6.12.deb`
* `sudo dpkg -i elasticsearch-5.6.12.deb `
* `cd /usr/share/elasticsearch`
* `sudo bin/plugin install delete-by-query`
* Start Elasticsearch Service `sudo service elasticsearch restart`
* Make sure elasticsearch is running in port 9200.

## Tools and packages

This application uses following packages:

* [ElasticSearch PHP client](https://github.com/elastic/elasticsearch-php) - for Elastic Search API
* [League Route](http://route.thephpleague.com/) - for Routing
* [PHP dotenv] (https://github.com/vlucas/phpdotenv) - for loading environment variables

