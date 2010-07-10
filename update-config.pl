#!/usr/bin/perl

if (!exists $ENV{INSTALLDIR}) {
    warn "no INSTALLDIR set, assuming /home/wikifarm\n";
    $ENV{INSTALLDIR} = "/home/wikifarm";
}

open STDIN, "<", "$ENV{INSTALLDIR}/etc/wiki.list" or die "$!";
open STDOUT, ">", "$ENV{INSTALLDIR}/etc/apache2.conf.$$" or die "$!";

$OPENID_DB_FILE = "/tmp/mod_auth_openid.db";
$WIKIFARM_DB_FILE = "$ENV{INSTALLDIR}/db/wikis.db";

print qq{
#RewriteLog /tmp/rewrite.log
#RewriteLogLevel 9

<Location />
  AuthOpenIDEnabled On
  AuthOpenIDCookieLifespan 604800
  AuthOpenIDCookiePath /
  AuthOpenIDDBLocation $OPENID_DB_FILE
</Location>

SetEnv WIKIFARM_DB_FILE $WIKIFARM_DB_FILE
RewriteEngine On
};

while (<STDIN>)
{
    chomp;
    my ($wikiid, $wikiname, $wikiowner, $wikigroup) = split "\t";
    if (!defined $wikigroup) {
	$wikigroup = "labmembers";
	$wikigroup = "joshilab" if $wikiid == 64 || $wikiid == 65;
    }
    print qq{
RewriteCond %{ENV:WIKIID} !.
RewriteRule ^/$wikiid/(.*) /$wikiid/\$1 [E=WIKIID:$wikiid]
RewriteCond %{ENV:WIKIID} !.
RewriteRule ^/$wikiname/(.*) /$wikiid/index.php?title=\$1 [E=WIKIID:$wikiid,QSA]
};
}

print qq{
RewriteCond \${openid_authorize:${WIKIFARM_DB_FILE}:::${OPENID_DB_FILE}:::%{ENV:WIKIID}:::%{REQUEST_URI}:::%{HTTP_COOKIE}} !=yes
RewriteRule .* . [F]

# Prevent direct access to mediawiki installations
RewriteCond %{REQUEST_URI} ^/mediawiki
RewriteRule . / [F]
};

close STDOUT;

rename ("$ENV{INSTALLDIR}/etc/apache2.conf.$$",
	"$ENV{INSTALLDIR}/etc/apache2.conf.inc") or die "$!";
