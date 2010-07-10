#!/usr/bin/perl

use DBI;

die "usage: $0 log_file_pattern default_log_file wiki_db_file\n"
    if @ARGV != 3 || $ARGV[0] !~ /\{\}/;

my ($log_file_pattern, $default_log_file, $wiki_db_file) = @ARGV;
my $wiki_db;
my %handle;			# 01 -> <filehandle>
my $wikiid;			# smd -> 01

open DEFAULT, ">>", $default_log_file;
select DEFAULT;
$| = 1;

while(<STDIN>)
{
    my ($host, $ident, $auth, $date, $method, $uri) =
	/^(\S+) (\S+) (\S+) (\[.*?\]) "(\S+) (\S+) ([^"]*)/;
    my ($tag) = $uri =~ m{^/(\S+?)/};
    my $wikiid = $tag;
    if (exists $wikiid{$tag}) {
	$wikiid = $wikiid{$tag};
    }
    elsif ($wikiid =~ /\D/) {
	if (!$wiki_db) {
	    $wiki_db = DBI->connect("dbi:SQLite:dbname=$wiki_db_file",
				    "",
				    "",
				    { RaiseError => 1 });
	}
	($wikiid) = $wiki_db->selectrow_array (
	    "SELECT id FROM wikis WHERE wikiname=?", undef, $wikiid);
	$wikiid{$tag} = $wikiid if $wikiid;
    }
    if (!exists $handle{$wikiid} && $wikiid =~ /^\d+$/) {
	my $filename = $log_file_pattern;
	if ($filename =~ s/\{\}/$wikiid/g) {
	    open $handle{$wikiid}, ">>", $filename;
	    select $handle{$wikiid};
	    $| = 1;
	}
    }
    if (exists $handle{$wikiid}) {
	print { $handle{$wikiid} } ($_);
    } else {
	print DEFAULT ($_);
    }
}

__DATA__
wikifarm-dev.freelogy.org:443 207.216.210.143 - https://www.google.com/accounts/o8/id?id=AItOawk7ZGGKT6BRPULxU4kOQH-WVkn1vG4nnq0 [10/Jul/2010:16:41:27 -0400] "GET /smd/Wiki_Tutorial HTTP/1.1" 200 13337 "https://wikifarm-dev.freelogy.org/index.php" "Mozilla/5.0 (X11; U; Linux x86_64; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.99 Safari/533.4"
