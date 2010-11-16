#!/bin/bash

set -e
set -o pipefail

. $1/env

cd $DB
sqlite3 wikis.db .dump >dump-wikis.db.sql.$$
mv dump-wikis.db.sql.$$ dump-wikis.db.sql
ls -l dump-wikis.db.sql

cd $WWW
mysqlrootpw="$(echo '<? require("mediawiki/AdminSettings.php"); print $wgDBadminpassword;' | php)"

cd $DB
sqlite3 -separator , wikis.db 'select id from wikis' | while read id
do
  id=`printf %02d "$id"`
  cd $WWW/$id
  mysqldump -uroot -p"$mysqlrootpw" wikidb$id | gzip -n9 --rsyncable >private/wikidb$id.sql.gz.$$
  mv private/wikidb$id.sql.gz.$$ private/wikidb$id.sql.gz
  ls -l private/wikidb$id.sql.gz
done
