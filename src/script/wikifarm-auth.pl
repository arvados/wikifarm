#!/usr/bin/perl

use DBI;
use Time::HiRes qw(gettimeofday tv_interval);

$debug = 0;

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
    my $t0 = [gettimeofday];
    my ($wikifarm_db_file, $auth_openid_db_file, $wikiid, $uri, $cookie) = split (":::", $_, 5);

    if ($uri =~ m:^/login2?\.php$:) {
        print X "uri $uri > anonymous\n";
        print "anonymous\n";
        next;
    }

    if (!$openid_db) {
	print X "connect to $auth_openid_db_file\n" if $debug;
	db_connect (\$openid_db, $auth_openid_db_file);
	if (!$openid_db) {
	    print X "ERROR: openid db not connected yet -- uri $uri\n" if $debug;
	    print "-\n";
	    next;
	}
    }

    if (!$wikifarm_db) {
	print X "connect to $wikifarm_db_file\n" if $debug;
	db_connect (\$wikifarm_db, $wikifarm_db_file);
	if (!$wikifarm_db) {
	    print X "ERROR: wikifarm db not connected yet -- uri $uri\n" if $debug;
	    print "-\n";
	    next;
	}
    }

    my ($session_id) = $in =~ /open_id_session_id=(\w+)/;
    print X "session_id is $session_id\n" if $debug;

    # extend session expiry time so it can't expire without N seconds
    # of inactivity
    my $now = scalar time;
    my $minexpire = $now + 86400 * 4;
    $openid_db->do (
        "UPDATE sessionmanager SET expires_on=?
         WHERE session_id=? and expires_on>=? and expires_on<?",
        undef, $minexpire, $session_id, $now, $minexpire);
    if ($DBI::err) {
        print X "ERROR: $DBI::errstr\n";
        print "-\n";
        next;
    }

    my ($user_id, $session_exists) = $openid_db->selectrow_array (
	"SELECT identity, session_id FROM sessionmanager WHERE session_id=?",
	undef, $session_id);
    if ($DBI::err) {
	print X "ERROR: $DBI::errstr\n";
        print "-\n";
        next;
    }

    print X "user_id is $user_id\n" if $debug;
    print X "session_exists is $session_exists\n" if $debug;

    my $result = "-";
    if (!defined $session_exists) {
	# redirect to /
	$result = "/";
        print X "returning / because session_id is not set\n" if $debug;
    } elsif ($uri =~ m:^/[^/]*$: ||
             $uri =~ m:^/(css|js|images|help)/:) {
	# Authenticated users have access to the control panel
	$result = "$user_id";
    } elsif ($uri =~ m:^/test.php:) {
	$result = "$user_id";
    }
    elsif ($why = user_can_see_wiki ($wikifarm_db, $user_id, $wikiid, $uri)) {
	$result = "$user_id";
    }


    printf X "wikiid: $wikiid\n";
    printf X ("%.3f s %s", tv_interval ($t0), "wiki $wikiid > session $session_id > user $user_id > uri $uri > $result ($why)\n") if $debug;
    print "$result\n";
}

exit 0;

sub db_connect
{
    my $db = shift;
    my $file = shift;
    # return undef unless -r $file;
    if (! -r $file) {
	    return undef;
    }
    $$db = DBI->connect("dbi:SQLite:dbname=$file",
			"",
			"",
			{ RaiseError => 0 });
}

sub user_can_see_wiki
{
    my $db = shift;
    my $userid = shift;
    my $wikiid = shift;
    my $uri = shift;

    my $ok;

    # maybe this user owns this wiki
    ($ok) = $db->selectrow_array ("SELECT 1
 FROM wikis WHERE id=? AND userid=?", undef, $wikiid, $userid);
    return "owner" if $ok;

    # /wikiid/private/ is only accessible to owner or admin
    goto CHECK_ADMIN if $uri =~ m{^/[^/]+/private/};

    # maybe this user has permission to access this wiki
    ($ok) = $db->selectrow_array ("SELECT 1
 FROM wikis
 LEFT JOIN wikipermission ON wikipermission.wikiid = wikis.id
 WHERE wikis.id = ? AND userid_or_groupname = ?",
	undef, $wikiid, $userid);
    return "user" if $ok;

    # maybe this user belongs to a group that has access to this wiki
    ($ok) = $db->selectrow_array ("SELECT 1
 FROM wikis
 LEFT JOIN wikipermission ON wikipermission.wikiid = wikis.id
 LEFT JOIN usergroups ON userid_or_groupname = usergroups.groupname
 WHERE wikis.id = ? AND usergroups.userid = ?",
	undef, $wikiid, $userid);
    return "group" if $ok;

CHECK_ADMIN:
    # maybe this user belongs to the special ADMIN group
    ($ok) = $db->selectrow_array ("SELECT 1
 FROM usergroups
 WHERE groupname = ? AND usergroups.userid = ?",
	undef, 'ADMIN', $userid);
    return "admin" if $ok;
    return 0;
}
