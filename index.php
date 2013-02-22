<?php

/* Initializing the file*/
/* Create SQL Database "twitter"
and table "leads" with the following command:

create table leads (
keyword VARCHAR(40),
username VARCHAR(40),
tweet VARCHAR(200),
image_url VARCHAR(200),
prob_neg DOUBLE,
prob_pos DOUBLE,
prob_neu DOUBLE,
label VARCHAR(8),
id BIGINT unsigned not null key,
index(keyword),
index(id)) engine myISAM;

Also ensure you have the bootstrap library from here:
http://twitter.github.com/bootstrap/index.html
 */

echo <<<_END
<html>
  <head>
    <title>Twitter Feed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
  </head>
    <script src="http://code.jquery.com/jquery.js"></script>
    <script src="bootstrap/js/bootstrap.min.js"></script>
	<div class="hero-unit">
	  <h1>Company Name</h1>
	  <p>Sales leads sourced from twitter sorted by disgruntled index</p>
	  <p>
		<form method="post">
		<input type="hidden" name="update" value="yes" />
		<input type="hidden" name="update_id" value="1" />
		<input class = "btn btn-primary" type ="submit" value ="UPDATE" />
		</form>
	  </p>
	  <p class="text-error">Twitter is rate-limited, Don't refresh very often</p>
	</div>
	<body>
_END;

/* Initialize Database First */
$db_server = init_db();

/* Initialize Database
Database: twitter
Table: leads */
function init_db()
{
	$db_hostname = 'localhost:3306';
	$db_username = 'root';
	$db_password = 'root';

    /* Connect to SQL database */
	$db_server = mysql_connect($db_hostname,$db_username,$db_password);
	if (!$db_server) die("Unable to connect to MySQL:" . mysql_error());
	

	/* Select database */
	$db_database = 'twitter';
	mysql_select_db($db_database) or die("Unable to select database: " . mysql_error());
		
	return $db_server; /*Success */
}
/* End of function init_db() */

/* Capture Update
Execute Main Body */
if (isset($_POST['update']))
{}

/* Capture Delete */
if (isset($_POST['delete']) && isset($_POST['delete_id']))
{
	$tid = $_POST['delete_id'];
	$del_query = "DELETE from leads where id = $tid";
	
	if (!mysql_query($del_query, $db_server))
	echo "DELETE failed: " . mysql_error() . "<br />";

    read_twitter_data("all");
    mysql_close($db_server);
    die();
}

/* This fetches all the twitter data */
get_all_twitter_data();

/* Add all keywords for Twitter
   Enter Competitors here
   Example: "burgerking" */
function get_all_twitter_data()
{
	get_twitter_data("burgerking");
}

/* Get Twitter Data by prodiving a search string and store it in database */
function get_twitter_data($search_string)
{
	/* Modify rpp upto 100 */
	$path = "http://search.twitter.com/search.json?q=%40" . $search_string . "&rpp=5&result_type=recent";


	/* Get the JSON data from Twitter */
	$twitter_data = file_get_contents("$path");
	if (!$twitter_data) die ("Unable to fetched Twitter Data");
	
	/* Decode the JSON data and put it in an array
	Hack here for unknown json data - feel free to change */
	$twitter_json = json_decode($twitter_data, true);
	if (!$twitter_json) return 1;
	
	/* Read the Tweets and Display */
	foreach ($twitter_json['results'] as $data)
	{

		/* Initialize local variables and make text SQL happy */
		$keyword = mysql_real_escape_string($search_string);
		$username = mysql_real_escape_string($data['from_user']);
		$tweet =  mysql_real_escape_string ($data['text']);

		$image_url = $data['profile_image_url'];
		$id = $data['id'];
				
		/* reset Array */		
		$output = array();	
				
		/* Fetch sentiment analysis to determine the label */
		$cmd = "curl -d 'text=$tweet' http://text-processing.com/api/sentiment/";
		exec(escapeshellcmd($cmd), $output, $status);
		if ($status) echo "Exec command failed for Command = $cmd";
		
		/* Decode Sentiment JSON
		   Hack here for break in case unknown JSON */
		$sentiment_json = json_decode($output[0],true);
		if (!$sentiment_json) break;

                /* reset Array */
                unset($output);
               

		/* Load local variables of sentiment analysis */
		$prob_neg = $sentiment_json['probability']['neg'];
		$prob_pos = $sentiment_json['probability']['pos'];
		$prob_neu = $sentiment_json['probability']['neutral'];
		$label = $sentiment_json['label'];
				
		/* Store it in the database */
		$db_result = update_db($keyword, $username, $tweet, $image_url, $prob_neg, $prob_pos, $prob_neu, $label, $id);
	}

	return 1; /* Success */
}
/* End of function get_twitter_data() */

/* Update Database
Database: twitter
Table: leads */
function update_db($keyword, $username, $tweet, $image_url, $prob_neg, $prob_pos, $prob_neu, $label, $id)
{
	/* Check if the row exists */
	$result = mysql_query("SELECT * FROM leads WHERE id = $id");
	$num_rows = mysql_num_rows($result);

    /* if row exists just return success */
    if ($num_rows == 1) return 1;
	
	/* Store it in the database for reads*/
	$db_query = "INSERT into leads VALUES('$keyword', '$username', '$tweet', '$image_url', $prob_neg, $prob_pos, $prob_neu, '$label', $id)";
	$row_result = mysql_query($db_query);
	if (!$row_result) die ("Table insertion failed for : " . mysql_error());
	
	return $row_result;
}

/* Read all Twitter data */
read_twitter_data("all");

/* Read Twitter Data */
function read_twitter_data($keyword)
{
	/* Order by label, prob_neg desc and prob_pos asc */
	if ($search_string = "all") $db_query = "SELECT * from leads ORDER BY label, prob_neg DESC, prob_pos ASC";
	else $db_query = "SELECT * from leads where keyword =" . $keyword;
	
	$read_result = display_db($db_query);
	if (!$read_result) die ("Display Database failed");
	
	return 1; /*Success */
} /* End of read_twitter_data() */

/* Display Database */
function display_db($dquery)
{
	/* Get all the rows from the table */
	$dresult = mysql_query($dquery);
	if (!$dresult) die ("Select * database query failed: " . mysql_error());

	$rows = mysql_num_rows($dresult);
	if (!$rows) die ("Database is empty - Nothing to Display: " . mysql_error());

    /* Header for the table */
	echo "<table class='table table-bordered' class='span10'><tr> <th>Image</th> <th>Username</th> <th>Competitor</th><th>Tweet</th>";
	echo "<th>Negativity</th><th>Neutrality</th><th>Positivity</th><th>Label</th><th>Action</th></tr>";

    /* Loop through each row */
	for ($j=0; $j < $rows; $j++)
	{
		/* Fetch the row */
		$temp_row = mysql_fetch_row($dresult);
		if (!$temp_row) die ("SQL Fetch Row failed: " . mysql_error());
		
		/* Begin row execution and color code based on label */
		if ($temp_row[7] == 'neg') echo "<tr class='success'>";
		else if($temp_row[7] == 'neutral') echo "<tr class='warning'>";
		else echo "<tr class='error'>";
		
		/* Display Image */
		echo "<td>";
		echo "<img name='myimage' src=". $temp_row[3] . " class='img-polaroid' width='40' height='40' alt='word' />";
		echo "</td>";

		/* Display Username with links */
		echo "<td><a href='https://www.twitter.com/" .  $temp_row[1] . "'>" . $temp_row[1] . "</a></td>";

		/* Display Competitor Name */
		echo "<td><strong>". $temp_row[0]. "</strong></td>";

		/* Display other values */
		echo "<td>$temp_row[2]</td>";
		echo "<td>$temp_row[4]</td><td>$temp_row[6]</td><td>$temp_row[5]</td><td>$temp_row[7]</td>";
		
		echo "<td>"; /* Add delete button */
echo <<<_END
			<form method="post">
			<input type="hidden" name="delete" value="yes" />
			<input type="hidden" name="delete_id" value="$temp_row[8]" />
			<input class = "btn btn-danger" type ="submit" value ="DELETE" />
			</form>
_END;
		echo "</td>"; /* End delete button execution */
	    
		echo "</tr>"; /* End row execution */
	
	}
	echo "</table>"; /* End table */

	return 1; /* success */
} /* End of display_db() */

/* Close the database server */
mysql_close($db_server);

echo "</body></html>"; /* Woohoo */

?>
