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
 *              outerground <outerground.com>
 * @license     MIT License https://raw.github.com/SamHellawell/wp2anchor/master/LICENSE.txt
 * @link        Official page: http://samhellawell.info
 *              Company: http://outerground.com
 *              GitHub Repository: https://github.com/SamHellawell/wp2anchor
 * 
 * - Use of libxml internal errors info.
 * - Modifications in namespace tag prefixes.
 * - Store categories and tags in new extended fields, so they won't be lost
 *   in the import process.
 * - Save WordPress category descriptions.
 * - WordPress slugs (which use urlencode) changed to the same kind of url slugs
 *   used by Anchor, more human-readable.
 * - WordPress does not export the posts comments ordered, it is done here.
 *
 * @author      neverbot
 * @link        https://github.com/neverbot
 *
 */


// List of substitutions/fixes needed to be done in the xml wordpress file
// before the import process. This namespace prefixes will be
// changed from whatever:xxx to whatever_xxx
$namespaces = ['xmlns:', 'wp:', 'dc:', 'content:', 'excerpt:'];

// Anchor only allows one category per posts, while we can have many in a 
// wordpress post. If $useMultipleCategories == true, new fields
// will be added to the anchor_extend table to store this info per post
// (won't be usable from anchor, but will be stored for future use, 
// like a plugin who admits multiple categories per post)
$useMultipleCategories = true;
// Name, slug and description of the common category for all the 
// imported posts
// Only used if $useMultipleCategories == true
$importedCategory = ['Imported', 
                     'imported',
                     'Post imported with wp2anchor'];
// Fields for extended post info                     
// Only used if $useMultipleCategories == true
$importedExtendedInfo = array('imported-by' => 'Importer',
                              'imported-categories' => 'Old categories',
                              'imported-tags' => 'Old tags');


/*
  Function to log what's happening
*/
$output="";
function wp2anchor_log($txt)
{
  global $output;
  $output .= $txt . "<br />\n";
}

$hasImported=false;
if(isset($_POST['host']) && isset($_POST['port']) && isset($_POST['user']) && isset($_POST['pass']) && isset($_POST['name']))
{
  // Upload the XML file
  if($_FILES["xmlfile"]["error"]  == 0)
  {
    // Upload the XML file
    $file = $_FILES["xmlfile"]["tmp_name"];

    // MySQL information
    $mysqlInfo = array("host" => $_POST['host'],
               "username" => $_POST['user'],
               "password" => $_POST['pass'],
               "database" => $_POST['name']);
  }
  else
  {
    // Log error
    switch($_FILES["xmlfile"]["error"])
    {
      case 1:
      case 2:
        wp2anchor_log("File is too big, maybe alter the upload_max_filesize directive in php.ini?");
      break;

      case 3:
        wp2anchor_log("The file was only partially uploaded, try again!");
      break;

      case 4:
        wp2anchor_log("No file was uploaded, you sure you selected one?");
      break;

      case 6:
        wp2anchor_log("Missing a temporary folder <a href=\"http:// bit.ly/HkrsDA\">http:// bit.ly/HkrsDA</a>");
      break;

      default:
        wp2anchor_log("File upload error [" . $_FILES["xmlfile"]["error"] . "]");
      break;
    }
  }
}

// Only run if file and mysql data exist
if(isset($file) && $file != "" && isset($mysqlInfo))
{
  /*
    Function to get the category ID from array
  */
  function getCategoryID($title, $haystack)
  {
    $i = 1;
    foreach($haystack as $arr)
    {
      if($arr["title"] == $title)
      {
        return $i;
      }
      $i++;
    }
    return 0;
  }

  // Same slug creation code as in Anchor CMS, so the URLs are built the same way
  function slug($str, $separator = '-') 
  {
    $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');

    $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');

    $str = str_replace($a, $b, $str);

    // replace non letter or digits by separator
    $str = preg_replace('#[^A-z0-9]#', $separator, $str);

    return trim(strtolower($str), $separator);
  }

  // To order comment lists, which comes from the WordPress export unordered
  function compare_comment_ids($a, $b)
  {
    if ((int)$a->wp_comment_id == (int)$b->wp_comment_id) return 0;
    return ((int)$a->wp_comment_id < (int)$b->wp_comment_id) ? -1 : 1;
  }  
  function compare_posts_ids($a, $b)
  {
    if ((int)$a->wp_post_id == (int)$b->wp_post_id) return 0;
    return ((int)$a->wp_post_id < (int)$b->wp_post_id) ? -1 : 1;
  } 

  // Get file contents (you'll see why soon)
  $fileContents = file_get_contents($file);

  // Replace namespaces so the simplexml library does not
  // die in panic
  foreach ($namespaces as $prefix)
  {
    $newPrefix = str_replace(':', '_', $prefix);
    $fileContents = str_replace($prefix, $newPrefix, $fileContents);
    
    wp2anchor_log('Namespace prefix changed from "'.$prefix.'" to "'.$newPrefix.'"');
  }

  // Prepare xml error logger
  libxml_use_internal_errors(true);

  // Create an XML reader
  $xml = simplexml_load_string($fileContents, null, LIBXML_NOCDATA);
  $wpData = $xml->channel;

  // Log errors (if any)
  if (!$xml)
  {
    $errors = libxml_get_errors();
    wp2anchor_log('<strong>There are errors in the imported xml file:</strong>');
    foreach ($errors as $error)
    {
      wp2anchor_log('Error '.$error->code.': '.trim($error->message));
    }
    wp2anchor_log('');
    libxml_clear_errors();
  }

    // Get the site metadata
  $siteMeta = array("sitename"    => $wpData->title,
            "description"   => $wpData->description);

  // Get the categories
  $categories = array();

  // Using multiple categories
  if ($useMultipleCategories == true)
  {
    array_push($categories, array('title' => $importedCategory[0],
                    'slug' => slug(urldecode($importedCategory[1])),
                    'description' => $importedCategory[2]));
  }

  foreach($wpData->wp_category as $wpCategory)
  {
    array_push($categories, array("title"       => (string)$wpCategory->wp_cat_name,
                                  "slug"        => slug(urldecode((string)$wpCategory->wp_category_nicename)),
                                  "description" => (string)$wpCategory->wp_category_description));
  }

  // Get the tags
  $tags = array();

  foreach($wpData->wp_tag as $wpTag)
  {
    array_push($tags, array("title"       => (string)$wpTag->wp_tag_name,
                            "slug"        => slug(urldecode((string)$wpTag->wp_tag_slug)),
                            "description" => (string)$wpTag->wp_tag_description));
  }


  // Get the posts and pages
  $posts = array();
  $pages = array();
  $comments = array();

  // Sometimes WordPress does not return the posts ordered by date/id
  // we have to order them by ourselves to be sure
  $orderedWpData = [];

  foreach($wpData->item as $wpPost)
    array_push($orderedWpData, $wpPost);

  usort($orderedWpData, 'compare_posts_ids');

  $wpData = null;

  foreach($orderedWpData as $wpPost)
  {
    // Get status
    $status = $wpPost->wp_status;
    if($status == "publish") $status = "published";

    // Post or a page?
    if($wpPost->wp_post_type == "post")
    {
      // Using multiple categories
      if ($useMultipleCategories == true)
      {
        $postCategories = array();
        $postTags = array();

        foreach($wpPost->category as $term)
        {
          // It's indeed a category
          if (($cat = getCategoryID((string)$term, $categories)) != 0) 
            array_push($postCategories, $term);
          else
            // If it's not a category, it's a tag
            array_push($postTags, $term);
        }

        // Insert into posts array
        array_push($posts, array("title"    => (string)$wpPost->title,
                   "description" => (string)$wpPost->description,
                   "slug"        => slug(urldecode((string)$wpPost->wp_post_name)),
                   "html"        => (string)$wpPost->content_encoded,
                   "created"     => (string)$wpPost->wp_post_date,
                   "author"      => 1,
                   "status"      => $status,
                   "category"    => getCategoryID((string)$importedCategory[0], $categories),
                   "comments"    => (($wpPost->wp_comment_status == "open") ? 1 : 0),
                   "saved-cat"   => implode(',', $postCategories),
                   "saved-tag"   => implode(',', $postTags),
                   ));
      }
      else
      {
        // Insert into posts array
        array_push($posts, array("title"    => (string)$wpPost->title,
                   "description" => (string)$wpPost->description,
                   "slug"        => slug(urldecode((string)$wpPost->wp_post_name)),
                   "html"        => (string)$wpPost->content_encoded,
                   "created"     => (string)$wpPost->wp_post_date,
                   "author"      => 1,
                   "status"      => $status,
                   "category"    => getCategoryID((string)$wpPost->category, $categories),
                   "comments"    => (($wpPost->wp_comment_status == "open") ? 1 : 0)));
      }

      // Get the comments
      $postComments = [];

      // The WordPress export does not return the comments ordered
      foreach($wpPost->wp_comment as $wpComment)
        array_push($postComments, $wpComment);

      usort($postComments, 'compare_comment_ids');

      foreach($postComments as $wpComment)
      {
        // Insert into comments array
        array_push($comments, array("post"    => count($posts),
                      "status"  => ($wpComment->wp_comment_approved == 1) ? "approved" : "pending",
                      "date"    => (string)$wpComment->wp_comment_date,
                      "name"    => (string)$wpComment->wp_comment_author,
                      "email"   => (string)$wpComment->wp_comment_author_email,
                      "text"    => (string)$wpComment->wp_comment_content));
      }
    }
    else if($wpPost->wp_post_type == "page")
    {
      // Insert into pages array
      array_push($pages, array("name"      => (string)$wpPost->title,
                   "title"     => (((string)$wpPost->description != "") ? (string)$wpPost->description : (string)$wpPost->title),
                   "slug"      => slug(urldecode((string)$wpPost->wp_post_name)),
                   "content"   => (string)$wpPost->content_encoded,
                   "status"    => $status,
                   "redirect"  => ""));
    }
  }

  // Connect to MySQL
  class mysqli_utf extends mysqli {
      public function __construct($host = NULL, $username = NULL, $password = NULL, $database = NULL) {
        parent::__construct($host, $username, $password, $database);
        $this->set_charset("utf8");
      }
  }
  
  @$mysql = new mysqli_utf($mysqlInfo["host"], $mysqlInfo["username"], $mysqlInfo["password"], $mysqlInfo["database"]);
  if($mysql->connect_errno > 0)
  {
      wp2anchor_log('Unable to connect to database [' . @$mysql->connect_error . ']');
  }
  else
  {
    wp2anchor_log("Connected to the database: " . $mysqlInfo["database"]);

    // Set prefix
    $prefix = $mysql->real_escape_string($_POST['prefix']);

    // Truncate tables we need to override
    if(!@$mysql->query("TRUNCATE TABLE `" . $prefix . "categories`") ||
       !@$mysql->query("TRUNCATE TABLE `" . $prefix . "comments`") ||
       !@$mysql->query("TRUNCATE TABLE `" . $prefix . "extend`") ||
       !@$mysql->query("TRUNCATE TABLE `" . $prefix . "page_meta`") ||
       !@$mysql->query("TRUNCATE TABLE `" . $prefix . "post_meta`") ||
       !@$mysql->query("TRUNCATE TABLE `" . $prefix . "posts`"))
    {
        wp2anchor_log('Unable to clear database [' . @$mysql->error . ']');
    }
    else
    {
      wp2anchor_log("Cleared tables: " . $prefix . "categories, " . 
                                         $prefix . "comments, " . 
                                         $prefix . "extend, " . 
                                         $prefix . "page_meta, " . 
                                         $prefix . "post_meta, " . 
                                         $prefix . "posts" .
                                         ".");
    }

    // Update site meta data
    foreach($siteMeta as $key=>$value)
    {
      if(@$mysql->query("UPDATE `" . $prefix . "meta` SET  `value` =  '" . $mysql->escape_string($value) . "' WHERE  `key` =  '" . $mysql->escape_string($key) . "';"))
      {
        wp2anchor_log("Set site meta [<em>" . $key . "</em>]" . " to [<em>" . $value . "</em>]");
      }
      else
      {
        wp2anchor_log("Unable to set meta data [" . @$mysql->error . "]");
        break;
      }
    }

    // Using multiple categories
    if ($useMultipleCategories == true)
    {
      foreach($importedExtendedInfo as $key => $value)
      {
        // Create extend post fields
        if(@$mysql->query("INSERT INTO `" . $prefix . "extend` ".
            "(`id`, `type`, `field`, `key`, `label`, `attributes`) VALUES ".
            "(NULL, 'post', 'text', '".$mysql->escape_string($key)."', '".$mysql->escape_string($value)."', '');"))
        {
          wp2anchor_log("Extended post info [<em>".$mysql->escape_string($value)."</em>]");
        }
        else
        {
          wp2anchor_log("Unable to extend post info [" . @$mysql->error . "]");
          break;
        }        
      }
    }

    // Insert categories
    foreach($categories as $category)
    {
      if(@$mysql->query("INSERT INTO `" . $prefix . "categories` (`id`, `title`, `slug`, `description`) VALUES (NULL, '" . $mysql->escape_string($category["title"]) . "', '" . $mysql->escape_string($category["slug"]) . "', '" . $mysql->escape_string($category["description"]) . "');"))
      {
        wp2anchor_log("Added category [<em>" . $category["title"] . "</em>]");
      }
      else
      {
        wp2anchor_log("Unable to add categories [" . @$mysql->error . "]");
        break;
      }
    }

    $postId = 1;
    
    // Insert posts
    foreach($posts as $post)
    {
      if(@$mysql->query("INSERT INTO `" . $prefix . "posts` (`id`, `title`, `slug`, `description`, `html`, `css`, `js`, `created`, `author`, `category`, `status`, `comments`) VALUES (NULL, '" . $mysql->escape_string($post["title"]) . "', '" . $mysql->escape_string($post["slug"]) . "', '" . $mysql->escape_string($post["description"]) . "', '" . $mysql->escape_string($post["html"]) . "', '', '', '" . $mysql->escape_string($post["created"]) . "', '1', '" . $mysql->escape_string($post["category"]) . "', '" . $mysql->escape_string($post["status"]) . "', '" . $mysql->escape_string($post["comments"]) . "');"))
      {
        wp2anchor_log("Added post [<em>" . $post["title"] . "</em>]");

        // Insert meta info (categories and tags)
        if(@$mysql->query("INSERT INTO `" . $prefix . "post_meta` ".
            "(`id`, `post`, `extend`, `data`) VALUES ".
            "(NULL, '".$postId."', '1', '{\"text\":\"wp2anchor\"}')".
            ((empty($post['saved-cat']))?'':(", (NULL, '".$postId."', '2', '{\"text\":\"".$post['saved-cat']."\"}')")).
            ((empty($post['saved-tag']))?'':(", (NULL, '".$postId."', '3', '{\"text\":\"".$post['saved-tag']."\"}')")).
            ";"))
        {
        }
        else
        {
          wp2anchor_log("Unable to add post meta info [" . @$mysql->error . "]");
        }

      }
      else
      {
        wp2anchor_log("Unable to add posts [" . @$mysql->error . "]");
        break;
      }

      $postId++;
    }

    // Insert pages
    foreach($pages as $page)
    {
      if(@$mysql->query("INSERT INTO `" . $prefix . "pages` (`id`, `slug`, `name`, `title`, `content`, `status`, `redirect`) VALUES (NULL, '" . $mysql->escape_string($page["slug"]) . "', '" . $mysql->escape_string($page["name"]) . "', '" . $mysql->escape_string($page["title"]) . "', '" . $mysql->escape_string($page["content"]) . "', '" . $mysql->escape_string($page["status"]) . "', '" . $mysql->escape_string($page["redirect"]) . "');"))
      {
        wp2anchor_log("Added page [<em>" . $page["title"] . "</em>]");
      }
      else
      {
        wp2anchor_log("Unable to add pages [" . @$mysql->error . "]");
        break;
      }
    }

    // Insert comments
    foreach($comments as $comment)
    {
      if(@$mysql->query("INSERT INTO `" . $prefix . "comments` (`id`, `post`, `status`, `date`, `name`, `email`, `text`) VALUES (NULL, '" . $mysql->escape_string($comment["post"]) . "', '" . $mysql->escape_string($comment["status"]) . "', '" . $mysql->escape_string($comment["date"]) . "', '" . $mysql->escape_string($comment["name"]) . "', '" . $mysql->escape_string($comment["email"]) . "', '" . $mysql->escape_string($comment["text"]) . "');"))
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

    // Close MySQL
    @$mysql->close();
  }
}
?>
<?php
/*

  HERE BEGINS THE USER FRIENDLY SECTION, IT'S REALLY MESSY

*/
?>
<!doctype html>
<html lang="en-gb">
  <head>
    <meta charset="utf-8">
    <title>Installin' Anchor CMS</title>
    <meta name="robots" content="noindex, nofollow">

    <style>
      *{-webkit-font-smoothing:antialiased;margin:0;padding:0}
      a{-webkit-transition:color .2s;font-weight:500;color:#a0afc6;text-decoration:none}
      html{background:#444f5f;color:#8797af;font:15px/25px "Helvetica Neue", "Open Sans", "DejaVu Sans", Arial, sans-serif}
      body{width:960px;margin:40px auto}
      body > small{display:block;padding-top:30px;color:#7f8c9f}
      body > small a{color:#adbdd5;font-weight:500;text-decoration:none}
      .small{background:#89b92c;color:#86936c}
      .small nav,.small small{display:none}
      .small .content{background:#fff;width:360px;height:100px;position:absolute;left:50%;top:50%;margin:-90px 0 0 -230px}
      .small h1{color:#6b931e;font-size:29px;font-weight:300;text-align:center;padding:10px 0 25px}
      .small a{background:#e5eed5;color:#7aa031;float:left;border-radius:5px;font-size:13px;font-weight:500;padding:6px 18px}
      .small a:hover{background:#e0ecc8;color:#7aa031}
      .small a + a{float:right}
      nav{overflow:hidden;padding-bottom:40px}
      nav ul{float:right;height:1px;margin-top:10px;border-bottom:1px solid #576477}
      nav li,nav img{float:left;margin-right:80px}
      nav li{list-style:none;font-size:13px;font-weight:500;background:#444f5f;color:#70829b;margin-top:-11px;padding:0 10px}
      nav li:last-child{margin-right:0}
      .content{background:#36404e;border-radius:5px;padding:40px 50px}
      .options{margin-top:2em}
      article h1{font-size:29px;line-height:35px;font-weight:300;color:#fff;padding-bottom:15px}
      article ul{padding-left:2em}
      article ul li{margin:6px 0}
      article code{display:inline-block;border-radius:4px;background:#3C4757;padding:0 6px}
      fieldset{border:none}
      form{width:900px;margin-top:2em}
      form p{position:relative;margin-bottom:15px}
      form i{position:absolute;left:0;top:18px;font-size:11px;font-style:normal;color:#65758c}
      label{font-size:13px;line-height:18px;font-weight:500;display:block;padding-top:3px;float:left;width:220px;cursor:pointer}
      input,textarea,select{background-color:#ffffff;border:none;border-radius:5px;font:13px/25px "Helvetica Neue", "Open Sans", "DejaVu Sans", Arial, sans-serif;color:#576170;width:400px;margin-bottom:10px;padding:8px 20px}
      textarea{resize:vertical;min-height:150px;max-height:600px;padding:12px 20px}
      input:focus,textarea:focus{outline:none}
      select{width:440px;background:#fff;padding:10px 20px}
      .error{width:810px;margin-bottom:25px;background:#b43e27;color:#fff;border-radius:5px;padding:10px 20px}
      .btn{border:none;border-radius:5px;display:inline-block;font:500 13px/18px "Helvetica Neue", sans-serif;background:#3f75c3;color:#fff;cursor:pointer;padding:11px 20px}
      .btn:hover{background:#4680d3}
      form .btn{margin-left:220px}
      body > small a:hover,nav li.elapsed,a:hover{color:#fff}
      .small .options,form .error p{margin:0}
    </style>
  </head>
  <body>
    <section class="content">
      <article>
        <h1>wp2anchor importer</h1>
        <?php if($output != "") { ?>
        <p><?php echo $output; ?><br /></p>
        <?php } ?>
      <?php if(!$hasImported) { ?>
        <p>Enter the <strong>exact</strong> database details for your exisitng Anchor installation, then select your Wordpress XML file.
          <br />No Wordpress XML file? Go to your Wordpress Dashboard &raquo; Tools &raquo; Export. Done!
        </p>
      </article>

      <form method="post" enctype="multipart/form-data" autocomplete="off">

        <fieldset>
          <p>
              <label for="host">Database Host</label>
              <input id="host" name="host" value="127.0.0.1">

              <i>Most likely <b>localhost</b> or <b>127.0.0.1</b>.</i>
            </p>

          <p>
              <label for="port">Port</label>
              <input id="port" name="port" value="3306">

              <i>Usually <b>3306</b>.</i>
            </p>

          <p>
              <label for="user">Username</label>
              <input id="user" name="user" value="root">

              <i>The database user, usually <b>root</b>.</i>
          </p>

          <p>
              <label for="pass">Password</label>
              <input id="pass" name="pass" value="pass">

              <i>Leave blank for empty password.</i>
            </p>

          <p>
              <label for="name">Database Name</label>
              <input id="name" name="name" value="anchor">

              <i>Your database’s name.</i>
            </p>

          <p>
              <label for="name">Table Prefix</label>
              <input id="prefix" name="prefix" value="anchor_">

              <i>Your database’s table prefix.</i>
            </p>

          <p>
              <label for="xmlfile">XML file</label>
              <input type="file" name="xmlfile" id="xmlfile">

              <i>Your exported Wordpress XML data file.</i>
            </p>
        </fieldset>

        <section class="options">
          <button type="submit" class="btn">Import &raquo;</button>
        </section>
      </form>
    </section>
    <?php } ?>
    <small>
      Wordpress to Anchor importer by <a href="http:// samhellawell.info">Sam Hellawell</a>.
      Some modifications done by <a href="https://github.com/neverbot">neverbot</a>.
      Using Anchor's installation template.
    </small>
  </body>
</html>
