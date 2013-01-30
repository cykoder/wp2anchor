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

//Temporary file data URL, adding upload form later
$file = "sampledata.xml";

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

	//Get the comments
	foreach($wpPost->wp_comment as $wpComment)
	{
		print_r($wpComment);
	}
}

//Print XML
print_r($comments);

?>