## ResourceContracts API Dockerfile

This repository contains the Dockerfile for [ResouceContracts API component](https://github.com/younginnovations/resourcecontracts-api) for Docker.

### Base Docker Image

[Ubuntu 14.04](http://dockerfile.github.io/#/ubuntu)

### Installation

1. Install [Docker](https://www.docker.com/).
2. Clone this repo `git clone https://github.com/younginnovations/docker-rc-api.git`
3. Go to the cloned folder `docker-rc-api`
4. Copy `conf/.env.example` to `conf/.env` with proper configurations
5. Build an image from Dockerfile `docker build -t=rc-api .`

### Usage

* Run `docker run -p 80:80 -d rc-api`
* Access the system from the browser at http://xxx/rc-api/index.php

### TODO

* Update the apache configuration so that the system could be accessed from the base IP http://xxx 
* Mount the system temporary folder to the host folder to preserve the temporary files and logs
* Currently system is run using root, need to use appropriate users for running the servers and applications.