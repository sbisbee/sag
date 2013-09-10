#!/bin/sh

[ -z "$1" ] && echo 'Error: Must pass sag dir as first param' && exit 1

/usr/local/etc/init.d/couchdb start

while [ 1 ]; do
  /usr/local/etc/init.d/couchdb status && break
  sleep 1
done

cd $1 && make check
