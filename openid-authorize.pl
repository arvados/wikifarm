#!/usr/bin/perl

use DBI;

$debug = 1;

$|=1;

my $openid_db;
my $wikifarm_db;

if ($debug) {
    open X, ">>", "/tmp/openidauthlog";
    select X; $|=1; select STDOUT;
    print X "starting " . scalar(localtime) . "\n";
}
while (defined ($_ = <STDIN>)) {
    chomp;
    my $in = $_;
    my ($wikifarm_db_file, $auth_openid_db_file, $wikiid, $uri, $cookie) = split (":::", $_, 5);

    if (!$openid_db) {
	print X "connect to $auth_openid_db_file\n" if $debug;
	db_connect (\$openid_db, $auth_openid_db_file);
	if ($openid_db) {
	    print X "not connected yet -- uri $uri\n" if $debug;
	    print "no\n";
	    next;
	}
    }
    if (!$wikifarm_db) {
	print X "connect to $wikifarm_db_file\n" if $debug;
	db_connect (\$wikifarm_db, $wikifarm_db_file);
	if ($wikifarm_db) {
	    print X "not connected yet -- uri $uri\n" if $debug;
	    print "no\n";
	    next;
	}
    }

    my ($session_id) = $in =~ /open_id_session_id=(\w+)/;
    my ($user_id, $session_exists) = $openid_db->selectrow_array (
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
    elsif ($uri =~ m:^/test.php:) {
	$yesno = "yes";
    }
    elsif (!$wikiid) {
	$yesno = "no";		# change to "yes" when index.php is safe
    }
    elsif (user_can_see_wiki ($wikifarm_db, $user_id, $wikiid)) {
	$yesno = "yes";
    }
    print X "wiki $wikiid > session $session_id > user $user_id > uri $uri > $yesno\n" if $debug;
    print "$yesno\n";
}

exit 0;

sub db_connect
{
    my $db = shift;
    my $file = shift;
    $$db = DBI->connect("dbi:SQLite:dbname=$file",
			"",
			"",
			{ RaiseError => 1 });
}

sub user_can_see_wiki
{
    my $db = shift;
    my $userid = shift;
    my $wikiid = shift;
    if ($userid eq "http://tomclegg.myopenid.com/" || $wikiid == 42) {
	return 1;
    } else {
	return 0;
    }
}
