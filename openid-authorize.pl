#!/usr/bin/perl

use DBI;

$debug = 1;

$|=1;

if ($debug) {
    open X, ">>", "/tmp/openidauthlog";
    select X; $|=1; select STDOUT;
    print X "starting " . scalar(localtime) . "\n";
}
while (defined ($_ = <STDIN>)) {
    chomp;
    my $in = $_;
    my ($wiki_db_file, $auth_openid_db_file, $wikiid, $uri, $cookie) = split (":::", $_, 5);

    if (!$main::dbh) {
	print X "connect to $wiki_db_file\n" if $debug;
	dbi_connect ($auth_openid_db_file);
	if (!$main::dbh) {
	    print X "not connected yet -- uri $uri\n" if $debug;
	    print "no\n";
	    next;
	}
    }

    my ($session_id) = $in =~ /open_id_session_id=(\w+)/;
    my ($user_id, $session_exists) = $main::dbh->selectrow_array (
	"SELECT identity, session_id FROM sessionmanager WHERE session_id=?",
	undef, $session_id);

    my $yesno = "no";
    if (!defined $session_exists) {
	# allow mod_auth_openid to show a login page
	$yesno = "yes";
    }
    elsif ($user_id eq "http://tomclegg.myopenid.com/") {
	$yesno = "yes";
    }
    elsif (!$wikiid) {
	$yesno = "no";		# change to "yes" when index.php is safe
    }
    elsif (user_can_see_wiki ($user_id, $wikiid)) {
	$yesno = "yes";
    }
    print X "wiki $wikiid > session $session_id > user $user_id > uri $uri > $yesno\n" if $debug;
    print "$yesno\n";
}

exit 0;

sub dbi_connect
{
    my $file = shift;
    $main::dbh = DBI->connect("dbi:SQLite:dbname=$file",
			      "",
			      "",
			      { RaiseError => 1 });
}

sub user_can_see_wiki
{
    my $userid = shift;
    my $wikiid = shift;
    if ($userid eq "http://tomclegg.myopenid.com/" || $wikiid == 42) {
	return 1;
    } else {
	return 0;
    }
}
