<?php
/**
 * MIT License
 * ===========
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 *
 * @author      Sam Hellawell <sshellawell@gmail.com>
 *				outerground <outerground.com>
 * @license     MIT License https://raw.github.com/SamHellawell/wp2anchor/master/LICENSE.txt
 * @link        Official page: http://samhellawell.info
 * 				Company: http://outerground.com
 *              GitHub Repository: https://github.com/SamHellawell/wp2anchor
 */

/*
	Function to log what's happening
*/
$output="";
function wp2anchor_log($txt)
{
	global $output;
	$output .= $txt . "<br />\n";
}

//Are we using a user friendly version?
$userFriendly=false;
$hasImported=false;

if(!$userFriendly)
{
	//XML file URL
	$file = "sampledata.xml";

	//MySQL information
	$mysqlInfo = array("host" => "localhost",
					   "username" => "root",
					   "password" => "mypass",
					   "database" => "anchor");
}
else if(isset($_POST['host']) && isset($_POST['port']) && isset($_POST['user']) && isset($_POST['pass']) && isset($_POST['name']))
{
	//Upload the XML file
	if($_FILES["xmlfile"]["error"]  == 0)
	{
		//Upload the XML file
		$file = $_FILES["xmlfile"]["tmp_name"];

		//MySQL information
		$mysqlInfo = array("host" => $_POST['host'],
						   "username" => $_POST['user'],
						   "password" => $_POST['pass'],
						   "database" => $_POST['name']);
	}
	else
	{
		//Log error
		wp2anchor_log("File upload error [" . $_FILES["xmlfile"]["error"] . "]");
	}
}

//Only run if file and mysql data exist
if($file != "" && isset($mysqlInfo))
{
	/*
		Function to get the category ID from array
	*/
	function getCategoryID($title, $haystack)
	{
		$i=1;
		foreach($haystack as $arr)
		{
			if($arr["title"] == $title)
			{
				return $i;
			}
			$i++;
		}
	}

	//Get file contents (you'll see why soon)
	$fileContents = file_get_contents($file);

	//Replace namespaces (<wp:x>)
	$fileContents = preg_replace('~(</?|\s)([a-z0-9_]+):~is', '$1$2_', $fileContents);

	//Create an XML reader
	$xml = simplexml_load_string($fileContents, null, LIBXML_NOCDATA);
	$wpData = $xml->channel;

	//Get the site metadata
	$siteMeta = array("sitename"		=> $wpData->title,
					  "description" 	=> $wpData->description);

	//Get the categories
	$categories = array();
	foreach($wpData->wp_category as $wpCategory)
	{
		array_push($categories, array("title"		=> (string)$wpCategory->wp_cat_name,
									  "slug"		=> (string)$wpCategory->wp_category_nicename,
									  "description" => ""));
	}

	//Get the posts and pages
	$posts = array();
	$pages = array();
	$comments = array();
	foreach($wpData->item as $wpPost)
	{
		//Get status
		$status = $wpPost->wp_status;
		if($status == "publish") $status = "published";

		//Post or a page?
		if($wpPost->wp_post_type == "post")
		{
			//Insert into posts array
			array_push($posts, array("title"		=> (string)$wpPost->title,
									 "description"	=> (string)$wpPost->description,
									 "slug"			=> (string)$wpPost->wp_post_name,
									 "html"			=> (string)$wpPost->content_encoded,
									 "created"		=> (string)$wpPost->wp_post_date,
									 "author"		=> 1,
									 "status"		=> $status,
									 "category"		=> getCategoryID((string)$wpPost->category, $categories),
									 "comments"		=> (($wpPost->wp_comment_status == "open") ? 1 : 0)));

			//Get the comments
			foreach($wpPost->wp_comment as $wpComment)
			{
				//Insert into comments array
				array_push($comments, array("post"		=> count($posts),
											"status"	=> ($wpComment->wp_comment_approved == 1) ? "approved" : "pending",
											"date"		=> (string)$wpComment->wp_comment_date,
											"name"		=> (string)$wpComment->wp_comment_author,
											"email"		=> (string)$wpComment->wp_comment_author_email,
											"text"		=> (string)$wpComment->wp_comment_content));
			}
		}
		else if($wpPost->wp_post_type == "page")
		{
			//Insert into pages array
			array_push($pages, array("name"			=> (string)$wpPost->title,
									 "title"		=> (((string)$wpPost->description != "") ? (string)$wpPost->description : (string)$wpPost->title),
									 "slug"			=> (string)$wpPost->wp_post_name,
									 "content"		=> (string)$wpPost->content_encoded,
									 "status"		=> $status,
									 "redirect"		=> ""));
		}
	}

	//Connect to MySQL
	@$mysql = new mysqli($mysqlInfo["host"], $mysqlInfo["username"], $mysqlInfo["password"], $mysqlInfo["database"]);
	if($mysql->connect_errno > 0)
	{
	    wp2anchor_log('Unable to connect to database [' . @$mysql->connect_error . ']');
	}
	else
	{
		wp2anchor_log("Connected to the database: " . $mysqlInfo["database"]);

		//Truncate tables we need to override
		if(!@$mysql->query("TRUNCATE TABLE `categories`") ||
		   !@$mysql->query("TRUNCATE TABLE `comments`") ||
		   !@$mysql->query("TRUNCATE TABLE `page_meta`") ||
		   !@$mysql->query("TRUNCATE TABLE `post_meta`") ||
		   !@$mysql->query("TRUNCATE TABLE `posts`"))
		{
		    wp2anchor_log('Unable to clear database [' . @$mysql->error . ']');
		}
		else
		{
			wp2anchor_log("Cleared tables: `categories`, `comments`, `page_meta`, `post_meta`, `posts`");
		}

		//Update site meta data
		foreach($siteMeta as $key=>$value)
		{
			if(@$mysql->query("UPDATE `meta` SET  `value` =  '" . $mysql->escape_string($value) . "' WHERE  `key` =  '" . $mysql->escape_string($key) . "';"))
			{
				wp2anchor_log("Set site meta [<em>" . $key . "</em>]" . " to [<em>" . $value . "</em>]");
			}
			else
			{
				wp2anchor_log("Unable to set meta data [" . @$mysql->error . "]");
				break;
			}
		}

		//Insert categories
		foreach($categories as $category)
		{
			if(@$mysql->query("INSERT INTO `categories` (`id`, `title`, `slug`, `description`) VALUES (NULL, '" . $mysql->escape_string($category["title"]) . "', '" . $mysql->escape_string($category["slug"]) . "', '" . $mysql->escape_string($category["description"]) . "');"))
			{
				wp2anchor_log("Added category [<em>" . $category["title"] . "</em>]");
			}
			else
			{
				wp2anchor_log("Unable to add categories [" . @$mysql->error . "]");
				break;
			}
		}

		//Insert posts
		foreach($posts as $post)
		{
			if(@$mysql->query("INSERT INTO `posts` (`id`, `title`, `slug`, `description`, `html`, `css`, `js`, `created`, `author`, `category`, `status`, `comments`) VALUES (NULL, '" . $mysql->escape_string($post["title"]) . "', '" . $mysql->escape_string($post["slug"]) . "', '" . $mysql->escape_string($post["description"]) . "', '" . $mysql->escape_string($post["html"]) . "', '', '', '" . $mysql->escape_string($post["created"]) . "', '1', '" . $mysql->escape_string($post["category"]) . "', '" . $mysql->escape_string($post["status"]) . "', '" . $mysql->escape_string($post["comments"]) . "');"))
			{
				wp2anchor_log("Added post [<em>" . $post["title"] . "</em>]");
			}
			else
			{
				wp2anchor_log("Unable to add posts [" . @$mysql->error . "]");
				break;
			}
		}

		//Insert pages
		foreach($pages as $page)
		{
			if(@$mysql->query("INSERT INTO `pages` (`id`, `slug`, `name`, `title`, `content`, `status`, `redirect`) VALUES (NULL, '" . $mysql->escape_string($page["slug"]) . "', '" . $mysql->escape_string($page["name"]) . "', '" . $mysql->escape_string($page["title"]) . "', '" . $mysql->escape_string($page["content"]) . "', '" . $mysql->escape_string($page["status"]) . "', '" . $mysql->escape_string($page["redirect"]) . "');"))
			{
				wp2anchor_log("Added page [<em>" . $page["title"] . "</em>]");
			}
			else
			{
				wp2anchor_log("Unable to add pages [" . @$mysql->error . "]");
				break;
			}
		}

		//Insert comments
		foreach($comments as $comment)
		{
			if(@$mysql->query("INSERT INTO `comments` (`id`, `post`, `status`, `date`, `name`, `email`, `text`) VALUES (NULL, '" . $mysql->escape_string($comment["post"]) . "', '" . $mysql->escape_string($comment["status"]) . "', '" . $mysql->escape_string($comment["date"]) . "', '" . $mysql->escape_string($comment["name"]) . "', '" . $mysql->escape_string($comment["email"]) . "', '" . $mysql->escape_string($comment["text"]) . "');"))
			{
				wp2anchor_log("Added comment by [<em>" . $comment["name"] . "</em>]");
			}
			else
			{
				wp2anchor_log("Unable to add comments [" . @$mysql->error . "]");
				break;
			}
		}

		$hasImported=true;

		//Close MySQL
		@$mysql->close();
	}

	//Output
	if(!$userFriendly) echo $output;
}
?>