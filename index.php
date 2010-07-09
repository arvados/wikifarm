<html>
<head>
<link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
<br>
<br>
Links
<ul>
<li><a href="smd/Wiki_Tutorial">Wiki Tutorial</a>
<li><a href="table.php">Excel -> Wiki Table converter</a>
</ul>

Wikis
<ol>
<?php
     ;
$db = new SQLite3 (getenv("WIKIFARM_DB_FILE"));
$result = $db->query ('select id, wikiname, realname from wikis order by id');
while ($row = $result->fetchArray()) {
    if ($row[2])
	print "<li value=\"$row[0]\"><a href=\"$row[1]/\">$row[2]</a>\n";
}
?>
</ol>


</body>
</html>
