#!/bin/bash

INSTALLDIR=${INSTALLDIR:-/home/wikifarm}

set -e
set -x

builder=$INSTALLDIR/etc/awstats_buildstaticpages.pl

# "all" stats go in /01/private/stats

wikiid_allstats=01
wikidir=$INSTALLDIR/wikis/$wikiid_allstats/private/stats
cd $wikidir
perl -pe "s:/00/:/$wikiid_allstats/:g;" <$INSTALLDIR/etc/awstats.all.conf >awstats.all.conf
perl $builder -awstatsprog=/usr/lib/cgi-bin/awstats.pl -configdir=$wikidir -config="all" -update -dir=$wikidir


# Individual wiki stats go in /##/private/stats

sqlite3 -separator ' ' /home/wikifarm/db/wikis.db \
 'select id, wikiname from wikis' | while read wikiid wikiname
do
  wikiid=`printf %02d $wikiid`

  adminlink=""
  if [ "$wikiid" = "$wikiid_allstats" ]
  then
    adminlink="<li><a href=\"stats/awstats.all.html\">report for all wikis</a></li>"
  fi
  cat >${INSTALLDIR}/wikis/$wikiid/private/index.html <<EOF
<html>
<head>
<link rel="stylesheet" type="text/css" href="/style.css">
</head>
<body>
<br>
<br>
Backups
<ul>
<li>pub is mirrored nightly at 3:30 am, and backed up to tape at 4:30 am.</li>
<li>contact Shawn if you would like to download a local copy of your wiki.</li>
<!--<li><a href="$wikiname-backup.tar">$wikiname-backup.tar</a></li>-->
</ul>
Access Statistics
<ul>
<li><a href="stats/awstats.$wikiid.html">report</a></li>
<li><a href="access_log.txt">raw access_log</a></li>
$adminlink
</ul>
</body>
</html>
EOF
  wikidir=$INSTALLDIR/wikis/$wikiid/private/stats
  cd $wikidir
  perl -pe "s:/00/:/$wikiid/:g;" <$INSTALLDIR/etc/awstats.00.conf >awstats.$wikiid.conf
  perl $builder -awstatsprog=/usr/lib/cgi-bin/awstats.pl -configdir=$wikidir -config=$wikiid -update -dir=$wikidir
done
