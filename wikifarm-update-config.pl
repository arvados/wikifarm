#!/usr/bin/perl

if (!exists $ENV{INSTALLDIR}) {
    warn "no INSTALLDIR set, assuming /home/wikifarm\n";
    $ENV{INSTALLDIR} = "/home/wikifarm";
}

open STDIN, "sqlite3 $ENV{INSTALLDIR}/db/wikis.db 'select id,wikiname from wikis' |" or die "$!";
open STDOUT, ">", "$ENV{INSTALLDIR}/etc/apache2.conf.$$" or die "$!";

$OPENID_DB_FILE = "/tmp/mod_auth_openid.db";
$WIKIFARM_DB_FILE = "$ENV{INSTALLDIR}/db/wikis.db";

print qq{
#RewriteLog /tmp/rewrite.log
#RewriteLogLevel 9
CustomLog "|$ENV{INSTALLDIR}/etc/wikifarm-log-split.pl $ENV{INSTALLDIR}/wikis/{}/private/access_log.txt /var/log/apache2/wikifarm-access.log $ENV{INSTALLDIR}/db/wikis.db" combined

<Location />
  AuthOpenIDEnabled On
  # This sets cookie lifetime = 0 but server session lifetime = 86400
  AuthOpenIDCookieLifespan 0
  AuthOpenIDCookiePath /
  AuthOpenIDDBLocation $OPENID_DB_FILE
# AuthOpenIDTrustRoot http://wikifarm-dev.freelogy.org/
  AuthOpenIDLoginPage /login.php
</Location>
<LocationMatch ^/log(in|out).*>
  AuthOpenIDEnabled Off
</LocationMatch>

SetEnv WIKIFARM_DB_FILE $WIKIFARM_DB_FILE
SetEnv OPENID_DB_FILE $OPENID_DB_FILE
RewriteEngine On
};

open HTACCESS, ">", "$ENV{INSTALLDIR}/wikis/.htaccess.$$" or die "$!";
print HTACCESS "RewriteEngine On\n\n";
while (<STDIN>)
{
    chomp;
    my ($wikiid, $wikiname) = split /\|/;
    $wikiid = sprintf "%02d", $wikiid;
    print HTACCESS qq{
RewriteCond %{ENV:WIKIID} !.
RewriteRule ^$wikiid(/(.*))?\$ $wikiid/\$2 [E=WIKIID:$wikiid]
RewriteCond %{ENV:WIKIID} !.
RewriteRule ^$wikiname(/(.*))?\$ $wikiid/index.php?title=\$2 [E=WIKIID:$wikiid,QSA]
};
}

print HTACCESS qq{
RewriteCond \${wikifarm_auth:${WIKIFARM_DB_FILE}:::${OPENID_DB_FILE}:::%{ENV:WIKIID}:::%{REQUEST_URI}:::%{HTTP_COOKIE}} !=yes
RewriteRule .* . [F]

# Prevent direct access to mediawiki installations
RewriteCond %{REQUEST_URI} ^mediawiki
RewriteRule . / [F]
};

close HTACCESS;
close STDOUT;

rename ("$ENV{INSTALLDIR}/wikis/.htaccess.$$",
	"$ENV{INSTALLDIR}/wikis/.htaccess") or die "$!";
rename ("$ENV{INSTALLDIR}/etc/apache2.conf.$$",
	"$ENV{INSTALLDIR}/etc/apache2.conf.inc") or die "$!";
