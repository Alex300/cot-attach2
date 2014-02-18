<?php defined('COT_CODE') or die('Wrong URL');
/**
 * Attachments 2.x API
 *
 * @package Attachments
 * @author Trustmaster
 * @copyright Copyright (c) 2008-2013, Vladimir Sibirov. All rights reserved. Distributed under BSD License.
 */

include cot_langfile('attach2', 'plug');

if (!isset($GLOBALS['db_attach'])) $GLOBALS['db_attach'] = $GLOBALS['db_x'] . 'attach';
$GLOBALS['att_item_cache'] = array();

/**
 * Returns original name of a file being uploaded
 * @param  string $input Input name
 * @return string        Original file name and extension
 */
function att_get_filename($input)
{
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		return $_FILES[$input]['name'];
	}
	else
	{
		return $_GET[$input];
	}
}

/**
 * Returns size of a file being uploaded
 * @param  string $input Input name
 * @return integer       File size in bytes
 */
function att_get_filesize($input)
{
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		return $_FILES[$input]['size'];
	}
	else
	{
		return (int) $_SERVER['CONTENT_LENGTH'];
	}
}

/**
 * Checks if the file has been uploaded and the size is
 * acceptable and returns the file stream if necessary.
 * @param  string $input Input name (only for POST)
 * @return mixed         Uploaded file stream (for GET, PUT, etc.) or input name (only for POST)
 */
function att_get_uploaded_file($input = '')
{
	$limits = att_get_limits();
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if ($_FILES[$input]['size'] > 0 && is_uploaded_file($_FILES[$input]['tmp_name']))
		{
			if ($_FILES[$input]['size'] > $limits['file'])
			{
				cot_error('att_err_toobig');
			}
			if ($_FILES[$input]['size'] > $limits['left'])
			{
				cot_error('att_err_nospace');
			}
		}
		else
		{
			cot_error('att_err_upload');
		}
		return $input;
	}
	else
	{
		$input = fopen('php://input', 'r');
		while (!feof($input))
			$temp .= fread($input, att_get_filesize(''));
		$temp = tmpfile();
		$size = stream_copy_to_stream($input, $temp);
		fclose($input);

		if (!$size)
		{
			cot_error('att_err_upload');
		}
		else
		{
			if ($size > $limits['file'])
			{
				cot_error('att_err_toobig');
			}
			if ($size > $limits['left'])
			{
				cot_error('att_err_nospace');
			}
		}
		return $temp;
	}
}

/**
 * Saves an uploaded file regardless of request method.
 * @param  mixed   $input A value returned by att_get_uploaded_file()
 * @param  string  $path  Target path
 * @return boolean        true on success, false on error
 */
function att_save_uploaded_file($input, $path)
{
	if (cot_error_found())
	{
		return false;
	}
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		return move_uploaded_file($_FILES[$input]['tmp_name'], $path);
	}
	else
	{
		$target = fopen($path, 'w');
		if (!$target)
		{
			return false;
		}
		fseek($input, 0, SEEK_SET);
		stream_copy_to_stream($input, $target);
		fclose($target);
		return true;
	}
}

/**
 * Calculates attachment path.
 * @param  string $area Module or plugin code
 * @param  int    $item Parent item ID
 * @param  int    $id   Attachment ID
 * @param  string $ext  File extension. Leave it empty to auto-detect.
 * @return string       Path for the file on disk
 */
function att_path($area, $item, $id, $ext = '')
{
	global $cfg;
	if (empty($ext))
	{
		// Auto-detect extension
		$mask = $cfg['plugin']['attach2']['folder'] . '/' . $area . '/' . $item . '/'
			. $cfg['plugin']['attach2']['prefix'] . $id . '.*';
		$files = glob($mask, GLOB_NOSORT);
		if (!$files || count($files) == 0)
		{
			return false;
		}
		else
		{
			return $files[0];
		}
	}
	else
	{
		return $cfg['plugin']['attach2']['folder'] . '/' . $area . '/' . $item . '/'
			. $cfg['plugin']['attach2']['prefix'] . $id . '.' . $ext;
	}
}

/**
 * Calculates path for attachment thumbnail.
 * @param  int    $id     Attachment ID
 * @param  int    $width  Thumbnail width
 * @param  int    $height Thumbnail height
 * @param  int    $frame  Thumbnail framing mode
 * @return string         Path for the file on disk or false file was not found
 */
function att_thumb_path($id, $width, $height, $frame)
{
	global $cfg;
	$thumbs_folder = $cfg['plugin']['attach2']['folder'] . '/_thumbs/' . $id;
	$mask = $thumbs_folder . '/'
		. $cfg['plugin']['attach2']['prefix'] . $id
		. '-' . $width . 'x' . $height . '-' . $frame . '.*';
	$files = glob($mask, GLOB_NOSORT);
	if (!$files || count($files) == 0)
	{
		return false;
	}
	else
	{
		return $files[0];
	}
}

/**
 * Returns paths to all thumbnails found for a given attachment.
 * @param  int    $id   Attachment ID
 * @return array        Array of paths or false on error
 */
function att_thumb_paths($id)
{
	global $cfg;
	$thumbs_folder = $cfg['plugin']['attach2']['folder'] . '/_thumbs/' . $id;
	$path = $thumbs_folder . '/'
	 . $cfg['plugin']['attach2']['prefix'] . $id;
	return glob($path . '-*', GLOB_NOSORT);
}

/**
 * Extracts filename extension with tar (.tar.gz, tar.bz2, etc.) support.
 * @param  string $filename File name
 * @return string           File extension or false on error
 */
function att_get_ext($filename)
{
	if (preg_match('#((\.tar)?\.\w+)$#', $filename, $m))
	{
		return mb_strtolower(mb_substr($m[1], 1));
	}
	else
	{
		return false;
	}
}

/**
 * Gets upload space limits.
 *
 * @return array
 */
function att_get_limits()
{
	global $db_attach, $usr, $db, $cfg;

	$res = array();
	$res['file']  = $cfg['plugin']['attach2']['filesize'] > 0 ? (int)$cfg['plugin']['attach2']['filesize'] : 100000000000000000 ;
	$res['total'] = $cfg['plugin']['attach2']['filespace'] > 0 ? (int)$cfg['plugin']['attach2']['filespace'] : 100000000000000000 ;
	$res['used']  = (int) $db->query("SELECT SUM(att_size) FROM $db_attach WHERE att_user = {$usr['id']}")->fetchColumn();
	$res['left']  = $res['total'] - $res['used'];
	return $res;
}

/**
 * Returns an icon for a given extension and size
 * @param  string  $ext  File extension
 * @param  integer $size Icon size in pixels
 * @return string        Path to icon
 */
function att_icon($ext, $size = 48)
{
	global $cfg;
	if (!file_exists($cfg['plugins_dir'] . "/attach2/img/types/$size"))
	{
		$size = 48;
	}
	if (file_exists($cfg['plugins_dir'] . "/attach2/img/types/$size/$ext.png"))
	{
		return $cfg['plugins_dir'] . "/attach2/img/types/$size/$ext.png";
	}
	else
	{
		return $cfg['plugins_dir'] . "/attach2/img/types/$size/archive.png";
	}
}

/**
 * Checks if file extension is allowed for upload. Returns error message or empty string.
 * Emits error messages via cot_error().
 *
 * @param  string  $ext   File extension
 * @return boolean        true if all checks passed, false if something was wrong
 */
function att_check_file($ext)
{
	global $cfg;
	$valid_exts = explode(',', $cfg['plugin']['attach2']['exts']);
	$valid_exts = array_map('trim', $valid_exts);
	if (empty($ext) || !in_array($ext, $valid_exts))
	{
		//cot_error('att_err_type');
		return false;
	}
	return true;
}

/**
 * Returns the number of files already attached to an item
 * @param  string $area Target module/plugin code.
 * @param  integer $item Target item id.
 * @param  string $field
 * @return integer
 */
function att_count_files($area, $item, $field = '_all_')
{
	global $db, $db_attach;

    $whereFileld = '';
    if($field != '_all_') $whereFileld = "AND att_field=".$db->quote($field);

	return $db->query("SELECT COUNT(*) FROM $db_attach WHERE att_area = ? AND att_item = ? $whereFileld", array($area, (int)$item))->fetchColumn();
}

/**
 * Adds a new attachment to the database and disk.
 *
 * @param  string  $area      Target module/plugin code.
 * @param  int     $item      Target item id.
 * @param  string  $input     Upload field name.
 * @param  string  $title     Attachment caption.
 * @param  boolean $allow_img Mark images for use in galleries.
 * @return boolean            false on error, true on success.
 */
function att_add($area, $item, $input, $title = '', $allow_img = true)
{
	global $usr, $db_attach, $db, $sys;
	if(!cot_auth('plug', 'attach2', 'W'))
	{
		cot_error('att_err_perms');
		return false;
	}

	$fname = att_get_filename($input);
	$ext = att_get_ext($fname);

	$upload = att_get_uploaded_file($input);

	if (att_check_file($ext) && !cot_error_found())
	{
		// This is done in 2 steps, otherwise we may run into a race condition
		$img = (int) (in_array($ext, array('gif', 'jpg', 'jpeg', 'png')) && $allow_img);

		$order = ((int)$db->query("SELECT MAX(att_order) FROM $db_attach WHERE att_area = ? AND att_item = ?", array($area, $item))->fetchColumn()) + 1;

		$affected = $db->insert($db_attach, array(
			'att_user'     => $usr['id'],
			'att_area'     => $area,
			'att_item'     => $item,
			'att_path'     => '',
			'att_filename' => $fname,
			'att_ext'      => $ext,
			'att_img'      => $img,
			'att_size'     => att_get_filesize($input),
			'att_title'    => $title,
			'att_count'    => 0,
			'att_order'    => $order,
			'att_lastmod'  => $sys['now']
		));

		if ($affected == 1)
		{
			$id = $db->lastInsertId();
			$path = att_path($area, $item, $id, $ext);
			if (att_save_uploaded_file($upload, $path))
			{
				$db->update($db_attach, array(
					'att_path' => $path,
					'att_size' => filesize($path)
				), "att_id = $id");
			}
			else
			{
				// Recover db state
				$db->delete($db_attach, "att_id = $id");
				cot_error('att_err_move');
			}
		}
		else
		{
			cot_error('att_err_db');
		}
	}

	return !cot_error_found();
}

/**
 * Removes an attachment by identifier
 *
 * @param  integer $id       Attachment ID
 * @return boolean
 */
function att_remove($id)
{
	global $cfg, $usr, $db_attach, $db;

	$res = true;

	$sql = $db->query("SELECT * FROM $db_attach
		WHERE att_id = ?", array((int) $id));

	if ($row = $sql->fetch())
	{
		if ($row['att_user'] != $usr['id'] && !cot_auth('plug', 'attach2', 'A'))
		{
			return false;
		}
		$res &= @unlink($row['att_path']);
		$res &= att_remove_thumbs($row['att_id']);
		@rmdir($cfg['plugin']['attach2']['folder'] . '/_thumbs/' . $id);
		$res &= $db->delete($db_attach, "att_id = ?", array((int) $id)) == 1;
	}
	else
	{
		return false;
	}

	return $res;
}

/**
 * Remove all attachments matching the criteria.
 *
 * @param  int    $user_id   Target user identifier
 * @param  string $area      Target module/plugin code
 * @param  int    $item_id   Target item identifier
 * @return int               Number of affected entries
 */
function att_remove_all($user_id = null, $area = null, $item_id = null)
{
	global $db_attach, $db, $cfg;

	$count = 0;

	// Build the selection criteria
	$bits = array(
		'att_user'   => (int) $user_id,
		'att_area'   => (string) $area,
		'att_item'   => (int) $item_id
	);
	$where = '';
	foreach ($bits as $key => $bit)
	{
		if (is_integer($bit) && $bit > 0 || is_string($bit) && !empty($bit))
		{
			if (!empty($where))
			{
				$where .= ' AND ';
			}
			$val = is_integer($bit) ? $bit : $db->quote($bit);
			$where .= "$key = $val";
		}
	}
	if (empty($where))
	{
		$where = '1';
	}
	// Remove files, thumbs and db records
	$sql = $db->query("SELECT * FROM $db_attach WHERE $where");
	$count = $sql->rowCount();
	foreach ($sql->fetchAll() as $row)
	{
		@unlink($row['att_path']);
		att_remove_thumbs($row['att_id']);
		@rmdir($cfg['plugin']['attach2']['folder'] . '/_thumbs/' . $row['att_id']);
	}
	$db->delete($db_attach, $where);

	return $count;
}

/**
 * Removes thumbnails matching the arguments.
 *
 * @param  string  $area Module or plugin code
 * @param  int     $item Parent item ID
 * @param  int     $id   Attachment ID
 * @return boolean       true on success, false on error
 */
function att_remove_thumbs($id)
{
	$res = true;

    $thumbPaths = att_thumb_paths($id);
    if(!empty($thumbPaths) && is_array($thumbPaths)){
        foreach (att_thumb_paths($id) as $thumb)
        {
            $res &= @unlink($thumb);
        }
    }

	return $res;
}

/**
 * Updates an existing attachment. Returns error message or empty string
 *
 * @param  int     $id    Attachment ID
 * @param  string  $input Upload field name
 * @return boolean        true on sucess, false on error
 */
function att_update_file($id, $input)
{
	global $usr, $db_attach, $db, $sys, $cfg;

	$fname = att_get_filename($input);
	$ext = att_get_ext($fname);

	$upload = att_get_uploaded_file($input);

	if (att_check_file($ext) && !cot_error_found())
	{
		$row = $db->query("SELECT * FROM $db_attach WHERE att_id = ?", array((int) $id))->fetch();

		if ($row['att_user'] != $usr['id'] && !cot_auth('plug', 'attach2', 'A'))
		{
			cot_error('att_err_perms');
			return false;
		}

		$path = $row['att_path'];

		att_remove_thumbs($row['att_id']);
		unlink($path);

		$path = att_path($row['att_area'], $row['att_item'], $row['att_id'], $ext);

		if (att_save_uploaded_file($upload, $path))
		{
			$size = filesize($path);
			$img = (int) in_array($ext, array('gif', 'jpg', 'jpeg', 'png'));

            // Image resize
            if ($img && $cfg['plugin']['attach2']['img_resize']){
                // Масштабирование
                list($width_orig, $height_orig) = getimagesize($path);
                if ($width_orig > $cfg['plugin']['attach2']['img_maxwidht'] || $height_orig > $cfg['plugin']['attach2']['img_maxheight']){
                    $input_file = $path;
                    $tmp_file =  att_path($row['att_area'], $row['att_item'], $row['att_id'], 'tmp.'.$ext);
                    att_cot_thumb($input_file, $tmp_file, $cfg['plugin']['attach2']['img_maxwidht'],
                        $cfg['plugin']['attach2']['img_maxheight'], 'auto', (int)$cfg['plugin']['attach2']['quality']);
                    @unlink($input_file);
                    @rename($tmp_file, $input_file);
                    $size->size = $file_size = filesize($input_file);
                }
            }

			$ratt = array(
				'att_ext'      => $ext,
				'att_img'      => $img,
				'att_filename' => $fname,
				'att_size'     => $size,
				'att_path'     => $path,
				'att_lastmod'  => $sys['now']
			);
			$count = $db->update($db_attach, $ratt, "att_id = ?", array((int) $id));
			if ($count != 1)
			{
				cot_error('att_err_db');
			}
		}
		else
		{
			cot_error('att_err_move');
		}
	}

	return !cot_error_found();
}

/**
 * Updates file caption only.
 *
 * @param  int     $id    Attachment ID
 * @param  string  $title Caption
 * @return boolean        true on sucess, false on error
 */
function att_update_title($id, $title)
{
	global $db_attach, $usr, $db;

	$row = $db->query("SELECT * FROM $db_attach WHERE att_id = ?", array((int) $id))->fetch();

	if($row['att_user'] != $usr['id'] && !cot_auth('plug', 'attach2', 'A'))
	{
		cot_error('att_err_perms');
		return false;
	}
	if ($row['att_title'] == $title)
	{
		// Nothing changed is OK
		return true;
	}
	if (!empty($title))
	{
		$count = $db->update($db_attach, array(
			'att_title' => $title
		), "att_id = ?", array((int) $id));

		if ($count != 1)
		{
			cot_error('att_err_db');
		}
	}
	else
	{
		cot_error('att_err_title');
	}
	return !cot_error_found();
}

/**
 * Increments a hit counter.
 *
 * @param int $id Attachment ID
 */
function att_inc_count($id)
{
	global $db_attach, $db;
	$db->query("UPDATE $db_attach SET att_count = att_count + 1 WHERE att_id = ?", array((int) $id));
}

/**
 * Fetches a single attachment object for a given item.
 * @param  string $area Target module/plugin code.
 * @param  integer $item Target item id.
 * @param  string $field Target item field
 * @param  string $column Empty string to return full row, one of the following to return a single value: 'id', 'user', 'path', 'filename', 'ext', 'img', 'size', 'title', 'count'
 * @param  string $number Attachment number within item, or one of these values: 'first', 'rand' or 'last'. Defines which image is selected.
 * @return mixed           Scalar column value, entire row as array or NULL if no attachments found.
 */
function att_get($area, $item, $field = '', $column = '', $number = 'first')
{
	global $db, $db_attach;
	static $a_cache;
	if (!isset($a_cache[$area][$item][$number]))
	{
		$order_by = $number == 'rand' ? 'RAND()' : 'att_order';
		if ($number == 'last') $order_by .= ' DESC';
		$offset = is_numeric($number) && $number > 1 ? ((int)$number - 1) . ',' : '';
        $whereFileld = '';
        if($field != '_all_') $whereFileld = "AND att_field=".$db->quote($field);
		$a_cache[$area][$item][$number] = $db->query("SELECT * FROM $db_attach
			WHERE att_area = ? AND att_item = ? $whereFileld
			ORDER BY $order_by
			LIMIT $offset 1", array($area, (int)$item))->fetch();
	}
	return empty($column) ? $a_cache[$area][$item][$number] : $a_cache[$area][$item][$number]['att_' . $column];
}

/**
 * Returns attachment thumbnail path. Generates the thumbnail first if
 * it does not exist.
 * @param  mixed   $id     Attachment ID or a row returned by att_get() function.
 * @param  integer $width  Thumbnail width in pixels
 * @param  integer $height Thumbnail height in pixels
 * @param  string  $frame  Framing mode: 'width', 'height', 'auto' or 'crop'
 * @return string          Thumbnail path on success or false on error
 */
function att_thumb($id, $width = 0, $height = 0, $frame = '')
{
	global $cfg, $db, $db_attach;

	// Support rows fetched by att_get()
	if (is_array($id))
	{
		$row = $id;
		$id = $row['att_id'];
	}

	// Validate arguments
	if (!is_numeric($id) || $id <= 0)
	{
		return '';
	}

	if (empty($frame) || !in_array($frame, array('width', 'height', 'auto', 'crop')))
	{
		$frame = $cfg['plugin']['attach2']['thumb_framing'];
	}

	if ($width <= 0)
	{
		$width = (int) $cfg['plugin']['attach2']['thumb_x'];
	}

	if ($height <= 0)
	{
		$height = (int) $cfg['plugin']['attach2']['thumb_y'];
	}

	// Attempt to load from cache
	$thumbs_folder = $cfg['plugin']['attach2']['folder'] . '/_thumbs';
	$cache_folder  = $thumbs_folder . '/' . $id;
	if (!file_exists($cache_folder))
	{
		mkdir($cache_folder, $cfg['dir_perms'], true);
	}
	$thumb_path = att_thumb_path($id, $width, $height, $frame);

	if (!$thumb_path || !file_exists($thumb_path))
	{
		// Generate a new thumbnail
		if (!isset($row))
		{
			$row = $db->query("SELECT * FROM $db_attach WHERE att_id = ?", array((int) $id))->fetch();
		}
		if (!$row || !$row['att_img'])
		{
			return false;
		}

		$orig_path = $row['att_path'];

		$thumbs_folder = $cfg['plugin']['attach2']['folder'] . '/_thumbs/' . $id;
		$thumb_path = $thumbs_folder . '/'
			. $cfg['plugin']['attach2']['prefix'] . $id
			. '-' . $width . 'x' . $height . '-' . $frame . '.' . $row['att_ext'];

		att_cot_thumb($orig_path, $thumb_path, $width, $height, $frame, (int) $cfg['plugin']['attach2']['quality'], (int) $cfg['plugin']['attach2']['upscale']);

        // Watermark
        if(!empty($cfg['plugin']['attach2']['thumb_watermark']) && file_exists($cfg['plugin']['attach2']['thumb_watermark']))
        list($th_width, $th_height) = getimagesize($thumb_path);
        if($th_width >= $cfg['plugin']['attach2']['thumb_wm_x'] || $th_height >= $cfg['plugin']['attach2']['thumb_wm_y']){
            att_watermark($thumb_path, $thumb_path, $cfg['plugin']['attach2']['thumb_watermark']);
        }
	}

	return $thumb_path;
}

/**
 * Creates image thumbnail
 *
 * @param string $source Original image path
 * @param string $target Thumbnail path
 * @param int $width Thumbnail width
 * @param int $height Thumbnail height
 * @param string $resize Resize options: crop auto width height
 * @param int $quality JPEG quality in %
 * @param boolean $upscale Upscale images smaller than thumb size
 * @return bool
 */
function att_cot_thumb($source, $target, $width, $height, $resize = 'crop', $quality = 85, $upscale = false)
{
	$ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
	list($width_orig, $height_orig) = getimagesize($source);

	if (!$upscale && $width_orig <= $width && $height_orig <= $height)
	{
		// Do not upscale smaller images, just copy them
		copy($source, $target);
		return;
	}

	$x_pos = 0;
	$y_pos = 0;

	$width = (mb_substr($width, -1, 1) == '%') ? (int) ($width_orig * (int) mb_substr($width, 0, -1) / 100) : (int) $width;
	$height = (mb_substr($height, -1, 1) == '%') ? (int) ($height_orig * (int) mb_substr($height, 0, -1) / 100) : (int) $height;

	// Avoid loading images there's not enough memory for
	if (function_exists('cot_img_check_memory') && !cot_img_check_memory($source, (int)ceil($width * $height * 4 / 1048576)))
	{
		return false;
	}

	if ($resize == 'crop')
	{
		$newimage = imagecreatetruecolor($width, $height);
		$width_temp = $width;
		$height_temp = $height;

		if ($width_orig / $height_orig > $width / $height)
		{
			$width = $width_orig * $height / $height_orig;
			$x_pos = -($width - $width_temp) / 2;
			$y_pos = 0;
		}
		else
		{
			$height = $height_orig * $width / $width_orig;
			$y_pos = -($height - $height_temp) / 2;
			$x_pos = 0;
		}
	}
	else
	{
		if ($resize == 'width' || $height == 0)
		{
			if ($width_orig > $width)
			{
				$height = $height_orig * $width / $width_orig;
			}
			else
			{
				$width = $width_orig;
				$height = $height_orig;
			}
		}
		elseif ($resize == 'height' || $width == 0)
		{
			if ($height_orig > $height)
			{
				$width = $width_orig * $height / $height_orig;
			}
			else
			{
				$width = $width_orig;
				$height = $height_orig;
			}
		}
		elseif ($resize == 'auto')
		{
			if ($width_orig < $width && $height_orig < $height)
			{
				$width = $width_orig;
				$height = $height_orig;
			}
			else
			{
				if ($width_orig / $height_orig > $width / $height)
				{
					$height = $width * $height_orig / $width_orig;
				}
				else
				{
					$width = $height * $width_orig / $height_orig;
				}
			}
		}

		$newimage = imagecreatetruecolor($width, $height); //
	}

	switch ($ext)
	{
		case 'gif':
			$oldimage = imagecreatefromgif($source);
			break;
		case 'png':
			imagealphablending($newimage, false);
			imagesavealpha($newimage, true);
			$oldimage = imagecreatefrompng($source);
			break;
		default:
			$oldimage = imagecreatefromjpeg($source);
			break;
	}

	imagecopyresampled($newimage, $oldimage, $x_pos, $y_pos, 0, 0, $width, $height, $width_orig, $height_orig);

	switch ($ext)
	{
		case 'gif':
			imagegif($newimage, $target);
			break;
		case 'png':
			imagepng($newimage, $target);
			break;
		default:
			imagejpeg($newimage, $target, $quality);
			break;
	}

	imagedestroy($newimage);
	imagedestroy($oldimage);
}

/**
 * Adds watermark for image
 * @param $source
 * @param $target
 * @param string $watermark watermark file
 * @param int $jpegquality
 * @return bool
 */
function att_watermark($source, $target, $watermark = '', $jpegquality = 85){

    if (empty($watermark)) return false;

    $sourceExt = att_get_ext($source);
    $targetExt = att_get_ext($target);

    $is_img = (int)in_array($sourceExt, array('gif', 'jpg', 'jpeg', 'png'));
    if (!$is_img) return false;

    // Load the image
    $image = imagecreatefromstring(file_get_contents($source));
    $w = imagesx($image);
    $h = imagesy($image);

    // Load the watermark
    $watermark = imagecreatefrompng($watermark);
    $ww = imagesx($watermark);
    $wh = imagesy($watermark);

    $wmAdded = false;
    if ( ($ww + 60) < $w && ($wh + 40) < $h ){
        // Insert watermark to the right bottom corner
        imagecopy($image, $watermark, intval(($w-$ww)/2), $h-$wh-20, 0, 0, $ww, $wh);
        unlink($target);
        switch($targetExt)
        {
            case 'gif':
                imagegif($image, $target);
                break;

            case 'png':
                imagepng($image, $target);
                break;

            default:
                imagejpeg($image, $target, $jpegquality);
                break;
        }
        $wmAdded = true;

    }

    imagedestroy($watermark);
    imagedestroy($image);
    return $wmAdded;
}

/**
 * Generates a file upload/edit widget.
 * Use it as CoTemplate callback.
 * @param  string $area Target module/plugin code.
 * @param  integer $item Target item id.
 * @param  string $field Target item field
 * @param  string $tpl Template code
 * @param string $width
 * @param string $height
 * @return string           Rendered widget
 */
function att_widget($area, $item, $field = '', $tpl = 'attach2.widget', $width = '100%', $height = '200')
{
	global $att_widget_present, $cfg;

	$t = new XTemplate(cot_tplfile($tpl, 'plug'));

	// Metadata
	$limits = att_get_limits();

	$t->assign(array(
		'ATTACH_AREA'    => $area,
		'ATTACH_ITEM'    => $item,
        'ATTACH_FIELD'   => $field,
		'ATTACH_EXTS'    => preg_replace('#[^a-zA-Z0-9,]#', '', $cfg['plugin']['attach2']['exts']),
		'ATTACH_ACCEPT'  => preg_replace('#[^a-zA-Z0-9,*/-]#', '',$cfg['plugin']['attach2']['accept']),
		'ATTACH_MAXSIZE' => $limits['file'],
		'ATTACH_WIDTH'  => $width,
		'ATTACH_HEIGHT' => $height
	));

	$t->parse();

	$att_widget_present = true;

	return $t->text();
}

/**
 * Renders attached items on page
 * @param  string $area Target module/plugin code
 * @param  integer $item Target item id
 * @param  string $field
 * @param  string $tpl Template code
 * @param  string $type Attachment type filter: 'files', 'images'. By default includes all attachments.
 * @param  int $limit
 * @param  string $order
 * @return string        Rendered output
 */
function att_display($area, $item, $field = '',  $tpl = 'attach2.display', $type = 'all', $limit = 0, $order = '')
{
	global $db, $db_attach;

	$t = new XTemplate(cot_tplfile($tpl, 'plug'));

	$t->assign(array(
		'ATTACH_AREA'    => $area,
		'ATTACH_ITEM'    => $item,
        'ATTACH_FIELD'   => $field,
	));

	$type_filter = '';
	if ($type == 'files')
	{
		$type_filter = " AND att_img = 0";
	}
	elseif ($type == 'images')
	{
		$type_filter = " AND att_img = 1";
	}

    if($field != '_all_'){
        $type_filter .= " AND att_field = ".$db->quote($field);
    }

    $sqlLimit = '';
    if($limit > 0) $sqlLimit = 'LIMIT '.$limit;

    $sqlOrder = ' ORDER BY att_order ASC';
    if($order != '') $sqlOrder = ' ORDER BY '.$order;

    $params = array($area);

    if(is_array($item)){
        $item = array_map('intval', $item);
        $item = "IN(".implode(',', $item).")";
    }else{
        $item = intval($item);
        $params[] = $item;
        $item = '= ?';
    }

    $sql = "SELECT * FROM $db_attach WHERE att_area = ? AND att_item $item $type_filter $sqlOrder $sqlLimit";
	$res = $db->query($sql, $params);

	$num = 1;
	foreach ($res->fetchAll() as $row)
	{
		$t->assign(array(
			'ATTACH_ROW_NUM'      => $num,
			'ATTACH_ROW_ID'       => $row['att_id'],
			'ATTACH_ROW_USER'     => $row['att_user'],
			'ATTACH_ROW_PATH'     => $row['att_path'],
			'ATTACH_ROW_URL'      => $row['att_img'] ? $row['att_path'] : cot_url('index', 'r=attach2&a=dl&id='.$row['att_id']),
			'ATTACH_ROW_FILENAME' => htmlspecialchars($row['att_filename']),
			'ATTACH_ROW_EXT'      => htmlspecialchars($row['att_ext']),
			'ATTACH_ROW_IMG'      => $row['att_img'],
			'ATTACH_ROW_SIZE'     => cot_build_filesize($row['att_size']),
			'ATTACH_ROW_TITLE'    => htmlspecialchars($row['att_title']),
			'ATTACH_ROW_COUNT'    => $row['att_count'],
			'ATTACH_ROW_LASTMOD'  => $row['att_lastmod']
		));
		$t->parse('MAIN.ATTACH_ROW');
		$num++;
	}

	$t->parse();

	return $t->text();
}

/**
 * Returns number of attachments for a specific item.
 * @param  string $area Target module/plugin code
 * @param  integer $item Target item id
 * @param  string $field Target item field
 * @param  string $type Attachment type filter: 'files', 'images'. By default includes all attachments.
 * @return integer       Number of attachments
 */
function att_count($area, $item, $field = '', $type = 'all')
{
	global $db, $db_attach;
	static $a_cache = array();

    $cacheField = ($field != '') ? $field : '_empty_field_name_';
	if (!isset($a_cache[$area][$item][$cacheField][$type]))
	{
		$type_filter = '';
		if ($type == 'files')
		{
			$type_filter = " AND att_img = 0";
		}
		elseif ($type == 'images')
		{
			$type_filter = " AND att_img = 1";
		}
        if($field != '_all_') $type_filter .= " AND att_field=".$db->quote($field);

		$a_cache[$area][$item][$cacheField][$type] = (int) $db->query("SELECT COUNT(*) FROM $db_attach WHERE att_area = ? AND att_item = ? $type_filter", array($area, (int)$item))->fetchColumn();
	}
	return $a_cache[$area][$item][$cacheField][$type];
}

/**
 * Renders files only as downloads block.
 * @param  string $area Target module/plugin code
 * @param  integer $item Target item id
 * @param  string $field
 * @param  string $tpl Template code
 * @param  int $limit
 * @param  string $order
 * @return string        Rendered output
 */
function att_downloads($area, $item, $field = '', $tpl = 'attach2.downloads', $limit = 0, $order = '')
{
	return att_display($area, $item, $field, $tpl, 'files', $limit, $order);
}

/**
 * Renders images only as a gallery.
 * @param  string $area Target module/plugin code
 * @param  integer $item Target item id
 * @param  string $field
 * @param  string $tpl Template code
 * @param  int $limit
 * @param  string $order
 * @return string        Rendered output
 */
function att_gallery($area, $item, $field = '', $tpl = 'attach2.gallery', $limit = 0, $order = '')
{
	return att_display($area, $item, $field, $tpl, 'images', $limit, $order);
}


/**
 * Generates a form input file
 * Use it as CoTemplate callback.
 *
 * @param $area
 * @param $item
 * @param string $name Input name
 * @param string $type File types. Comma separated 'all', 'file', 'image', 'audio', 'video'
 * @param int $limit file limit
 *      -1 - use plugin config value
 *       0 - unlimited
 * @param mixed $attrs Additional attributes as an associative array or a string
 * @param string $custom_rc Custom resource string name
 * @return string
 *
 * @todo проверка на то, что header уже выполнен или вывод css в header
 */
function att_filebox($area, $item, $name = '', $type = 'all', $limit = -1, $attrs = '', $custom_rc = '')
{
    global $R, $cfg;

    $jsFunc = 'cot_rc_link_file';
    if(1 || headers_sent()){
        $jsFunc = 'cot_rc_link_footer';
    }

    // Подключаем jQuery-templates только один раз
    static $jQtemlatesOut = false;
    $jQtemlates = '';
    if(!$jQtemlatesOut){
        $tpl = new XTemplate(cot_tplfile('attach2.templates', 'plug'));
        $tpl->parse();
        $jQtemlates = $tpl->text();
        $jQtemlatesOut = true;


        // todo nocache parameters
        // Generic page styles
        $jsFunc($cfg['plugins_dir'].'/attach2/tpl/filebox.css');

        // Bootstrap Image Gallery styles
        //$jsFunc($cfg['plugins_dir'].'/attach2/lib/Gallery/css/blueimp-gallery.min.css');

        // CSS to style the file input field as button and adjust the Bootstrap progress bars
        $jsFunc($cfg['plugins_dir'].'/attach2/lib/upload/css/jquery.fileupload.css');
        $jsFunc($cfg['plugins_dir'].'/attach2/lib/upload/css/jquery.fileupload-ui.css');


        /* === Java Scripts === */
        // The jQuery UI widget factory, can be omitted if jQuery UI is already included
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/upload/js/vendor/jquery.ui.widget.js');

        // The Templates plugin is included to render the upload/download listings
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/JavaScript-Templates/tmpl.min.js');

        // The Load Image plugin is included for the preview images and image resizing functionality
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/JavaScript-Load-Image/js/load-image.min.js');

        // The Canvas to Blob plugin is included for image resizing functionality
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/JavaScript-Canvas-to-Blob/canvas-to-blob.min.js');

        // blueimp Gallery script
        //cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/Gallery/js/jquery.blueimp-gallery.min.js');

        // The Iframe Transport is required for browsers without support for XHR file uploads
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/upload/js/jquery.iframe-transport.js');

        // The basic File Upload plugin
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/upload/js/jquery.fileupload.js');

        // The File Upload file processing plugin
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/upload/js/jquery.fileupload-process.js');

        // The File Upload image preview & resize plugin
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/upload/js/jquery.fileupload-image.js');

        // The File Upload audio preview plugin
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/upload/js/jquery.fileupload-audio.js');

        // The File Upload video preview plugin
        //cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/upload/js/jquery.fileupload-video.js');

        // The File Upload validation plugin
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/upload/js/jquery.fileupload-validate.js');

        // The File Upload user interface plugin
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/upload/js/jquery.fileupload-ui.js');

    //    // The localization script
    //    cot_rc_link_footer($cfg['plugins_dir'].'/attach2/lib/upload/js/locale.js');


        // The main application script
        cot_rc_link_footer($cfg['plugins_dir'].'/attach2/js/attach2.js');

        // Table Drag&Drop plugin for reordering
        cot_rc_link_footer('js/jquery.tablednd.min.js');
    }

    $formId = "{$area}_{$item}_{$name}";
    $type = str_replace(' ', '', $type);
    if(empty($type)){
        $type = array('all');
    }else{
        $type = explode(',', $type);
    }
    $type = json_encode($type);

    $t = new XTemplate(cot_tplfile('attach2.filebox', 'plug'));


    $limits = att_get_limits();
    if($limit == 0){
        $limit = 100000000000000000;
    }elseif($limit == -1){
        $limit = $cfg['plugin']['attach2']['items'] > 0 ? (int)$cfg['plugin']['attach2']['items'] : 100000000000000000;
    }

    $unikey = mb_substr(md5($formId . '_' . rand(0, 99999999)), 0, 15);
    $params = base64_encode(serialize(array(
        'area'    => $area,
        'item'    => $item,
        'field'   => $name,
        'limit'   => $limit,
        'type'    => $type,
        'unikey' => $unikey
    )));

    $action = 'index.php?r=attach2&a=upload&area='.$area.'&item='.$item;
    if(!empty($name)) $action .= '&field='.$name;
    // Metadata
    $t->assign(array(
        'ATTACH_ID'      => $formId,
        'ATTACH_AREA'    => $area,
        'ATTACH_ITEM'    => $item,
        'ATTACH_FIELD'   => $name,
        'ATTACH_LIMIT'   => $limit,
        'ATTACH_TYPE'    => $type,
        'ATTACH_PARAM'   => $params,
        'ATTACH_CHUNK'   => (int)$cfg['plugin']['attach2']['chunkSize'],
        'ATTACH_EXTS'    => preg_replace('#[^a-zA-Z0-9,]#', '', $cfg['plugin']['attach2']['exts']),
        'ATTACH_ACCEPT'  => preg_replace('#[^a-zA-Z0-9,*/-]#', '',$cfg['plugin']['attach2']['accept']),
        'ATTACH_MAXSIZE' => $limits['file'],
        'ATTACH_ACTION'  => $action
    ));

    $t->parse();
    return $t->text().$jQtemlates;
}