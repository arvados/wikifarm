#!/bin/bash

INSTALLDIR=${INSTALLDIR:-/home/wikifarm}

set -e
set -o pipefail

cd $INSTALLDIR/db
sqlite3 wikis.db .dump >dump-wikis.db.sql.$$
mv dump-wikis.db.sql.$$ dump-wikis.db.sql
ls -l dump-wikis.db.sql

cd $INSTALLDIR
mysqlrootpw="$(echo '<? require("wikis/mediawiki2/AdminSettings.php"); print $wgDBadminpassword;' | php)"

sqlite3 -separator , db/wikis.db 'select id from wikis' | while read id
do
  id=`printf %02d "$id"`
  cd $INSTALLDIR/wikis/$id
  mysqldump -uroot -p$mysqlrootpw wikidb$id | gzip -n9 --rsyncable >private/wikidb$id.sql.gz.$$
  mv private/wikidb$id.sql.gz.$$ private/wikidb$id.sql.gz
  ls -l private/wikidb$id.sql.gz
done
