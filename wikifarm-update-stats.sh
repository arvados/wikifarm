#!/bin/bash

set -e
set -x

. $1/env

builder=$ETC/awstats_buildstaticpages.pl

# "all" stats go in /01/private/stats

wikiid_allstats=01
wikidir=$WWW/$wikiid_allstats/private/stats
cd $wikidir
perl -pe "s:/00/:/$wikiid_allstats/:g;" <$ETC/awstats.all.conf >awstats.all.conf
perl $builder -awstatsprog=/usr/lib/cgi-bin/awstats.pl -configdir=$wikidir -config="all" -update -dir=$wikidir


# Individual wiki stats go in /##/private/stats

sqlite3 -separator ' ' $DB/wikis.db \
 'select id, wikiname from wikis' | while read wikiid wikiname
do
  wikiid=`printf %02d $wikiid`

  adminlink=""
  if [ "$wikiid" = "$wikiid_allstats" ]
  then
    adminlink="<li><a href=\"stats/awstats.all.html\">report for all wikis</a></li>"
  fi
  cat >${WWW}/$wikiid/private/index.html <<EOF
<html>
<head>
<link rel="stylesheet" type="text/css" href="/style.css">
</head>
<body>
<br>
<br>
<p>For backups and statistics, go to the <A href="/">wikifarm dashboard</A>.</p>
</body>
</html>
EOF
  wikidir=$WWW/$wikiid/private/stats
  cd $wikidir
  perl -pe "s:/00/:/$wikiid/:g;" <$ETC/awstats.00.conf >awstats.$wikiid.conf
  perl $builder -awstatsprog=/usr/lib/cgi-bin/awstats.pl -configdir=$wikidir -config=$wikiid -update -dir=$wikidir
done
