#!/usr/bin/perl

open STDIN, "sqlite3 $ENV{DB}/wikis.db 'select id,wikiname from wikis' |" or die "sqlite3 $ENV{DB}/wikis.db: $!";
open STDOUT, ">", "$ENV{DB}/apache2.conf.$$" or die "$ENV{DB}/apache2.conf.$$: $!";

$OPENID_DB_FILE = "$ENV{DB}/sessions.db";
$WIKIFARM_DB_FILE = "$ENV{DB}/wikis.db";

print qq{
CustomLog "|$ENV{ETC}/wikifarm-log-split.pl $ENV{WWW}/{}/private/access_log.txt /var/log/apache2/wikifarm-access.log $ENV{DB}/wikis.db" combined

<Location />
  Require all granted
</Location>
<LocationMatch ^/(login2?\.php|logout\.php|css/|images/|js/|serverlogo\.).*>
  Require all granted
</LocationMatch>
<LocationMatch ^/mediawiki.*>
  Require all denied
</LocationMatch>

SetEnv WIKIFARM_DB_FILE $WIKIFARM_DB_FILE
SetEnv OPENID_DB_FILE $OPENID_DB_FILE
SetEnv WIKIFARM_ETC $ENV{ETC}
SetEnv WIKIFARM_WWW $ENV{WWW}
SetEnv WIKIFARM_DB $ENV{DB}
RewriteEngine On
};

open HTACCESS, ">", "$ENV{WWW}/.htaccess.$$" or die "$!";
print HTACCESS "RewriteEngine On\n\n";
while (<STDIN>)
{
    chomp;
    my ($wikiid, $wikiname) = split /\|/;
    $wikiid = sprintf "%02d", $wikiid;
    print HTACCESS qq{
RewriteCond %{ENV:WIKIID} !.
RewriteRule ^$wikiid(/(.*))?\$ $wikiid/\$2 [E=WIKIID:$wikiid,E=MW_INSTALL_PATH:$ENV{WWW}/$wikiid]
RewriteCond %{ENV:WIKIID} !.
RewriteRule ^$wikiname(/(.*))?\$ $wikiid/index.php?title=\$2 [E=WIKIID:$wikiid,E=MW_INSTALL_PATH:$ENV{WWW}/$wikiid,QSA]
};
}
if (!close STDIN) {
    warn "sqlite3 exited $?";
    unlink ("$ENV{WWW}/.htaccess.$$",
	    "$ENV{DB}/apache2.conf.$$");
    exit 1;
}

print HTACCESS qq{
RewriteRule .* - [E=REMOTE_USER:\${wikifarm_auth:${WIKIFARM_DB_FILE}:::${OPENID_DB_FILE}:::%{ENV:WIKIID}:::%{REQUEST_URI}:::%{HTTP_COOKIE}}]
RewriteCond %{ENV:REMOTE_USER} ^-
RewriteRule .* . [F]

RewriteCond %{ENV:REMOTE_USER} ^/\$
RewriteRule .* /login.php [L]

# Prevent direct access to mediawiki installations
RewriteCond %{REQUEST_URI} ^mediawiki
RewriteRule . / [F]
};

if (!close HTACCESS ||
    !close STDOUT) {
    warn "giving up, could not write $ENV{WWW}/.htaccess.$$ and $ENV{DB}/apache2.conf.$$";
    unlink ("$ENV{WWW}/.htaccess.$$",
	    "$ENV{DB}/apache2.conf.$$");
    exit 1;
}

rename ("$ENV{WWW}/.htaccess.$$",
	"$ENV{WWW}/.htaccess") or die "$!";
rename ("$ENV{DB}/apache2.conf.$$",
	"$ENV{DB}/apache2.conf.inc") or die "$!";
